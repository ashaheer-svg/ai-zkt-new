<?php
class Logger
{
    public function __construct(private PDO $pdo) {}

    /** Global activity log — every user action. */
    public function activity(
        int    $userId,
        string $action,
        string $entityType = '',
        int    $entityId   = 0
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_log (user_id, action, entity_type, entity_id, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $action, $entityType, $entityId ?: null, client_ip()]);
    }

    /** Per-application audit log with optional comment. */
    public function appLog(
        int    $applicationId,
        int    $userId,
        string $action,
        string $comment = ''
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO application_logs (application_id, user_id, action, comment)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$applicationId, $userId, $action, $comment ?: null]);
    }

    /** Record a field-level edit on an application. */
    public function editLog(
        int    $applicationId,
        int    $userId,
        string $fieldName,
        mixed  $oldValue,
        mixed  $newValue
    ): void {
        if ((string)$oldValue === (string)$newValue) return; // skip no-ops
        $stmt = $this->pdo->prepare("
            INSERT INTO application_edits (application_id, edited_by, field_name, old_value, new_value)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$applicationId, $userId, $fieldName, $oldValue, $newValue]);
    }

    /** Retrieve application timeline (logs + edits merged). */
    public function getTimeline(int $applicationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT al.action, al.comment, al.created_at,
                   u.full_name, u.role, 'log' AS entry_type, '' AS field_name,
                   '' AS old_value, '' AS new_value
            FROM application_logs al
            JOIN users u ON u.id = al.user_id
            WHERE al.application_id = ?
            ORDER BY al.created_at ASC
        ");
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll();
    }

    public function getEditHistory(int $applicationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ae.*, u.full_name, u.role
            FROM application_edits ae
            JOIN users u ON u.id = ae.edited_by
            WHERE ae.application_id = ?
            ORDER BY ae.created_at DESC
        ");
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll();
    }
}
