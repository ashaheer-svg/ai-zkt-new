<?php
class CashController
{
    public static function index(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);

        $where = [];
        $params = [];
        
        // Default date range: last 1 month
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 month'));
        $endDate   = $_GET['end_date']   ?? date('Y-m-d');

        if (!empty($_GET['from'])) {
            $where[] = "ct.from_user_id = ?";
            $params[] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $where[] = "ct.to_user_id = ?";
            $params[] = $_GET['to'];
        }
        
        $where[] = "DATE(ct.created_at) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;

        $sql = "SELECT ct.*, f.full_name as from_name, t.full_name as to_name 
                FROM cash_transfers ct 
                JOIN users f ON f.id = ct.from_user_id
                JOIN users t ON t.id = ct.to_user_id";
        
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY ct.created_at DESC";
        
        $page = max(1, (int)($_GET['p'] ?? 1));
        $result = paginate($pdo, $sql, $params, $page);
        
        $transfers = $result['rows'];
        $pagination = $result;

        // Fetch unique senders and receivers for filters
        $senders = $pdo->query("SELECT DISTINCT u.id, u.full_name FROM users u JOIN cash_transfers ct ON u.id = ct.from_user_id ORDER BY u.full_name")->fetchAll();
        $receivers = $pdo->query("SELECT DISTINCT u.id, u.full_name FROM users u JOIN cash_transfers ct ON u.id = ct.to_user_id ORDER BY u.full_name")->fetchAll();

        // Fetch 1.b User Summary
        $stmtSummary = $pdo->prepare("
            SELECT 
                u.id, 
                u.full_name, 
                u.username, 
                u.role,
                u.balance,
                COALESCE(SUM(CASE WHEN d.status = 'authorized' THEN d.amount ELSE 0 END), 0) as authorized_amount,
                COALESCE(SUM(CASE WHEN d.status = 'pending' THEN d.amount ELSE 0 END), 0) as pending_amount
            FROM users u
            LEFT JOIN disbursements d ON d.assigned_to = u.id
            WHERE u.role IN (?, ?) AND u.is_active = 1
            GROUP BY u.id
            ORDER BY u.role DESC, u.full_name
        ");
        $stmtSummary->execute([ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE]);
        $userSummary = $stmtSummary->fetchAll();

        $pageTitle = 'Cash Transfer Management';
        $activePage = 'cash.transfers';
        require __DIR__ . '/../views/cash/index.php';
    }

    public static function transfer(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $toUserId = (int)$_POST['to_user_id'];
            $amount = (float)$_POST['amount'];
            $reference = trim($_POST['reference'] ?? '');

            if ($amount <= 0) {
                flash('error', 'Amount must be greater than zero.');
                redirect('index.php?page=cash.transfer');
            }

            // Verify target user is a 1.b user (Village In-Charge)
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$toUserId]);
            $toUser = $stmt->fetch();

            if (!$toUser || !in_array($toUser['role'], [ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE])) {
                flash('error', 'Transfers can only be made to 1.b or 1.c users.');
                redirect('index.php?page=cash.transfer');
            }

            $pdo->beginTransaction();
            try {
                // Record transfer
                $stmt = $pdo->prepare("INSERT INTO cash_transfers (from_user_id, to_user_id, amount, reference) VALUES (?, ?, ?, ?)");
                $stmt->execute([$auth->id(), $toUserId, $amount, $reference]);

                // Update 1.b balance
                $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$amount, $toUserId]);

                $logger->activity($auth->id(), 'cash_transfer', 'user', $toUserId);
                $pdo->commit();

                flash('success', 'Transferred ' . money($amount) . ' successfully.');
                redirect('index.php?page=cash.transfers');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', 'Failed to complete transfer: ' . $e->getMessage());
                redirect('index.php?page=cash.transfer');
            }
        }

        // Fetch all 1.b and 1.c users
        $stmt = $pdo->prepare("SELECT id, full_name, username, role, balance FROM users WHERE role IN (?, ?) AND is_active = 1 ORDER BY role DESC, full_name");
        $stmt->execute([ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE]);
        $recipients = $stmt->fetchAll();

        $pageTitle = 'Transfer Funds';
        $activePage = 'cash.transfers';
        require __DIR__ . '/../views/cash/transfer.php';
    }
}
