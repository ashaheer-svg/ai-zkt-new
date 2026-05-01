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

            if (!in_array($type, [DISB_ONE_TIME,DISB_WEEKLY,DISB_MONTHLY,DISB_YEARLY])) $errors[] = 'Select a disbursement type.';
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
                        DISB_WEEKLY  => $dt->modify('+1 week'),
                        DISB_MONTHLY => $dt->modify('+1 month'),
                        DISB_YEARLY  => $dt->modify('+1 year'),
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
            $stmt = $pdo->prepare("SELECT d.*,u.full_name AS auth_name FROM disbursements d LEFT JOIN users u ON u.id=d.authorized_by WHERE d.application_id=? ORDER BY d.installment_no");
            $stmt->execute([$appId]); $disbursements = $stmt->fetchAll();
        } else {
            // Global view (1.c / sysadmin)
            $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
            $app = null;
            $sql = "SELECT d.*,a.id AS app_id,ap.full_name AS applicant_name,v.name AS village_name,u.full_name AS auth_name
                    FROM disbursements d JOIN applications a ON a.id=d.application_id JOIN applicants ap ON ap.id=a.applicant_id
                    JOIN villages v ON v.id=ap.village_id LEFT JOIN users u ON u.id=d.authorized_by
                    ORDER BY d.due_date ASC, d.id ASC";
            $page   = max(1,(int)($_GET['p']??1));
            $result = paginate($pdo, $sql, [], $page);
            $disbursements = $result['rows'];
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
            $pdo->prepare("UPDATE disbursements SET status='authorized',authorized_by=?,authorized_at=CURRENT_TIMESTAMP,notes=? WHERE id=?")
                ->execute([$auth->id(),$comment,$id]);
            $logger->appLog((int)$disb['application_id'],$auth->id(),'disbursement_authorized',"Installment #".$disb['installment_no'].". ".$comment);
            $logger->activity($auth->id(),'authorize_disbursement','disbursement',$id);
            flash('success','Disbursement #'.$disb['installment_no'].' authorized.');
            redirect('index.php?page=disbursements&app_id='.$disb['application_id']);
        }

        $pageTitle = 'Authorize Disbursement'; $activePage = 'disbursements';
        require __DIR__ . '/../views/disbursements/authorize.php';
    }

    // ── Release installment (1.c marks as paid) ───────────────────────────────
    public static function release(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_OVERALL_INCHARGE);
        csrf_verify();
        $id   = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM disbursements WHERE id=?"); $stmt->execute([$id]); $disb = $stmt->fetch();
        if (!$disb || $disb['status'] !== DISB_AUTHORIZED) {
            flash('error','Must be authorized before release.'); redirect('index.php?page=disbursements');
        }
        $notes = trim($_POST['notes'] ?? '');
        $pdo->prepare("UPDATE disbursements SET status='released',notes=? WHERE id=?")->execute([$notes,$id]);

        // Check if all installments for this application are released
        $pending = (int)$pdo->prepare("SELECT COUNT(*) FROM disbursements WHERE application_id=? AND status!='released' AND status!='cancelled'")->execute([$disb['application_id']]) ? 0 : 0;
        $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM disbursements WHERE application_id=? AND status NOT IN ('released','cancelled')");
        $stmtChk->execute([$disb['application_id']]); $remaining = (int)$stmtChk->fetchColumn();
        if ($remaining === 0) {
            $pdo->prepare("UPDATE applications SET status='completed',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$disb['application_id']]);
            $logger->appLog((int)$disb['application_id'],$auth->id(),'completed','All disbursements released.');
        }

        $logger->appLog((int)$disb['application_id'],$auth->id(),'disbursement_released',"Installment #".$disb['installment_no'].". ".$notes);
        $logger->activity($auth->id(),'release_disbursement','disbursement',$id);
        flash('success','Payment released.');
        redirect('index.php?page=disbursements&app_id='.$disb['application_id']);
    }
}
