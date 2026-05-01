<?php
class Upload
{
    public function __construct(private PDO $pdo, private int $userId) {}

    /**
     * Store multiple uploaded files for an application.
     * @return array ['stored'=>int, 'errors'=>string[]]
     */
    public function storeMultiple(array $filesInput, int $applicationId, string $description = ''): array
    {
        $stored = 0;
        $errors = [];

        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }

        // Normalise $_FILES array for multi-upload
        $files = $this->normalise($filesInput);

        foreach ($files as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Upload error on ' . e($file['name']) . ': error code ' . $file['error'];
                continue;
            }
            if ($file['size'] > UPLOAD_MAX_SIZE) {
                $errors[] = e($file['name']) . ' exceeds the 10 MB limit.';
                continue;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
                $errors[] = e($file['name']) . ' — unsupported file type.';
                continue;
            }

            // MIME check via finfo
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mime     = $finfo->file($file['tmp_name']);
            if (!in_array($mime, UPLOAD_ALLOWED_MIME, true)) {
                $errors[] = e($file['name']) . ' — MIME type not permitted.';
                continue;
            }

            $storedName = uuid() . '.' . $ext;
            $dest       = UPLOAD_DIR . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $errors[] = 'Could not save ' . e($file['name']) . '.';
                continue;
            }

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

    public function getById(int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM application_documents WHERE id = ?');
        $stmt->execute([$documentId]);
        return $stmt->fetch() ?: null;
    }

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

    public function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    // ── Normalise multi-file input ────────────────────────────────────────────
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
