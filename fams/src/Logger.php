<?php
/**
 * System Logger and Audit Service
 * 
 * Responsible for recording all user actions, application-specific lifecycle events,
 * and field-level changes for transparency and forensic auditing.
 */
class Logger
{
    /**
     * @param PDO $pdo Database connection for log persistence
     */
    public function __construct(private PDO $pdo) {}

    /** 
     * Records a global user action to the general activity log.
     * 
     * @param int $userId ID of the user performing the action
     * @param string $action Descriptive key for the action (e.g., 'login', 'update_settings')
     * @param string $entityType Optional category of the modified entity (e.g., 'village', 'user')
     * @param int $entityId Optional ID of the modified entity
     */
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

    /** 
     * Records an event in the specific lifecycle of an application.
     * 
     * @param int $applicationId
     * @param int $userId ID of the user triggering the event
     * @param string $action Lifecycle status change or action (e.g., 'approved', 'rejected')
     * @param string $comment Optional human-readable comment or justification
     */
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

    /** 
     * Records an atomic change to a single field within an application or applicant.
     * 
     * @param int $applicationId
     * @param int $userId ID of the user making the edit
     * @param string $fieldName Name of the modified database column or logical field
     * @param mixed $oldValue Previous value before change
     * @param mixed $newValue New value after change
     */
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

    /** 
     * Retrieves the chronological history of lifecycle events for a specific application.
     * 
     * @param int $applicationId
     * @return array List of log entries with user details
     */
    public function getTimeline(int $applicationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT al.id, al.action, al.comment, al.created_at,
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

    /**
     * Retrieves a detailed history of all field-level modifications for an application.
     * 
     * @param int $applicationId
     * @return array List of edit records with old/new values and editor details
     */
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
