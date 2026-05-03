<?php
/**
 * Disbursement Controller
 * 
 * Manages the financial lifecycle of approved projects, including schedule generation,
 * installment authorization (1.c), and the physical release of funds (1.b/1.c).
 * Implements balance checking for 1.b users to ensure accountability.
 */
class DisbursementController
{
    /**
     * Generates a disbursement schedule for an approved application.
     * Supports one-time payments and recurring frequencies (weekly, monthly, etc.).
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function schedule(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_OVERALL_INCHARGE);
        $appId = (int)($_GET['app_id'] ?? 0);
        $stmt  = $pdo->prepare("SELECT a.*,ap.full_name AS applicant_name,ap.village_id FROM applications a JOIN applicants ap ON ap.id=a.applicant_id WHERE a.id=?");
        $stmt->execute([$appId]); $app = $stmt->fetch();

        if (!$app || $app['status'] !== STATUS_APPROVED) {
            flash('error','Application not available for disbursement scheduling.');
            redirect('index.php?page=applications');
        }

        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $d = $_POST;
            $type   = $d['disbursement_type'] ?? '';
            $amount = (float)($d['disbursement_amount'] ?? 0);
            $count  = (int)($d['disbursement_count'] ?? 1);
            $start  = $d['disbursement_start_date'] ?? '';

            if (!in_array($type, [DISB_ONE_TIME,DISB_WEEKLY,DISB_MONTHLY,DISB_QUARTERLY,DISB_HALF_YEARLY,DISB_YEARLY])) $errors[] = 'Select a disbursement type.';
            if ($amount <= 0)   $errors[] = 'Amount must be greater than zero.';
            if ($count < 1)     $errors[] = 'Count must be at least 1.';
            if (!$start)        $errors[] = 'Start date is required.';

            if (!$errors) {
                $pdo->beginTransaction();
                // Delete any existing schedule (re-scheduling)
                $pdo->prepare("DELETE FROM disbursements WHERE application_id=?")->execute([$appId]);

                $stmt = $pdo->prepare("INSERT INTO disbursements (application_id,installment_no,due_date,amount) VALUES (?,?,?,?)");
                $dt   = new DateTime($start);
                for ($i = 1; $i <= $count; $i++) {
                    $stmt->execute([$appId, $i, $dt->format('Y-m-d'), $amount]);
                    if ($type === DISB_ONE_TIME) break;
                    match ($type) {
                        DISB_WEEKLY       => $dt->modify('+1 week'),
                        DISB_MONTHLY      => $dt->modify('+1 month'),
                        DISB_QUARTERLY    => $dt->modify('+3 months'),
                        DISB_HALF_YEARLY  => $dt->modify('+6 months'),
                        DISB_YEARLY       => $dt->modify('+1 year'),
                    };
                }

                $pdo->prepare("UPDATE applications SET status='disbursing',disbursement_type=?,disbursement_amount=?,disbursement_count=?,disbursement_start_date=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                    ->execute([$type,$amount,$count,$start,$appId]);

                $logger->appLog($appId,$auth->id(),'disbursement_scheduled',"Type:$type, Amount:$amount, Count:$count, Start:$start");
                $logger->activity($auth->id(),'schedule_disbursement','application',$appId);
                $pdo->commit();
                flash('success','Disbursement schedule created.');
                redirect('index.php?page=disbursements&app_id='.$appId);
            }
        }

        $pageTitle = 'Disbursement Schedule — App #'.$appId; $activePage = 'disbursements';
        require __DIR__ . '/../views/disbursements/schedule.php';
    }

    /**
     * Lists disbursement installments.
     * Can view per-application schedule or a global cross-project queue with filters.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function list(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        $appId = (int)($_GET['app_id'] ?? 0);

        if ($appId) {
            // Context: Single application schedule
            $stmt = $pdo->prepare("SELECT a.*,ap.full_name AS applicant_name,ap.village_id,v.name AS village_name FROM applications a JOIN applicants ap ON ap.id=a.applicant_id JOIN villages v ON v.id=ap.village_id WHERE a.id=?");
            $stmt->execute([$appId]); $app = $stmt->fetch();
            if (!$app || !$auth->canViewApplication($app)) {
                flash('error','Access denied.'); redirect('index.php?page=applications');
            }
            $stmt = $pdo->prepare("SELECT d.*,u.full_name AS auth_name, ua.full_name as assigned_name 
                                   FROM disbursements d 
                                   LEFT JOIN users u ON u.id=d.authorized_by 
                                   LEFT JOIN users ua ON ua.id=d.assigned_to
                                   WHERE d.application_id=? ORDER BY d.installment_no");
            $stmt->execute([$appId]); $disbursements = $stmt->fetchAll();
        } else {
            // Context: Global dashboard/queue
            $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN, ROLE_VILLAGE_INCHARGE]);
            $app = null;
            
            $where = ["1=1"];
            $params = [];

            // Geographic scoping for 1.b
            if ($auth->role() === ROLE_VILLAGE_INCHARGE) {
                $myVillages = $auth->myVillages();
                if ($myVillages) {
                    $ph = implode(',', array_fill(0, count($myVillages), '?'));
                    $where[] = "ap.village_id IN ($ph)";
                    $params = array_merge($params, $myVillages);
                } else {
                    $where[] = "0=1";
                }
            }

            // Status filter (Pending vs Authorized vs Released)
            $status = $_GET['status'] ?? '';
            if ($status) {
                $where[] = "d.status = ?";
                $params[] = $status;
            }

            // Village filter
            $villageId = (int)($_GET['village_id'] ?? 0);
            if ($villageId) {
                $where[] = "ap.village_id = ?";
                $params[] = $villageId;
            }

            // Period filter (This Month, Quarter, Year)
            $period = $_GET['period'] ?? '';
            if ($period) {
                if ($period === 'month') {
                    $where[] = "strftime('%Y-%m', d.due_date) = strftime('%Y-%m', 'now')";
                } elseif ($period === 'quarter') {
                    $where[] = "((strftime('%m','now')-1)/3) = ((strftime('%m', d.due_date)-1)/3) AND strftime('%Y','now') = strftime('%Y', d.due_date)";
                } elseif ($period === 'year') {
                    $where[] = "strftime('%Y', d.due_date) = strftime('%Y', 'now')";
                }
            }

            // Multi-column sorting
            $sortField = $_GET['sort'] ?? 'due_date';
            $sortOrder = strtoupper($_GET['order'] ?? 'ASC');
            $allowedSorts = ['due_date', 'amount', 'applicant_name', 'village_name', 'status'];
            if (!in_array($sortField, $allowedSorts)) $sortField = 'due_date';
            if (!in_array($sortOrder, ['ASC', 'DESC'])) $sortOrder = 'ASC';

            $orderBy = "d.$sortField";
            if ($sortField === 'applicant_name') $orderBy = "ap.full_name";
            if ($sortField === 'village_name')   $orderBy = "v.name";
            
            $sql = "SELECT d.*, a.id AS app_id, ap.full_name AS applicant_name, v.name AS village_name, v.district as village_district, u.full_name AS auth_name, ua.full_name as assigned_name
                    FROM disbursements d 
                    JOIN applications a ON a.id = d.application_id 
                    JOIN applicants ap ON ap.id = a.applicant_id 
                    JOIN villages v ON v.id = ap.village_id 
                    LEFT JOIN users u ON u.id = d.authorized_by
                    LEFT JOIN users ua ON ua.id = d.assigned_to
                    WHERE " . implode(' AND ', $where);
            
            $sortMap = [
                'app_id'       => 'd.application_id',
                'village_name' => 'v.name',
                'due_date'     => 'd.due_date',
                'amount'       => 'd.amount',
                'status'       => 'd.status'
            ];
            $order = $sortMap[$_GET['sort'] ?? ''] ?? 'd.due_date';
            $dir   = strtoupper($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY $order $dir";

            $page = max(1, (int)($_GET['p'] ?? 1));
            $pagination = paginate($pdo, $sql, $params, $page);
            $disbursements = $pagination['rows'];

            $villages = $pdo->query("SELECT id, name FROM villages ORDER BY name")->fetchAll();
            
            // Financial Summary for the filtered view
            $stmt = $pdo->prepare("SELECT SUM(d.amount) as total_scheduled, 
                                          SUM(CASE WHEN d.status='released' THEN d.amount ELSE 0 END) as total_released
                                   FROM disbursements d
                                   JOIN applications a ON a.id = d.application_id
                                   JOIN applicants ap ON ap.id = a.applicant_id
                                   WHERE " . implode(' AND ', $where));
            $stmt->execute($params);
            $stats = $stmt->fetch();
        }

        $pageTitle = $appId ? 'Disbursements — App #'.$appId : ($_GET['status'] === DISB_AUTHORIZED ? 'Pending Payments' : 'All Disbursements');
        $activePage = ($_GET['status'] === DISB_AUTHORIZED && $auth->role() === ROLE_VILLAGE_INCHARGE) ? 'disbursements.pending_release' : 'disbursements';
        require __DIR__ . '/../views/disbursements/list.php';
    }

    /**
     * Convenience wrapper for listing authorized payments awaiting release.
     * 
     * @param PDO $pdo
     * @param Auth $auth
     * @param Logger $logger
     */
    public static function pendingRelease(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $_GET['status'] = DISB_AUTHORIZED;
        self::list($pdo, $auth, $logger);
    }

