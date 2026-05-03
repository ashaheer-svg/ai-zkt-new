<?php
class DisbursementController
{
    // ── Schedule (1.c creates installment plan) ───────────────────────────────
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

    // ── List installments ─────────────────────────────────────────────────────
    public static function list(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        $appId = (int)($_GET['app_id'] ?? 0);

        if ($appId) {
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
            // Global view (1.c / sysadmin)
            $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
            $app = null;
            
            $where = ["1=1"];
            $params = [];

            // Village filter
            $villageId = (int)($_GET['village_id'] ?? 0);
            if ($villageId) {
                $where[] = "ap.village_id = ?";
                $params[] = $villageId;
            }

            // Period filter
            $period = $_GET['period'] ?? '';
            if ($period) {
                if ($period === 'month') {
                    $where[] = "strftime('%Y-%m', d.due_date) = strftime('%Y-%m', 'now')";
                } elseif ($period === 'quarter') {
                    // Current quarter: 1 (Jan-Mar), 2 (Apr-Jun), 3 (Jul-Sep), 4 (Oct-Dec)
                    $where[] = "((strftime('%m','now')-1)/3) = ((strftime('%m', d.due_date)-1)/3) AND strftime('%Y','now') = strftime('%Y', d.due_date)";
                } elseif ($period === 'year') {
                    $where[] = "strftime('%Y', d.due_date) = strftime('%Y', 'now')";
                }
            }

            // Sorting
            $sortField = $_GET['sort'] ?? 'due_date';
            $sortOrder = strtoupper($_GET['order'] ?? 'ASC');
            $allowedSorts = ['due_date', 'amount', 'applicant_name', 'village_name', 'status'];
            if (!in_array($sortField, $allowedSorts)) $sortField = 'due_date';
            if (!in_array($sortOrder, ['ASC', 'DESC'])) $sortOrder = 'ASC';

            // Custom sort mapping if needed
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
            
            // Stats
            $stmt = $pdo->prepare("SELECT SUM(d.amount) as total_scheduled, 
                                          SUM(CASE WHEN d.status='released' THEN d.amount ELSE 0 END) as total_released
                                   FROM disbursements d
                                   JOIN applications a ON a.id = d.application_id
                                   JOIN applicants ap ON ap.id = a.applicant_id
                                   WHERE " . implode(' AND ', $where));
            $stmt->execute($params);
            $stats = $stmt->fetch();
        }

        $pageTitle = $appId ? 'Disbursements — App #'.$appId : 'All Disbursements';
        $activePage = 'disbursements';
        require __DIR__ . '/../views/disbursements/list.php';
    }

    // ── Authorize installment (1.c) ───────────────────────────────────────────
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

        // Fetch 1.b and 1.c users for assignment
        $stmtUsers = $pdo->prepare("SELECT id, full_name, role FROM users WHERE role IN (?, ?) AND is_active = 1 ORDER BY role, full_name");
        $stmtUsers->execute([ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE]);
        $assignableUsers = $stmtUsers->fetchAll();

        $pageTitle = 'Authorize Disbursement'; $activePage = 'disbursements';
        require __DIR__ . '/../views/disbursements/authorize.php';
    }

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
                // For bulk, we might not assign to a specific 1.b user yet, or we default to the first one in village.
                // To keep it simple for now, we just authorize. The assignment can happen individually or we can add it here.
                // User said: "1.c level user should be able to select multiple projects at once for disbursement approval."
                $stmt->execute([$auth->id(), (int)$id]);
                
                // Get application ID for logging
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

    // ── Release installment (1.b or 1.c marks as paid) ───────────────────────
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

        // Access check: Either 1.c, or the assigned 1.b user
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
                // If 1.b is releasing, check and deduct balance
                if (!$is1c) {
                    $stmtUpdate = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
                    $stmtUpdate->execute([$disb['amount'], $auth->id(), $disb['amount']]);
                    
                    if ($stmtUpdate->rowCount() === 0) {
                        // Check why it failed (insufficient balance or invalid user)
                        $stmtCheck = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                        $stmtCheck->execute([$auth->id()]);
                        $currentBalance = (float)$stmtCheck->fetchColumn();
                        throw new Exception("Insufficient balance. Your current balance is " . money($currentBalance));
                    }
                }

                // Update disbursement
                $pdo->prepare("UPDATE disbursements SET status='released', payment_method=?, payment_date=?, payment_reference=?, notes=?, paid_at=CURRENT_TIMESTAMP, paid_by=? WHERE id=?")
                    ->execute([$method, $date, $ref, $notes, $auth->id(), $id]);

                // Check if application is completed
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
