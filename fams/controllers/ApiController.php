<?php
/**
 * Api Controller
 * 
 * Provides a RESTful interface for the mobile application.
 * Handles token-based authentication, project synchronization, and field
 * document/photo uploads.
 */
class ApiController
{
    /**
     * Internal authentication helper.
     * Extracts Bearer token from Authorization header and validates against api_tokens table.
     * Exits with 401 JSON response on failure.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service for token validation.
     * @return void
     */
    private static function authenticate(PDO $pdo, Auth $auth): void
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = '';
        if (preg_match('/Bearer\s+(.*)/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        if (empty($token) || !$auth->loginByToken($token)) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized or user deactivated']);
            exit;
        }
    }

    /**
     * Returns list of active document types for mobile UI selection.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @return void
     */
    public static function documentTypes(PDO $pdo, Auth $auth): void
    {
        self::authenticate($pdo, $auth);
        $stmt = $pdo->query("SELECT id, name FROM document_types WHERE is_active = 1 ORDER BY name");
        header('Content-Type: application/json');
        echo json_encode($stmt->fetchAll());
    }

    /**
     * Lists applications (projects) visible to the authenticated user.
     * Respects geographic scoping (village-level access).
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @return void
     */
    public static function projects(PDO $pdo, Auth $auth): void
    {
        self::authenticate($pdo, $auth);
        
        $villages = $auth->myVillages();
        $where = ["1=1"];
        $params = [];

        if (!empty($villages)) {
            $where[] = "ap.village_id IN (" . implode(',', array_fill(0, count($villages), '?')) . ")";
            $params = array_merge($params, $villages);
        } elseif (!$auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            // No villages assigned and not a management user
            header('Content-Type: application/json');
            echo json_encode([]);
            exit;
        }

        $sql = "SELECT a.id, ap.full_name as applicant_name, a.status, v.name as village_name, fc.name as category_name
                FROM applications a
                JOIN applicants ap ON ap.id = a.applicant_id
                JOIN villages v ON v.id = ap.village_id
                JOIN fund_categories fc ON fc.id = a.fund_category_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.created_at DESC LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        header('Content-Type: application/json');
        echo json_encode($stmt->fetchAll());
    }

    /**
     * Handles field document uploads from the mobile app.
     * Validates file presence, application ownership/access, and metadata.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function upload(PDO $pdo, Auth $auth, Logger $logger): void
    {
        self::authenticate($pdo, $auth);

        $appId = (int)($_POST['application_id'] ?? 0);
        $docType = trim($_POST['doc_type'] ?? 'Photo');
        $lang = trim($_POST['doc_language'] ?? 'English');
        $desc = trim($_POST['description'] ?? '');

        if (!$appId) {
            http_response_code(400);
            echo json_encode(['error' => 'Application ID missing']);
            exit;
        }

        // Verify access to this application before accepting payload
        $stmt = $pdo->prepare("SELECT a.*, ap.village_id FROM applications a JOIN applicants ap ON ap.id = a.applicant_id WHERE a.id = ?");
        $stmt->execute([$appId]);
        $app = $stmt->fetch();
        if (!$app || !$auth->canViewApplication($app)) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied to this project']);
            exit;
        }

        if (empty($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No file uploaded']);
            exit;
        }

        $upload = new Upload($pdo, $auth->id());
        $result = $upload->storeMultiple($_FILES, $appId, $desc);
        
        if ($result['stored'] > 0) {
            // Enrich document metadata (specifically for mobile uploads)
            $pdo->prepare("UPDATE application_documents SET doc_type = ?, doc_language = ? WHERE id = (SELECT MAX(id) FROM application_documents WHERE uploaded_by = ?)")
                ->execute([$docType, $lang, $auth->id()]);

            $logger->appLog($appId, $auth->id(), 'mobile_upload', "Uploaded $docType ($lang) via Mobile API.");
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Image uploaded successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Upload failed', 'details' => $result['errors']]);
        }
    }

    /**
     * Translation Proxy with Persistent DB Cache.
     * 
     * Checks translation_cache first (instant return on hit).
     * On cache miss, calls MyMemory free translation API server-side
     * (avoids CORS issues) and stores the result permanently.
     * 
     * Expects POST: table, record_id, field, source_lang, text
     * Returns JSON: { translated: string, cached: bool }
     * 
     * @param PDO  $pdo  The database connection instance.
     * @param Auth $auth The authentication service.
     * @return void
     */
    public static function translate(PDO $pdo, Auth $auth): void
    {
        // Session-authenticated (no Bearer token needed)
        $auth->requireLogin();
        header('Content-Type: application/json');

        $tableName  = trim($_POST['table']      ?? '');
        $recordId   = (int)($_POST['record_id'] ?? 0);
        $fieldName  = trim($_POST['field']      ?? '');
        $sourceLang = trim($_POST['source_lang'] ?? 'auto');
        $text       = trim($_POST['text']       ?? '');

        // Only allow known tables to prevent SQL injection
        $allowedTables = ['applications', 'applicants', 'application_logs', 'disbursements'];
        if (!in_array($tableName, $allowedTables) || !$recordId || !$fieldName || $text === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            return;
        }

        // 1. Check translation_cache (zero API cost on hit)
        $cacheStmt = $pdo->prepare(
            "SELECT translated_text FROM translation_cache
             WHERE table_name=? AND record_id=? AND field_name=? LIMIT 1"
        );
        $cacheStmt->execute([$tableName, $recordId, $fieldName]);
        $cached = $cacheStmt->fetchColumn();

        if ($cached !== false) {
            echo json_encode(['translated' => $cached, 'cached' => true]);
            return;
        }

        // 2. Cache miss — call MyMemory free API (server-side, no CORS)
        $langPair = ($sourceLang === 'auto' || $sourceLang === 'en')
            ? 'ta|en'
            : "{$sourceLang}|en";

        $apiUrl = 'https://api.mymemory.translated.net/get?q='
                . urlencode($text)
                . '&langpair=' . urlencode($langPair);

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header'  => "User-Agent: FAMS-NCT/1.0\r\n",
            ]
        ]);

        $response = @file_get_contents($apiUrl, false, $ctx);

        if ($response === false) {
            http_response_code(503);
            echo json_encode(['error' => 'Translation service unavailable']);
            return;
        }

        $data       = json_decode($response, true);
        $translated = $data['responseData']['translatedText'] ?? null;

        if (!$translated || ($data['responseStatus'] ?? '') != 200) {
            http_response_code(502);
            echo json_encode(['error' => 'Translation failed', 'detail' => $data['responseDetails'] ?? '']);
            return;
        }

        // 3. Persist to cache — INSERT OR REPLACE invalidates stale cache on edit
        $pdo->prepare(
            "INSERT OR REPLACE INTO translation_cache
             (table_name, record_id, field_name, source_lang, translated_text, translated_at)
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
        )->execute([$tableName, $recordId, $fieldName, $sourceLang, $translated]);

        echo json_encode(['translated' => $translated, 'cached' => false]);
    }
}
