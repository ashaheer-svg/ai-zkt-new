<?php
class DashboardController
{
    public static function overview(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);

        // ── Application counts by status ──────────────────────────────────────
        $stmt = $pdo->query("
            SELECT status, COUNT(*) AS cnt
            FROM applications
            WHERE is_privileged = 0 OR 1=1
            GROUP BY status
        ");
        $statusCounts = [];
        foreach ($stmt->fetchAll() as $row) {
            $statusCounts[$row['status']] = (int)$row['cnt'];
        }

        // ── Funds totals ──────────────────────────────────────────────────────
        $totalRequested = (float)$pdo->query(
            "SELECT COALESCE(SUM(amount_requested),0) FROM applications WHERE status != 'rejected'"
        )->fetchColumn();

        $totalDisbursed = (float)$pdo->query(
            "SELECT COALESCE(SUM(amount),0) FROM disbursements WHERE status = 'released'"
        )->fetchColumn();

        $totalAuthorized = (float)$pdo->query(
            "SELECT COALESCE(SUM(amount),0) FROM disbursements WHERE status = 'authorized'"
        )->fetchColumn();

        // ── Cash flow: upcoming disbursements (next 90 days) ──────────────────
        $stmt = $pdo->prepare("
            SELECT d.*, a.id AS app_id, ap.full_name AS applicant_name,
                   v.name AS village_name, fc.name AS category_name
            FROM disbursements d
            JOIN applications a  ON a.id  = d.application_id
            JOIN applicants ap   ON ap.id = a.applicant_id
            JOIN villages v      ON v.id  = ap.village_id
            JOIN fund_categories fc ON fc.id = a.fund_category_id
            WHERE d.status = 'pending'
              AND (d.due_date IS NULL OR d.due_date <= date('now', '+90 days'))
            ORDER BY d.due_date ASC
            LIMIT 50
        ");
        $stmt->execute();
        $upcomingDisbursements = $stmt->fetchAll();

        $cashFlow90 = (float)$pdo->query(
            "SELECT COALESCE(SUM(amount),0) FROM disbursements
             WHERE status = 'pending' AND (due_date IS NULL OR due_date <= date('now','+90 days'))"
        )->fetchColumn();

        // ── Per-village summary ───────────────────────────────────────────────
        $stmt = $pdo->query("
            SELECT v.name AS village, COUNT(a.id) AS total,
                   SUM(CASE WHEN a.status='approved' OR a.status='disbursing' OR a.status='completed' THEN 1 ELSE 0 END) AS approved,
                   COALESCE(SUM(CASE WHEN d.status='released' THEN d.amount ELSE 0 END),0) AS disbursed
            FROM villages v
            LEFT JOIN applicants ap ON ap.village_id = v.id
            LEFT JOIN applications a ON a.applicant_id = ap.id
            LEFT JOIN disbursements d ON d.application_id = a.id
            GROUP BY v.id, v.name
            ORDER BY total DESC
        ");
        $villageSummary = $stmt->fetchAll();

        // ── Recent activity ───────────────────────────────────────────────────
        $stmt = $pdo->query("
            SELECT al.*, u.full_name, u.role
            FROM activity_log al
            LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.created_at DESC
            LIMIT 20
        ");
        $recentActivity = $stmt->fetchAll();

        $pageTitle  = 'Overview Dashboard';
        $activePage = 'dashboard';
        require __DIR__ . '/../views/dashboard/overview.php';
    }
}
