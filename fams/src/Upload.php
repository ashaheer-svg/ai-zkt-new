<?php
/**
 * File Upload and Document Management Service
 * 
 * Handles multi-file uploads, security validation (MIME, size, extension),
 * database registration, and document streaming for the system.
 */
class Upload
{
    /**
     * @param PDO $pdo Database connection for document records
     * @param int $userId ID of the user performing the upload
     */
    public function __construct(private PDO $pdo, private int $userId) {}

    /**
     * Processes and stores multiple uploaded files for a specific application.
     * Includes validation for size, extension, and actual MIME content.
     * 
     * @param array $filesInput The raw $_FILES array entry
     * @param int $applicationId Associated application ID
     * @param string $description Optional description for the batch of files
     * @return array ['stored' => int, 'errors' => string[]]
     */
    public function storeMultiple(array $filesInput, int $applicationId, string $description = ''): array
    {
        $stored = 0;
        $errors = [];

        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }

        // Normalise $_FILES array for multi-upload handling
        $files = $this->normalise($filesInput);

        foreach ($files as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;
            
            // Basic error check
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Upload error on ' . e($file['name']) . ': error code ' . $file['error'];
                continue;
            }

            // Size check (defined in app.php)
            if ($file['size'] > UPLOAD_MAX_SIZE) {
                $errors[] = e($file['name']) . ' exceeds the 10 MB limit.';
                continue;
            }

            // Extension check
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
                $errors[] = e($file['name']) . ' — unsupported file type.';
                continue;
            }

            // MIME content check via finfo (prevents extension spoofing)
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mime     = $finfo->file($file['tmp_name']);
            if (!in_array($mime, UPLOAD_ALLOWED_MIME, true)) {
                $errors[] = e($file['name']) . ' — MIME type not permitted.';
                continue;
            }

            // Generate unique filename to prevent collisions and overwrites
            $storedName = uuid() . '.' . $ext;
            $dest       = UPLOAD_DIR . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $errors[] = 'Could not save ' . e($file['name']) . '.';
                continue;
            }

            // Register in database
            $stmt = $this->pdo->prepare("
                INSERT INTO application_documents
                    (application_id, uploaded_by, original_filename, stored_filename, mime_type, file_size, description)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $applicationId,
                $this->userId,
                $file['name'],
                $storedName,
                $mime,
                $file['size'],
                $description ?: null,
            ]);
            $stored++;
        }

        return compact('stored', 'errors');
    }

    /**
     * Retrieves all document records associated with an application.
     * 
     * @param int $applicationId
     * @return array
     */
    public function getForApplication(int $applicationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.full_name AS uploader_name
            FROM application_documents d
            JOIN users u ON u.id = d.uploaded_by
            WHERE d.application_id = ?
            ORDER BY d.created_at ASC
        ");
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll();
    }

    /**
     * Fetches metadata for a single document.
     * 
     * @param int $documentId
     * @return array|null
     */
    public function getById(int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM application_documents WHERE id = ?');
        $stmt->execute([$documentId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Streams a file directly to the browser with appropriate headers.
     * Images are streamed 'inline', other types as 'attachment'.
     * 
     * @param int $documentId
     * @throws Exception If file not found
     */
    public function stream(int $documentId): never
    {
        $doc = $this->getById($documentId);
        if (!$doc) {
            http_response_code(404);
            die('Document not found.');
        }

        $path = UPLOAD_DIR . $doc['stored_filename'];
        if (!file_exists($path)) {
            http_response_code(404);
            die('File missing from storage.');
        }

        $isInline = str_starts_with($doc['mime_type'], 'image/');
        header('Content-Type: ' . $doc['mime_type']);
        header('Content-Disposition: ' . ($isInline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($doc['original_filename']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /**
     * Deletes a document from both filesystem and database.
     * 
     * @param int $documentId
     * @return bool True if deleted successfully
     */
    public function delete(int $documentId): bool
    {
        $doc = $this->getById($documentId);
        if (!$doc) return false;

        $path = UPLOAD_DIR . $doc['stored_filename'];
        if (file_exists($path)) unlink($path);

        $stmt = $this->pdo->prepare('DELETE FROM application_documents WHERE id = ?');
        $stmt->execute([$documentId]);
        return true;
    }

    /**
     * Checks if a MIME type represents an image.
     * 
     * @param string $mimeType
     * @return bool
     */
    public function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Helper to transform PHP's multi-file $_FILES array into a cleaner list.
     * 
     * @param array $input
     * @return array
     */
    private function normalise(array $input): array
    {
        $files = [];
        if (is_array($input['name'])) {
            $count = count($input['name']);
            for ($i = 0; $i < $count; $i++) {
                $files[] = [
                    'name'     => $input['name'][$i],
                    'type'     => $input['type'][$i],
                    'tmp_name' => $input['tmp_name'][$i],
                    'error'    => $input['error'][$i],
                    'size'     => $input['size'][$i],
                ];
            }
        } else {
            $files[] = $input;
        }
        return $files;
    }
}
