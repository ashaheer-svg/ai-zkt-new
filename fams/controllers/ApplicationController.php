<?php
class ApplicationController
{
    // ── List ──────────────────────────────────────────────────────────────────
    public static function list(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        $role     = $auth->role();
        $villages = $auth->myVillages();
        $showAll  = ($auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]) && isset($_GET['show_all']));

        $where  = ['1=1'];
        $params = [];

        // Privileged: only 1.c / sysadmin
        if (!$auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            $where[] = 'a.is_privileged = 0';
        }

        // Status scope by role
        if ($role === ROLE_DATA_ENTRY) {
            $where[] = "a.created_by = ?";
            $params[] = $auth->id();
        } elseif ($role === ROLE_VILLAGE_INCHARGE || $role === ROLE_VERIFICATION) {
            $where[] = "a.status NOT IN ('draft','pending_validation')";
            if ($villages) {
                $ph = implode(',', array_fill(0, count($villages), '?'));
                $where[] = "ap.village_id IN ($ph)";
                $params  = array_merge($params, $villages);
            }
        } elseif ($auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            if (!$showAll) {
                $where[] = "a.status NOT IN ('draft','pending_validation')";
            }
        }

        // Search
        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $where[]  = "(ap.full_name LIKE ? OR ap.id_number LIKE ? OR ap.telephone LIKE ?)";
            $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
        }

        // Status filter
        $filterStatus = $_GET['status'] ?? '';
        if ($filterStatus) { $where[] = 'a.status = ?'; $params[] = $filterStatus; }

        $sql = "SELECT a.*, ap.full_name AS applicant_name, ap.id_number,
                       ap.village_id, v.name AS village_name, fc.name AS category_name,
                       u.full_name AS creator_name
                FROM applications a
                JOIN applicants ap      ON ap.id = a.applicant_id
                JOIN villages v         ON v.id  = ap.village_id
                JOIN fund_categories fc ON fc.id = a.fund_category_id
                JOIN users u            ON u.id  = a.created_by
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.updated_at DESC";

        $page   = max(1, (int)($_GET['p'] ?? 1));
        $result = paginate($pdo, $sql, $params, $page);

        $pageTitle  = 'Projects';
        $activePage = 'applications';
        require __DIR__ . '/../views/applications/list.php';
    }

    // ── Create ────────────────────────────────────────────────────────────────
    public static function create(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_DATA_ENTRY, ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);

        $villages   = self::_allowedVillages($pdo, $auth);
        $categories = $pdo->query("SELECT * FROM fund_categories WHERE is_active=1 ORDER BY name")->fetchAll();
        $errors     = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $d = $_POST;

            // Validate
            if (empty($d['full_name']))       $errors[] = 'Full name is required.';
            if (empty($d['village_id']))       $errors[] = 'Village is required.';
            if (empty($d['fund_category_id'])) $errors[] = 'Fund category is required.';
            if (empty($d['amount_requested'])) $errors[] = 'Amount requested is required.';
            if (empty($d['gender']))           $errors[] = 'Gender is required.';

            if (!$errors) {
                $pdo->beginTransaction();
                // Insert applicant
                $stmt = $pdo->prepare("INSERT INTO applicants
                    (full_name,address,gender,age,id_number,telephone,village_id,notes)
                    VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $d['full_name'], $d['address'] ?? '', $d['gender'],
                    $d['age'] ?: null, $d['id_number'] ?? '', $d['telephone'] ?? '',
                    $d['village_id'], $d['notes'] ?? ''
                ]);
                $applicantId = (int)$pdo->lastInsertId();

                // Spouse
                if (!empty($d['has_spouse'])) {
                    $pdo->prepare("INSERT INTO applicant_spouse (applicant_id,full_name,age,id_number,telephone) VALUES (?,?,?,?,?)")
                        ->execute([$applicantId, $d['spouse_name'], $d['spouse_age'] ?: null, $d['spouse_id'] ?? '', $d['spouse_tel'] ?? '']);
                }

                // Children
                if (!empty($d['child_name'])) {
                    $stmt = $pdo->prepare("INSERT INTO applicant_children (applicant_id,full_name,age,gender) VALUES (?,?,?,?)");
                    foreach ($d['child_name'] as $i => $cn) {
                        if (trim($cn) === '') continue;
                        $stmt->execute([$applicantId, $cn, $d['child_age'][$i] ?: null, $d['child_gender'][$i] ?? '']);
                    }
                }

                // Determine status
                $isHigherRole = $auth->hasRole([ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
                $status  = $isHigherRole ? STATUS_SUBMITTED  : STATUS_PENDING_VALIDATION;
                $isValid = $isHigherRole ? 1 : 0;

                $stmt = $pdo->prepare("INSERT INTO applications
                    (applicant_id,fund_category_id,amount_requested,status,is_valid,created_by)
                    VALUES (?,?,?,?,?,?)");
                $stmt->execute([$applicantId, $d['fund_category_id'], $d['amount_requested'], $status, $isValid, $auth->id()]);
                $appId = (int)$pdo->lastInsertId();

                // Documents
                if (!empty($_FILES['documents']['name'][0])) {
                    $upload = new Upload($pdo, $auth->id());
                    $result = $upload->storeMultiple($_FILES['documents'], $appId, $d['doc_description'] ?? '');
                    if ($result['errors']) flash('warning', implode(' ', $result['errors']));
                }

                $logger->appLog($appId, $auth->id(), 'created', 'Application created.');
                $logger->activity($auth->id(), 'create_application', 'application', $appId);
                $pdo->commit();

                flash('success', 'Application submitted successfully.');
                redirect('index.php?page=applications.view&id=' . $appId);
            }
        }

        $pageTitle  = 'New Project';
        $activePage = 'applications';
        require __DIR__ . '/../views/applications/create.php';
    }

    // ── Edit ──────────────────────────────────────────────────────────────────
    public static function edit(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        $id  = (int)($_GET['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app || !$auth->canEditApplication($app)) {
            flash('error', 'Not found or not editable.'); redirect('index.php?page=applications');
        }

        $applicant  = $pdo->prepare("SELECT * FROM applicants WHERE id=?")->execute([$app['applicant_id']]) ? null : null;
        $stmt       = $pdo->prepare("SELECT * FROM applicants WHERE id=?"); $stmt->execute([$app['applicant_id']]); $applicant = $stmt->fetch();
        $stmtS      = $pdo->prepare("SELECT * FROM applicant_spouse WHERE applicant_id=?"); $stmtS->execute([$app['applicant_id']]); $spouse = $stmtS->fetch();
        $stmtC      = $pdo->prepare("SELECT * FROM applicant_children WHERE applicant_id=? ORDER BY id"); $stmtC->execute([$app['applicant_id']]); $children = $stmtC->fetchAll();
        $villages   = self::_allowedVillages($pdo, $auth);
        $categories = $pdo->query("SELECT * FROM fund_categories WHERE is_active=1 ORDER BY name")->fetchAll();
        $errors     = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $d = $_POST;
            if (empty($d['full_name']))   $errors[] = 'Full name required.';
            if (empty($d['amount_requested'])) $errors[] = 'Amount required.';

            if (!$errors) {
                $pdo->beginTransaction();
                $fields = ['full_name','address','gender','age','id_number','telephone','notes'];
                foreach ($fields as $f) {
                    if (($applicant[$f] ?? '') !== ($d[$f] ?? '')) {
                        $logger->editLog($id, $auth->id(), $f, $applicant[$f], $d[$f] ?? '');
                    }
                }
                if (($app['amount_requested']) != ($d['amount_requested'])) {
                    $logger->editLog($id, $auth->id(), 'amount_requested', $app['amount_requested'], $d['amount_requested']);
                }
                $pdo->prepare("UPDATE applicants SET full_name=?,address=?,gender=?,age=?,id_number=?,telephone=?,notes=? WHERE id=?")
                    ->execute([$d['full_name'],$d['address']??'',$d['gender'],$d['age']?:null,$d['id_number']??'',$d['telephone']??'',$d['notes']??'',$app['applicant_id']]);
                $pdo->prepare("UPDATE applications SET fund_category_id=?,amount_requested=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                    ->execute([$d['fund_category_id'],$d['amount_requested'],$id]);

                // Spouse
                $pdo->prepare("DELETE FROM applicant_spouse WHERE applicant_id=?")->execute([$app['applicant_id']]);
                if (!empty($d['has_spouse'])) {
                    $pdo->prepare("INSERT INTO applicant_spouse (applicant_id,full_name,age,id_number,telephone) VALUES (?,?,?,?,?)")
                        ->execute([$app['applicant_id'],$d['spouse_name'],$d['spouse_age']?:null,$d['spouse_id']??'',$d['spouse_tel']??'']);
                }
                $pdo->prepare("DELETE FROM applicant_children WHERE applicant_id=?")->execute([$app['applicant_id']]);
                if (!empty($d['child_name'])) {
                    $stmt = $pdo->prepare("INSERT INTO applicant_children (applicant_id,full_name,age,gender) VALUES (?,?,?,?)");
                    foreach ($d['child_name'] as $i => $cn) {
                        if (trim($cn)==='') continue;
                        $stmt->execute([$app['applicant_id'],$cn,$d['child_age'][$i]?:null,$d['child_gender'][$i]??'']);
                    }
                }
                if (!empty($_FILES['documents']['name'][0])) {
                    $upload = new Upload($pdo, $auth->id());
                    $upload->storeMultiple($_FILES['documents'], $id, $d['doc_description'] ?? '');
                }
                $logger->appLog($id, $auth->id(), 'edited', 'Application edited.');
                $logger->activity($auth->id(), 'edit_application', 'application', $id);
                $pdo->commit();
                flash('success', 'Application updated.'); redirect('index.php?page=applications.view&id='.$id);
            }
        }

        $pageTitle = 'Edit Project #' . $id; $activePage = 'applications';
        require __DIR__ . '/../views/applications/edit.php';
    }

    // ── View ──────────────────────────────────────────────────────────────────
    public static function view(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        $id  = (int)($_GET['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app || !$auth->canViewApplication($app)) {
            flash('error', 'Not found or access denied.'); redirect('index.php?page=applications');
        }
        $stmt = $pdo->prepare("SELECT * FROM applicants WHERE id=?"); $stmt->execute([$app['applicant_id']]); $applicant = $stmt->fetch();
        $stmtS = $pdo->prepare("SELECT * FROM applicant_spouse WHERE applicant_id=?"); $stmtS->execute([$app['applicant_id']]); $spouse = $stmtS->fetch();
        $stmtC = $pdo->prepare("SELECT * FROM applicant_children WHERE applicant_id=? ORDER BY id"); $stmtC->execute([$app['applicant_id']]); $children = $stmtC->fetchAll();
        $timeline    = $logger->getTimeline($id);
        $editHistory = $logger->getEditHistory($id);
        $upload      = new Upload($pdo, $auth->id());
        $documents   = $upload->getForApplication($id);
        $stmtD = $pdo->prepare("SELECT d.*,u.full_name AS auth_name FROM disbursements d LEFT JOIN users u ON u.id=d.authorized_by WHERE d.application_id=? ORDER BY d.installment_no");
        $stmtD->execute([$id]); $disbursements = $stmtD->fetchAll();

        $pageTitle = 'Project #' . $id; $activePage = 'applications';
        require __DIR__ . '/../views/applications/view.php';
    }

    // ── Pending Validation Queue ──────────────────────────────────────────────
    public static function pending(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_DATA_ENTRY, ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
        $villages = $auth->myVillages();
        $where = ["a.status = 'pending_validation'", "a.created_by != ?"];
        $params = [$auth->id()];
        if ($villages && !$auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            $ph = implode(',', array_fill(0, count($villages), '?'));
            $where[] = "ap.village_id IN ($ph)";
            $params  = array_merge($params, $villages);
        }
        $sql = "SELECT a.*, ap.full_name AS applicant_name, v.name AS village_name, fc.name AS category_name, u.full_name AS creator_name
                FROM applications a JOIN applicants ap ON ap.id=a.applicant_id JOIN villages v ON v.id=ap.village_id
                JOIN fund_categories fc ON fc.id=a.fund_category_id JOIN users u ON u.id=a.created_by
                WHERE " . implode(' AND ', $where) . " ORDER BY a.created_at ASC";
        $page   = max(1,(int)($_GET['p']??1));
        $result = paginate($pdo, $sql, $params, $page);
        $pageTitle = 'Pending Validation'; $activePage = 'pending';
        require __DIR__ . '/../views/applications/pending.php';
    }

    // ── Validate ─────────────────────────────────────────────────────────────
    public static function validateApp(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_DATA_ENTRY, ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
        $id  = (int)($_GET['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app || $app['status'] !== STATUS_PENDING_VALIDATION) {
            flash('error', 'Not available for validation.'); redirect('index.php?page=applications.pending');
        }
        if ($app['created_by'] == $auth->id()) {
            flash('error', 'You cannot validate your own application.'); redirect('index.php?page=applications.pending');
        }
        if (!$auth->canViewApplication($app)) {
            flash('error', 'Access denied.'); redirect('index.php?page=applications.pending');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $decision = $_POST['decision'] ?? '';
            $comment  = trim($_POST['comment'] ?? '');
            if ($decision === 'approve') {
                $pdo->prepare("UPDATE applications SET status=?,is_valid=1,validated_by=?,validated_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                    ->execute([STATUS_SUBMITTED, $auth->id(), $id]);
                $logger->appLog($id, $auth->id(), 'validated', $comment);
                $logger->activity($auth->id(), 'validate_application', 'application', $id);
                flash('success', 'Application validated and submitted to workflow.');
            } else {
                $pdo->prepare("UPDATE applications SET status='rejected',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
                $logger->appLog($id, $auth->id(), 'validation_rejected', $comment);
                flash('warning', 'Application rejected at validation stage.');
            }
            redirect('index.php?page=applications.pending');
        }
        $stmt = $pdo->prepare("SELECT * FROM applicants WHERE id=?"); $stmt->execute([$app['applicant_id']]); $applicant = $stmt->fetch();
        $pageTitle = 'Validate Project #'.$id; $activePage = 'pending';
        require __DIR__ . '/../views/applications/validate.php';
    }

    // ── Advisory Comment ──────────────────────────────────────────────────────
    public static function comment(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_VERIFICATION]);
        csrf_verify();
        $bulk     = !empty($_POST['bulk']);
        $comment  = trim($_POST['comment'] ?? '');
        if (!$comment) { flash('error','Comment cannot be empty.'); redirect('index.php?page=applications'); }

        if ($bulk) {
            $villageId = (int)($_POST['village_id'] ?? 0);
            if (!$auth->isInVillage($villageId)) { flash('error','Access denied.'); redirect('index.php?page=applications'); }
            $stmt = $pdo->prepare("SELECT a.id FROM applications a JOIN applicants ap ON ap.id=a.applicant_id WHERE ap.village_id=? AND a.status NOT IN ('draft','pending_validation','rejected')");
            $stmt->execute([$villageId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $appId) {
                $logger->appLog((int)$appId, $auth->id(), 'advisory_comment', $comment);
            }
            $logger->activity($auth->id(), 'bulk_comment', 'village', $villageId);
            flash('success', 'Bulk comment added to all village applications.');
        } else {
            $id  = (int)($_POST['application_id'] ?? 0);
            $app = self::_loadApp($pdo, $id);
            if (!$app || !$auth->canViewApplication($app)) { flash('error','Access denied.'); redirect('index.php?page=applications'); }
            $logger->appLog($id, $auth->id(), 'advisory_comment', $comment);
            $logger->activity($auth->id(), 'comment_application', 'application', $id);
            flash('success', 'Comment added.');
        }
        redirect('index.php?page=applications');
    }

    // ── Review (1.b) ─────────────────────────────────────────────────────────
    public static function review(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_VILLAGE_INCHARGE);
        csrf_verify();
        $id  = (int)($_POST['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app || $app['status'] !== STATUS_SUBMITTED || !$auth->isInVillage((int)$app['village_id'])) {
            flash('error','Not available for review.'); redirect('index.php?page=applications.view&id='.$id);
        }
        $comment  = trim($_POST['comment'] ?? '');
        $decision = $_POST['decision'] ?? '';
        if ($decision === 'approve') {
            $pdo->prepare("UPDATE applications SET status=?,reviewed_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                ->execute([STATUS_UNDER_REVIEW, $auth->id(), $id]);
            $logger->appLog($id, $auth->id(), 'village_reviewed', $comment);
            flash('success','Application forwarded for final approval.');
        } else {
            $pdo->prepare("UPDATE applications SET status='rejected',reviewed_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$auth->id(),$id]);
            $logger->appLog($id, $auth->id(), 'rejected', $comment);
            flash('warning','Application rejected.');
        }
        $logger->activity($auth->id(),'review_application','application',$id);
        redirect('index.php?page=applications.view&id='.$id);
    }

    // ── Approve (1.c) ────────────────────────────────────────────────────────
    public static function approve(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_OVERALL_INCHARGE);
        csrf_verify();
        $id  = (int)($_POST['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app || $app['status'] !== STATUS_UNDER_REVIEW) {
            flash('error','Not available for approval.'); redirect('index.php?page=applications.view&id='.$id);
        }
        $comment = trim($_POST['comment'] ?? '');
        $pdo->prepare("UPDATE applications SET status='approved',approved_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$auth->id(),$id]);
        $logger->appLog($id,$auth->id(),'approved',$comment);
        $logger->activity($auth->id(),'approve_application','application',$id);
        flash('success','Application approved. Set up disbursement schedule.');
        redirect('index.php?page=disbursements.schedule&app_id='.$id);
    }

    // ── Reject (1.b or 1.c) ───────────────────────────────────────────────────
    public static function reject(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE]);
        csrf_verify();
        $id  = (int)($_POST['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app || !$auth->canViewApplication($app)) {
            flash('error','Access denied.'); redirect('index.php?page=applications');
        }
        $comment = trim($_POST['comment'] ?? '');
        if (!$comment) { flash('error','A comment is required when rejecting.'); redirect('index.php?page=applications.view&id='.$id); }
        $pdo->prepare("UPDATE applications SET status='rejected',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
        $logger->appLog($id,$auth->id(),'rejected',$comment);
        $logger->activity($auth->id(),'reject_application','application',$id);
        flash('warning','Application rejected.');
        redirect('index.php?page=applications.view&id='.$id);
    }

    // ── Privileged Toggle (1.c) ───────────────────────────────────────────────
    public static function setPrivileged(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
        csrf_verify();
        $id  = (int)($_POST['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app) { flash('error','Not found.'); redirect('index.php?page=applications'); }
        $newVal = $app['is_privileged'] ? 0 : 1;
        $pdo->prepare("UPDATE applications SET is_privileged=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$newVal,$id]);
        $logger->appLog($id,$auth->id(),'privilege_toggled','Privileged flag set to '.$newVal);
        $logger->activity($auth->id(),'set_privileged','application',$id);
        flash('success', $newVal ? 'Marked as privileged.' : 'Removed privileged flag.');
        redirect('index.php?page=applications.view&id='.$id);
    }

    // ── Upload Documents ──────────────────────────────────────────────────────
    public static function uploadDoc(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        csrf_verify();
        $id  = (int)($_POST['application_id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app || !$auth->canViewApplication($app)) { flash('error','Access denied.'); redirect('index.php?page=applications'); }
        if (empty($_FILES['documents']['name'][0])) { flash('error','No files selected.'); redirect('index.php?page=applications.view&id='.$id); }
        $upload = new Upload($pdo, $auth->id());
        $result = $upload->storeMultiple($_FILES['documents'], $id, $_POST['doc_description'] ?? '');
        if ($result['stored']) {
            $logger->appLog($id,$auth->id(),'documents_uploaded',$result['stored'].' file(s) uploaded.');
            $logger->activity($auth->id(),'upload_document','application',$id);
            flash('success',$result['stored'].' document(s) uploaded.');
        }
        if ($result['errors']) flash('warning', implode(' ', $result['errors']));
        redirect('index.php?page=applications.view&id='.$id);
    }

    // ── Delete Document ───────────────────────────────────────────────────────
    public static function deleteDoc(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        csrf_verify();
        $docId = (int)($_POST['doc_id'] ?? 0);
        $upload = new Upload($pdo, $auth->id());
        $doc    = $upload->getById($docId);
        if (!$doc) { flash('error','Not found.'); redirect('index.php?page=applications'); }
        // Only uploader or sysadmin can delete
        if ($doc['uploaded_by'] != $auth->id() && !$auth->hasRole(ROLE_SYSADMIN)) {
            flash('error','Cannot delete this document.'); redirect('index.php?page=applications.view&id='.$doc['application_id']);
        }
        $upload->delete($docId);
        $logger->appLog((int)$doc['application_id'],$auth->id(),'document_deleted','Doc #'.$docId.' deleted.');
        flash('success','Document deleted.');
        redirect('index.php?page=applications.view&id='.$doc['application_id']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private static function _loadApp(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare("
            SELECT a.*, ap.village_id
            FROM applications a
            JOIN applicants ap ON ap.id = a.applicant_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private static function _allowedVillages(PDO $pdo, Auth $auth): array
    {
        if ($auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            return $pdo->query("SELECT * FROM villages WHERE is_active=1 ORDER BY name")->fetchAll();
        }
        $ids = $auth->myVillages();
        if (!$ids) return [];
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM villages WHERE id IN ($ph) AND is_active=1 ORDER BY name");
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }
}
