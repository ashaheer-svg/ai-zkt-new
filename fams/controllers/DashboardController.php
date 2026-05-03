<?php
/**
 * Dashboard Controller
 * 
 * Provides high-level operational oversight for management and role-specific
 * action items for field staff. Aggregates financial totals, application counts,
 * and upcoming cash flow projections.
 */
class DashboardController
{
    /**
     * Renders the primary dashboard overview.
     * Logic branches based on user role to show relevant metrics:
     * - 1.c (Management): Global totals, cash flow, and village summaries.
     * - 1.b (Village Staff): Assigned payment instructions, geographic authorization totals, and wallet balance.
     * 
     * @param PDO $pdo
     * @param Auth $auth
     * @param Logger $logger
     */
    public static function overview(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN, ROLE_VILLAGE_INCHARGE]);

        // ── 1. Application distribution by state ────────────────────────────────
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

        // ── 2. Global Financial Aggregates ──────────────────────────────────────
        $totalRequested = (float)$pdo->query(
            "SELECT COALESCE(SUM(amount_requested),0) FROM applications WHERE status != 'rejected'"
        )->fetchColumn();

        $totalDisbursed = (float)$pdo->query(
            "SELECT COALESCE(SUM(amount),0) FROM disbursements WHERE status = 'released'"
        )->fetchColumn();

        $totalAuthorized = (float)$pdo->query(
            "SELECT COALESCE(SUM(amount),0) FROM disbursements WHERE status = 'authorized'"
        )->fetchColumn();

        // ── 3. Cash Flow Projection (Next 90 Days) ───────────────────────────
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

        // ── 4. Geographic Performance Summary ───────────────────────────────
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

        // ── 5. System-wide Audit Stream ──────────────────────────────────────
        $stmt = $pdo->query("
            SELECT al.*, u.full_name, u.role
            FROM activity_log al
            LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.created_at DESC
            LIMIT 20
        ");
        $recentActivity = $stmt->fetchAll();

        // ── 6. Role-Specific action data (1.b / Field Staff) ──────────────
        $myBalance = 0;
        $myInstructions = [];
        $myVillageAuthorized = 0;
        if ($auth->role() === ROLE_VILLAGE_INCHARGE) {
            // User's available float for disbursement
            $stmtBal = $pdo->prepare("SELECT balance FROM users WHERE id=?");
            $stmtBal->execute([$auth->id()]);
            $myBalance = (float)$stmtBal->fetchColumn();

            $myVillages = $auth->myVillages();
            if ($myVillages) {
                $ph = implode(',', array_fill(0, count($myVillages), '?'));
                $stmtAuth = $pdo->prepare("
                    SELECT COALESCE(SUM(d.amount),0) 
                    FROM disbursements d
                    JOIN applications a ON a.id = d.application_id
                    JOIN applicants ap ON ap.id = a.applicant_id
                    WHERE d.status = 'authorized' AND ap.village_id IN ($ph)
                ");
                $stmtAuth->execute($myVillages);
                $myVillageAuthorized = (float)$stmtAuth->fetchColumn();
            }

            // Direct payment assignments
            $stmtInst = $pdo->prepare("
                SELECT d.*, ap.full_name as applicant_name, v.name as village_name
                FROM disbursements d
                JOIN applications a ON a.id = d.application_id
                JOIN applicants ap ON ap.id = a.applicant_id
                JOIN villages v ON v.id = ap.village_id
                WHERE d.assigned_to = ? AND d.status = 'authorized'
                ORDER BY d.due_date ASC
            ");
            $stmtInst->execute([$auth->id()]);
            $myInstructions = $stmtInst->fetchAll();
        }

        $pageTitle  = 'Overview Dashboard';
        $activePage = 'dashboard';
        require __DIR__ . '/../views/dashboard/overview.php';
    }
}