    /**
     * Authorizes a specific installment for payment.
     * Transitions status from "Pending" to "Authorized".
     * Only accessible to ROLE_OVERALL_INCHARGE or ROLE_SYSADMIN.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function authorize(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_OVERALL_INCHARGE);
        $id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT d.*,a.applicant_id,ap.full_name AS applicant_name,ap.village_id FROM disbursements d JOIN applications a ON a.id=d.application_id JOIN applicants ap ON ap.id=a.applicant_id WHERE d.id=?");
        $stmt->execute([$id]); $disb = $stmt->fetch();
        if (!$disb || $disb['status'] !== DISB_PENDING) {
            flash('error','Disbursement not available.'); redirect('index.php?page=disbursements');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $comment = trim($_POST['comment'] ?? '');
            $assignedTo = (int)$_POST['assigned_to'];

            $pdo->prepare("UPDATE disbursements SET status='authorized',authorized_by=?,authorized_at=CURRENT_TIMESTAMP,notes=?,assigned_to=? WHERE id=?")
                ->execute([$auth->id(),$comment,$assignedTo,$id]);
            
            $logger->appLog((int)$disb['application_id'],$auth->id(),'disbursement_authorized',"Installment #".$disb['installment_no'].". Assigned to: $assignedTo. ".$comment);
            $logger->activity($auth->id(),'authorize_disbursement','disbursement',$id);
            flash('success','Disbursement #'.$disb['installment_no'].' authorized.');
            redirect('index.php?page=disbursements&app_id='.$disb['application_id']);
        }

        // Fetch valid recipients for assignment
        $stmtUsers = $pdo->prepare("SELECT id, full_name, role FROM users WHERE role IN (?, ?) AND is_active = 1 ORDER BY role, full_name");
        $stmtUsers->execute([ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE]);
        $assignableUsers = $stmtUsers->fetchAll();

        $pageTitle = 'Authorize Disbursement'; $activePage = 'disbursements';
        require __DIR__ . '/../views/disbursements/authorize.php';
    }

    /**
     * Reverts an authorized installment back to pending.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function bulkAuthorize(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_OVERALL_INCHARGE);
        csrf_verify();

        $ids = (array)($_POST['disb_ids'] ?? []);
        if (empty($ids)) {
            flash('error', 'No disbursements selected.');
            redirect('index.php?page=disbursements');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE disbursements SET status='authorized', authorized_by=?, authorized_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending'");
            foreach ($ids as $id) {
                $stmt->execute([$auth->id(), (int)$id]);
                
                $appStmt = $pdo->prepare("SELECT application_id FROM disbursements WHERE id=?");
                $appStmt->execute([$id]);
                $appId = $appStmt->fetchColumn();
                if ($appId) {
                    $logger->appLog((int)$appId, $auth->id(), 'disbursement_authorized', "Bulk authorized.");
                }
            }
            $pdo->commit();
            flash('success', count($ids) . ' disbursements authorized.');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('error', 'Bulk authorization failed.');
        }
        redirect('index.php?page=disbursements');
    }

    /**
     * Handles the physical release of funds to an applicant.
     * Checks user's virtual wallet balance before allowing the transaction.
     * Transitions status to "Released" and updates user balance.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function release(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT d.*, a.id as app_id, a.applicant_id, ap.full_name as applicant_name FROM disbursements d JOIN applications a ON a.id = d.application_id JOIN applicants ap ON ap.id = a.applicant_id WHERE d.id = ?");
        $stmt->execute([$id]);
        $disb = $stmt->fetch();

        if (!$disb || $disb['status'] !== DISB_AUTHORIZED) {
            flash('error', 'Disbursement not available for release.');
            redirect('index.php?page=disbursements');
        }

        // Only the assignee or management can release
        $is1c = $auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
        $isAssigned = ($disb['assigned_to'] == $auth->id());

        if (!$is1c && !$isAssigned) {
            flash('error', 'You are not authorized to release this payment.');
            redirect('index.php?page=disbursements');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $method = $_POST['payment_method'] ?? '';
            $date = $_POST['payment_date'] ?? date('Y-m-d');
            $ref = trim($_POST['payment_reference'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if (empty($method)) {
                flash('error', 'Payment method is required.');
                redirect('index.php?page=disbursements.release&id=' . $id);
            }

            $pdo->beginTransaction();
            try {
                // Balance verification logic: 1.b users must have float in their "virtual wallet"
                if (!$is1c) {
                    $stmtUpdate = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
                    $stmtUpdate->execute([$disb['amount'], $auth->id(), $disb['amount']]);
                    
                    if ($stmtUpdate->rowCount() === 0) {
                        $stmtCheck = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                        $stmtCheck->execute([$auth->id()]);
                        $currentBalance = (float)$stmtCheck->fetchColumn();
                        throw new Exception("Insufficient balance. Your current balance is " . money($currentBalance));
                    }
                }

                // Record the payment
                $pdo->prepare("UPDATE disbursements SET status='released', payment_method=?, payment_date=?, payment_reference=?, notes=?, paid_at=CURRENT_TIMESTAMP, paid_by=? WHERE id=?")
                    ->execute([$method, $date, $ref, $notes, $auth->id(), $id]);

                // Lifecycle management: Auto-complete application if it was the last installment
                $stmtRemaining = $pdo->prepare("SELECT COUNT(*) FROM disbursements WHERE application_id=? AND status NOT IN ('released','cancelled')");
                $stmtRemaining->execute([$disb['application_id']]);
                if ((int)$stmtRemaining->fetchColumn() === 0) {
                    $pdo->prepare("UPDATE applications SET status='completed', updated_at=CURRENT_TIMESTAMP WHERE id=?")
                        ->execute([$disb['application_id']]);
                    $logger->appLog((int)$disb['application_id'], $auth->id(), 'completed', 'All disbursements released.');
                }

                $logger->appLog((int)$disb['application_id'], $auth->id(), 'disbursement_released', "Installment #" . $disb['installment_no'] . " via $method. Date: $date");
                $logger->activity($auth->id(), 'release_disbursement', 'disbursement', $id);
                
                $pdo->commit();
                flash('success', 'Payment released successfully.');
                redirect('index.php?page=disbursements&app_id=' . $disb['application_id']);
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('error', $e->getMessage());
                redirect('index.php?page=disbursements.release&id=' . $id);
            }
        }

        $pageTitle = 'Release Payment — Installment #' . $disb['installment_no'];
        $activePage = 'disbursements';
        require __DIR__ . '/../views/disbursements/release.php';
    }
}
