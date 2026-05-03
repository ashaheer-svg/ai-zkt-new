<?php
/**
 * Application (Project) Controller
 * 
 * Manages the core lifecycle of Zakath applications, including data entry,
 * validation (1.b), review (1.c), approval, and document management.
 * Implements strict RBAC and geographic scoping for all actions.
 */
class ApplicationController
{
    /**
     * Lists applications based on user role and geographic permissions.
     * Handles search, status filtering, and pagination.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service for RBAC and scoping.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function list(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        $role     = $auth->role();
        $villages = $auth->myVillages();
        // 1.c and Sysadmins can toggle viewing of Drafts/Pending-Validation items
        $showAll  = ($auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]) && isset($_GET['show_all']));

        $where  = ['1=1'];
        $params = [];

        // Visibility restriction: Only high-level roles see "privileged" cases
        if (!$auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            $where[] = 'a.is_privileged = 0';
        }

        // Scope data based on Role
        if ($role === ROLE_DATA_ENTRY) {
            // Creators only see their own entries
            $where[] = "a.created_by = ?";
            $params[] = $auth->id();
        } elseif ($role === ROLE_VILLAGE_INCHARGE || $role === ROLE_VERIFICATION) {
            // Village-level users only see items beyond initial entry phase
            $where[] = "a.status NOT IN ('draft','pending_validation')";
            if ($villages) {
                $ph = implode(',', array_fill(0, count($villages), '?'));
                $where[] = "ap.village_id IN ($ph)";
                $params  = array_merge($params, $villages);
            }
        } elseif ($auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            // Overall management sees everything unless restricted by toggle
            if (!$showAll) {
                $where[] = "a.status NOT IN ('draft','pending_validation')";
            }
        }

        // Search logic (Name, ID, Phone)
        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $where[]  = "(ap.full_name LIKE ? OR ap.id_number LIKE ? OR ap.telephone LIKE ?)";
            $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
        }

        // Filter by Status
        $filterStatus = $_GET['status'] ?? '';
        if ($filterStatus) { $where[] = 'a.status = ?'; $params[] = $filterStatus; }

        $sql = "SELECT a.*, ap.full_name AS applicant_name, ap.id_number,
                       ap.village_id, v.name AS village_name, v.district as village_district, fc.name AS category_name,
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

    /**
     * Handles the creation of new applications and initial applicant registration.
     * Supports "Save as Draft" (partial data) and "Submit" (full validation).
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function create(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_DATA_ENTRY, ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);

        $villages   = self::_allowedVillages($pdo, $auth);
        $categories = $pdo->query("SELECT * FROM fund_categories WHERE is_active=1 ORDER BY name")->fetchAll();
        $errors     = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $d = $_POST;
            $isDraft = isset($d['save_draft']);

            // Validation logic changes based on whether it's a Draft or a Final Submission
            if (empty($d['full_name']))       $errors[] = 'Full name is required.';
            if (empty($d['village_id']))      $errors[] = 'Village is required.';
            
            if (!$isDraft) {
                if (empty($d['fund_category_id'])) $errors[] = 'Fund category is required.';
                if (empty($d['amount_requested'])) $errors[] = 'Amount requested is required.';
                if (empty($d['gender']))           $errors[] = 'Gender is required.';
            }

            if (!$errors) {
                $pdo->beginTransaction();
                
                // For drafts, fill mandatory DB columns with placeholders if missing
                if ($isDraft) {
                    if (empty($d['fund_category_id'])) {
                        $d['fund_category_id'] = $pdo->query("SELECT id FROM fund_categories WHERE is_active=1 LIMIT 1")->fetchColumn() ?: 0;
                    }
                    if (empty($d['amount_requested'])) {
                        $d['amount_requested'] = 0;
                    }
                }

                // 1. Insert Applicant Profile
                $stmt = $pdo->prepare("INSERT INTO applicants
                    (full_name,address,gender,age,id_number,telephone,telephone_home,village_id,marital_status,residency_status,occupation,employer_details,notes)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $d['full_name'], $d['address'] ?? '', $d['gender'],
                    $d['age'] ?: null, $d['id_number'] ?? '', $d['telephone'] ?? '', $d['telephone_home'] ?? '',
                    $d['village_id'], $d['marital_status'] ?? null, $d['residency_status'] ?? null,
                    $d['occupation'] ?? '', $d['employer_details'] ?? '', $d['notes'] ?? ''
                ]);
                $applicantId = (int)$pdo->lastInsertId();

                // 2. Insert Dependants (Spouses, children, siblings)
                if (!empty($d['dep_name'])) {
                    $stmt = $pdo->prepare("INSERT INTO applicant_dependants (applicant_id,full_name,age,gender,relationship,occupation,income) VALUES (?,?,?,?,?,?,?)");
                    foreach ($d['dep_name'] as $i => $dn) {
                        if (trim($dn) === '') continue;
                        $rel = $d['dep_rel'][$i] ?? 'other';
                        if ($rel === 'other' && !empty($d['dep_rel_other'][$i])) $rel = $d['dep_rel_other'][$i];
                        $stmt->execute([
                            $applicantId, $dn, $d['dep_age'][$i] ?: null, $d['dep_gender'][$i] ?? '', $rel,
                            $d['dep_occ'][$i] ?? '', (float)($d['dep_inc'][$i] ?? 0)
                        ]);
                    }
                }

                // 3. Determine Initial Application Status
                if ($isDraft) {
                    $status = STATUS_DRAFT;
                    $isValid = 0;
                } else {
                    $isHigherRole = $auth->hasRole([ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
                    // 1.a submissions require 1.b validation. Higher roles auto-validate their own entries.
                    $status  = $isHigherRole ? STATUS_SUBMITTED  : STATUS_PENDING_VALIDATION;
                    $isValid = $isHigherRole ? 1 : 0;
                }

                // 4. Create Application record
                $stmt = $pdo->prepare("INSERT INTO applications
                    (applicant_id,fund_category_id,amount_requested,status,is_valid,created_by,
                     requested_type,requested_installment,requested_count,
                     reason_for_application,applied_other_funds,expected_date)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $applicantId, $d['fund_category_id'], $d['amount_requested'], $status, $isValid, $auth->id(),
                    $d['requested_type'] ?? null, $d['requested_installment'] ?? null, $d['requested_count'] ?? null,
                    $d['reason_for_application'] ?? '', $d['applied_other_funds'] ?? '', $d['expected_date'] ?: null
                ]);
                $appId = (int)$pdo->lastInsertId();

                // 5. Handle initial document uploads
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

        // Rebuild dependants list from POST data if validation failed (prevents re-typing)
        $dependants = [];
        if (!empty($_POST['dep_name'])) {
            foreach ($_POST['dep_name'] as $i => $name) {
                if (trim($name) === '') continue;
                $dependants[] = [
                    'full_name' => $name,
                    'age' => $_POST['dep_age'][$i] ?? null,
                    'gender' => $_POST['dep_gender'][$i] ?? '',
                    'relationship' => $_POST['dep_rel'][$i] === 'other' ? ($_POST['dep_rel_other'][$i] ?? 'other') : ($_POST['dep_rel'][$i] ?? 'other'),
                    'occupation' => $_POST['dep_occ'][$i] ?? '',
                    'income' => (float)($_POST['dep_inc'][$i] ?? 0)
                ];
            }
        }

        $pageTitle  = 'New Project';
        $activePage = 'applications';
        require __DIR__ . '/../views/applications/create.php';
    }

    /**
     * Updates an existing application or applicant profile.
     * Maintains a detailed audit trail of all field-level changes.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function edit(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        $id  = (int)($_GET['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app || !$auth->canEditApplication($app)) {
            flash('error', 'Not found or not editable.'); redirect('index.php?page=applications');
        }

        // Load existing related data
        $stmt       = $pdo->prepare("SELECT * FROM applicants WHERE id=?"); $stmt->execute([$app['applicant_id']]); $applicant = $stmt->fetch();
        $stmtS      = $pdo->prepare("SELECT * FROM applicant_spouse WHERE applicant_id=?"); $stmtS->execute([$app['applicant_id']]); $spouse = $stmtS->fetch();
        $stmtC      = $pdo->prepare("SELECT * FROM applicant_dependants WHERE applicant_id=? ORDER BY id"); $stmtC->execute([$app['applicant_id']]); $dependants = $stmtC->fetchAll();
        $villages   = self::_allowedVillages($pdo, $auth);
        $categories = $pdo->query("SELECT * FROM fund_categories WHERE is_active=1 ORDER BY name")->fetchAll();
        $errors     = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $d = $_POST;
            $isDraft = isset($d['save_draft']);

            // Village ID fix: If disabled in UI (edit mode restriction), use existing value
            if (!isset($d['village_id'])) {
                $d['village_id'] = $app['village_id'];
            }

            if (empty($d['full_name']))   $errors[] = 'Full name required.';
            if (empty($d['village_id']))  $errors[] = 'Village is required.';
            
            if (!$isDraft) {
                if (empty($d['amount_requested'])) $errors[] = 'Amount required.';
                if (empty($d['fund_category_id'])) $errors[] = 'Category required.';
            }

            if (!$errors) {
                $pdo->beginTransaction();
                
                // Drafting logic for mandatory fields
                if ($isDraft) {
                    if (empty($d['fund_category_id'])) {
                        $d['fund_category_id'] = $pdo->query("SELECT id FROM fund_categories WHERE is_active=1 LIMIT 1")->fetchColumn() ?: 0;
                    }
                    if (empty($d['amount_requested'])) {
                        $d['amount_requested'] = 0;
                    }
                }
                
                // Status progression
                $status  = $app['status'];
                $isValid = (int)$app['is_valid'];

                if ($isDraft) {
                    $status = STATUS_DRAFT;
                    $isValid = 0;
                } elseif ($status === STATUS_DRAFT) {
                    // Transitioning from a saved Draft to a real submission
                    $isHigherRole = $auth->hasRole([ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
                    $status  = $isHigherRole ? STATUS_SUBMITTED  : STATUS_PENDING_VALIDATION;
                    $isValid = $isHigherRole ? 1 : 0;
                }

                // 1. Audit Logging (Applicants table)
                $fields = [
                    'full_name','address','gender','age','id_number','telephone','telephone_home',
                    'marital_status','residency_status','occupation','employer_details','notes'
                ];
                foreach ($fields as $f) {
                    if (($applicant[$f] ?? '') !== ($d[$f] ?? '')) {
                        $logger->editLog($id, $auth->id(), $f, $applicant[$f]??'', $d[$f] ?? '');
                    }
                }
                
                // 2. Audit Logging (Applications table)
                $appFields = ['fund_category_id','amount_requested','requested_type','requested_installment','requested_count','reason_for_application','applied_other_funds','expected_date'];
                foreach ($appFields as $f) {
                    if (($app[$f] ?? '') != ($d[$f] ?? '')) {
                        $logger->editLog($id, $auth->id(), $f, $app[$f]??'', $d[$f] ?? '');
                    }
                }

                // 3. Update DB Records
                $pdo->prepare("UPDATE applicants SET 
                    full_name=?, address=?, gender=?, age=?, id_number=?, telephone=?, telephone_home=?, 
                    marital_status=?, residency_status=?, occupation=?, employer_details=?, notes=? 
                    WHERE id=?")
                    ->execute([
                        $d['full_name'],$d['address']??'',$d['gender'],$d['age']?:null,$d['id_number']??'',$d['telephone']??'',$d['telephone_home']??'',
                        $d['marital_status']??null,$d['residency_status']??null,$d['occupation']??'',$d['employer_details']??'',$d['notes']??'',$app['applicant_id']
                    ]);
                
                $pdo->prepare("UPDATE applications SET 
                    fund_category_id=?, amount_requested=?, status=?, is_valid=?, requested_type=?, requested_installment=?, requested_count=?, 
                    reason_for_application=?, applied_other_funds=?, expected_date=?, updated_at=CURRENT_TIMESTAMP 
                    WHERE id=?")
                    ->execute([
                        $d['fund_category_id'],$d['amount_requested'],$status,$isValid,$d['requested_type']??null,$d['requested_installment']??null,$d['requested_count']??null,
                        $d['reason_for_application']??'',$d['applied_other_funds']??'',$d['expected_date']?:null,$id
                    ]);

                // 4. Refresh Dependants (Wipe and re-insert for simplicity)
                $pdo->prepare("DELETE FROM applicant_dependants WHERE applicant_id=?")->execute([$app['applicant_id']]);
                if (!empty($d['dep_name'])) {
                    $stmt = $pdo->prepare("INSERT INTO applicant_dependants (applicant_id,full_name,age,gender,relationship,occupation,income) VALUES (?,?,?,?,?,?,?)");
                    foreach ($d['dep_name'] as $i => $dn) {
                        if (trim($dn)==='') continue;
                        $rel = $d['dep_rel'][$i] ?? 'other';
                        if ($rel === 'other' && !empty($d['dep_rel_other'][$i])) $rel = $d['dep_rel_other'][$i];
                        $stmt->execute([
                            $app['applicant_id'],$dn,$d['dep_age'][$i]?:null,$d['dep_gender'][$i]??'',$rel,
                            $d['dep_occ'][$i] ?? '', (float)($d['dep_inc'][$i] ?? 0)
                        ]);
                    }
                }

                // 5. Handle additional document uploads
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

        // Error recovery: rebuild dependants from POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors) {
            $dependants = [];
            if (!empty($_POST['dep_name'])) {
                foreach ($_POST['dep_name'] as $i => $name) {
                    if (trim($name) === '') continue;
                    $dependants[] = [
                        'full_name' => $name,
                        'age' => $_POST['dep_age'][$i] ?? null,
                        'gender' => $_POST['dep_gender'][$i] ?? '',
                        'relationship' => $_POST['dep_rel'][$i] === 'other' ? ($_POST['dep_rel_other'][$i] ?? 'other') : ($_POST['dep_rel'][$i] ?? 'other'),
                        'occupation' => $_POST['dep_occ'][$i] ?? '',
                        'income' => (float)($_POST['dep_inc'][$i] ?? 0)
                    ];
                }
            }
        }

        $appId = $id;
        $pageTitle = 'Edit Project #' . $id;
        $activePage = 'applications';
        require __DIR__ . '/../views/applications/edit.php';
    }

    /**
     * Displays a comprehensive view of a single application.
     * Includes applicant details, dependants, documents, timeline, and edit history.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function view(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        $id  = (int)($_GET['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app || !$auth->canViewApplication($app)) {
            flash('error', 'Not found or access denied.'); redirect('index.php?page=applications');
        }
        
        // Load Profile
        $stmt = $pdo->prepare("SELECT * FROM applicants WHERE id=?"); $stmt->execute([$app['applicant_id']]); $applicant = $stmt->fetch();
        $stmtS = $pdo->prepare("SELECT * FROM applicant_spouse WHERE applicant_id=?"); $stmtS->execute([$app['applicant_id']]); $spouse = $stmtS->fetch();
        $stmtC = $pdo->prepare("SELECT * FROM applicant_dependants WHERE applicant_id=? ORDER BY id"); $stmtC->execute([$app['applicant_id']]); $dependants = $stmtC->fetchAll();
        
        // Load Audits & Assets
        $timeline    = $logger->getTimeline($id);
        $editHistory = $logger->getEditHistory($id);
        $upload      = new Upload($pdo, $auth->id());
        $documents   = $upload->getForApplication($id);
        
        // Load Financials
        $stmtD = $pdo->prepare("SELECT d.*,u.full_name AS auth_name FROM disbursements d LEFT JOIN users u ON u.id=d.authorized_by WHERE d.application_id=? ORDER BY d.installment_no");
        $stmtD->execute([$id]); $disbursements = $stmtD->fetchAll();

        $pageTitle = 'Project #' . $id; $activePage = 'applications';
        require __DIR__ . '/../views/applications/view.php';
    }

    /**
     * Lists applications awaiting 1.b validation.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function pending(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_DATA_ENTRY, ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
        $villages = $auth->myVillages();
        
        // Scope: Status must be "pending_validation" and user cannot validate their own entry (unless 1.b+)
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

    /**
     * Processes a "Validation" (Peer Review) by a 1.b user.
     * Confirms that the application data is accurate and ready for management review.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function validateApp(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
        $id  = (int)($_GET['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        
        if (!$app || $app['status'] !== STATUS_PENDING_VALIDATION) {
            flash('error', 'Not available for validation.'); redirect('index.php?page=applications.pending');
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

    /**
     * Pushes a validated application back to the unvalidated state.
     * Usually used by 1.c if data quality issues are found.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function revert(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
        csrf_verify();
        $id  = (int)($_POST['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app) { flash('error','Not found.'); redirect('index.php?page=applications'); }
        
        $comment = trim($_POST['comment'] ?? 'Pushed back to unvalidated status.');
        $pdo->prepare("UPDATE applications SET status=?, is_valid=0, updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([STATUS_PENDING_VALIDATION, $id]);
        
        $logger->appLog($id, $auth->id(), 'pushed_back', $comment);
        $logger->activity($auth->id(), 'revert_application', 'application', $id);
        flash('warning', 'Application pushed back to unvalidated status.');
        redirect('index.php?page=applications.view&id='.$id);
    }

    /**
     * Marks an application as "Privileged".
     * Privileged applications are only visible to management roles (1.c and Sysadmin).
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function hold(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
        csrf_verify();
        $id  = (int)($_POST['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app) { flash('error','Not found.'); redirect('index.php?page=applications'); }
        
        // Only validated/submitted projects can be put on hold
        if (!$app['is_valid']) {
            flash('error', 'Only validated projects can be put on hold.'); redirect('index.php?page=applications.view&id='.$id);
        }

        $comment = trim($_POST['comment'] ?? '');
        if (!$comment) { flash('error', 'Comment is required to put on hold.'); redirect('index.php?page=applications.view&id='.$id); }

        // Store current status in previous_status for later restoration
        $pdo->prepare("UPDATE applications SET previous_status=status, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([STATUS_ON_HOLD, $id]);
        
        $logger->appLog($id, $auth->id(), 'on_hold', $comment);
        $logger->activity($auth->id(), 'hold_application', 'application', $id);
        flash('warning', 'Application is now ON HOLD.');
        redirect('index.php?page=applications.view&id='.$id);
    }

    /**
     * Permanently deletes an application and all its related records.
     * (Applicant profile, dependants, documents, logs, and disbursements).
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function unhold(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
        csrf_verify();
        $id  = (int)($_POST['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app || $app['status'] !== STATUS_ON_HOLD) { flash('error','Not on hold.'); redirect('index.php?page=applications'); }
        
        $comment = trim($_POST['comment'] ?? '');
        if (!$comment) { flash('error', 'Comment is required to remove hold.'); redirect('index.php?page=applications.view&id='.$id); }

        $revertStatus = $app['previous_status'] ?: STATUS_SUBMITTED;
        $pdo->prepare("UPDATE applications SET status=?, previous_status=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$revertStatus, $id]);
        
        $logger->appLog($id, $auth->id(), 'hold_cancelled', $comment);
        $logger->activity($auth->id(), 'unhold_application', 'application', $id);
        flash('success', 'Hold removed. Status restored to '.STATUS_LABELS[$revertStatus]);
        redirect('index.php?page=applications.view&id='.$id);
    }

    /**
     * Adds an internal/advisory comment to an application or village scope.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function comment(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        csrf_verify();
        $bulk     = !empty($_POST['bulk']);
        $comment  = trim($_POST['comment'] ?? '');
        if (!$comment) { flash('error','Comment cannot be empty.'); redirect('index.php?page=applications'); }

        if ($bulk) {
            // Bulk comments are restricted to management
            $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN, ROLE_VERIFICATION]);
            $villageId = (int)($_POST['village_id'] ?? 0);
            if (!$auth->isInVillage($villageId)) { flash('error','Access denied.'); redirect('index.php?page=applications'); }
            
            $stmt = $pdo->prepare("SELECT a.id FROM applications a JOIN applicants ap ON ap.id=a.applicant_id WHERE ap.village_id=? AND a.status NOT IN ('draft','pending_validation','rejected')");
            $stmt->execute([$villageId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $appId) {
                $logger->appLog((int)$appId, $auth->id(), 'comment', $comment);
            }
            $logger->activity($auth->id(), 'bulk_comment', 'village', $villageId);
            flash('success', 'Bulk comment added.');
        } else {
            // Single application comment
            $id  = (int)($_POST['application_id'] ?? 0);
            $app = self::_loadApp($pdo, $id);
            if (!$app || !$auth->canViewApplication($app)) { flash('error','Access denied.'); redirect('index.php?page=applications'); }
            
            // Commenting Permission logic
            $isCreator   = ($app['created_by'] == $auth->id());
            $isValidator = ($app['validated_by'] == $auth->id());
            $isOverall   = $auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);

            if (!$isCreator && !$isValidator && !$isOverall && !$auth->hasRole(ROLE_VERIFICATION)) {
                flash('error', 'You do not have permission to comment on this application.');
                redirect('index.php?page=applications.view&id='.$id);
            }

            $logger->appLog($id, $auth->id(), 'comment', $comment);
            $logger->activity($auth->id(), 'comment_application', 'application', $id);
            flash('success', 'Comment added.');
            redirect('index.php?page=applications.view&id='.$id);
        }
        redirect('index.php?page=applications');
    }

    /**
     * Performs the 1.b level review.
     * Transitions from "Submitted" to "Under Review".
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
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

    /**
     * Final Approval (1.c).
     * Transitions to "Disbursing" and automatically generates the payment schedule.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function approve(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_OVERALL_INCHARGE);
        csrf_verify();
        $id  = (int)($_POST['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app || !in_array($app['status'], [STATUS_SUBMITTED, STATUS_UNDER_REVIEW])) {
            flash('error','Not available for approval.'); redirect('index.php?page=applications.view&id='.$id);
        }
        
        $comment = trim($_POST['comment'] ?? '');
        $type    = $_POST['disbursement_type'] ?? DISB_ONE_TIME;
        $amount  = (float)($_POST['disbursement_amount'] ?? 0);
        $count   = (int)($_POST['disbursement_count'] ?? 1);
        $start   = $_POST['disbursement_start_date'] ?? date('Y-m-d');

        if ($amount <= 0 || $count <= 0) {
            flash('error', 'Invalid disbursement guidelines.'); redirect('index.php?page=applications.view&id='.$id);
        }

        $pdo->beginTransaction();
        
        // 1. Update status and store approved guidelines
        $pdo->prepare("UPDATE applications SET status='disbursing', approved_by=?, updated_at=CURRENT_TIMESTAMP, 
                        disbursement_type=?, disbursement_amount=?, disbursement_count=?, disbursement_start_date=? 
                        WHERE id=?")
            ->execute([$auth->id(), $type, $amount, $count, $start, $id]);

        // 2. Generate Schedule
        // Wipes any existing schedule (shouldn't be any at this stage, but for safety)
        $pdo->prepare("DELETE FROM disbursements WHERE application_id=?")->execute([$id]);
        $stmt = $pdo->prepare("INSERT INTO disbursements (application_id,installment_no,due_date,amount) VALUES (?,?,?,?)");
        $dt   = new DateTime($start);
        for ($i = 1; $i <= $count; $i++) {
            $stmt->execute([$id, $i, $dt->format('Y-m-d'), $amount]);
            if ($type === DISB_ONE_TIME) break;
            match ($type) {
                DISB_WEEKLY       => $dt->modify('+1 week'),
                DISB_MONTHLY      => $dt->modify('+1 month'),
                DISB_QUARTERLY    => $dt->modify('+3 months'),
                DISB_HALF_YEARLY  => $dt->modify('+6 months'),
                DISB_YEARLY       => $dt->modify('+1 year'),
            };
        }

        $logger->appLog($id,$auth->id(),'approved',$comment . " | Guidelines: $type, $amount x $count starting $start");
        $logger->activity($auth->id(),'approve_application','application',$id);
        $pdo->commit();
        
        flash('success','Application approved and disbursement schedule generated.');
        redirect('index.php?page=applications.view&id='.$id);
    }

    /**
     * Deletes a document attachment.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
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

    /**
     * Toggles the "Privileged" visibility flag on an application.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
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

    /**
     * Handles document uploads for an existing application.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function uploadDoc(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        csrf_verify();
        $id  = (int)($_POST['application_id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app || !$auth->canViewApplication($app)) { flash('error','Access denied.'); redirect('index.php?page=applications'); }
        
        // Permitted to upload: Creator, Validator, or Overall In-charge
        $isCreator   = ($app['created_by'] == $auth->id());
        $isValidator = ($app['validated_by'] == $auth->id());
        $isOverall   = $auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);

        if (!$isCreator && !$isValidator && !$isOverall) {
            flash('error', 'You do not have permission to upload documents to this application.');
            redirect('index.php?page=applications.view&id='.$id);
        }

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

    /**
     * Deletes a document. Only uploader or Sysadmin can delete.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function deleteDoc(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireLogin();
        csrf_verify();
        $docId = (int)($_POST['doc_id'] ?? 0);
        $upload = new Upload($pdo, $auth->id());
        $doc    = $upload->getById($docId);
        if (!$doc) { flash('error','Not found.'); redirect('index.php?page=applications'); }
        
        if ($doc['uploaded_by'] != $auth->id() && !$auth->hasRole(ROLE_SYSADMIN)) {
            flash('error','Cannot delete this document.'); redirect('index.php?page=applications.view&id='.$doc['application_id']);
        }
        
        $upload->delete($docId);
        $logger->appLog((int)$doc['application_id'],$auth->id(),'document_deleted','Doc #'.$docId.' deleted.');
        flash('success','Document deleted.');
        redirect('index.php?page=applications.view&id='.$doc['application_id']);
    }

    /**
     * Re-calculates and replaces the remaining disbursement schedule.
     * Preserves "Released" payments and modifies everything else.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function adjustSchedule(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
        csrf_verify();
        $id  = (int)($_POST['id'] ?? 0);
        $app = self::_loadApp($pdo, $id);
        if (!$app || $app['status'] !== STATUS_DISBURSING) {
            flash('error', 'Only active (disbursing) projects can be re-scheduled.');
            redirect('index.php?page=applications.view&id='.$id);
        }

        $comment = trim($_POST['comment'] ?? '');
        if (!$comment) {
            flash('error', 'Reason for adjustment is required.');
            redirect('index.php?page=applications.view&id='.$id);
        }

        $type    = $_POST['disbursement_type'] ?? $app['disbursement_type'];
        $amount  = (float)($_POST['disbursement_amount'] ?? 0);
        $count   = (int)($_POST['disbursement_count'] ?? 1);
        $start   = $_POST['disbursement_start_date'] ?? date('Y-m-d');

        if ($amount <= 0 || $count <= 0) {
            flash('error', 'Invalid adjustment guidelines.');
            redirect('index.php?page=applications.view&id='.$id);
        }

        $pdo->beginTransaction();

        // 1. Preserve released payments
        $stmtReleased = $pdo->prepare("SELECT COUNT(*) FROM disbursements WHERE application_id=? AND status='released'");
        $stmtReleased->execute([$id]);
        $releasedCount = (int)$stmtReleased->fetchColumn();

        // 2. Wipe everything that hasn't been paid out yet
        $pdo->prepare("DELETE FROM disbursements WHERE application_id=? AND status IN ('pending', 'authorized', 'cancelled')")->execute([$id]);

        // 3. Update application totals
        $pdo->prepare("UPDATE applications SET disbursement_type=?, disbursement_amount=?, disbursement_count=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$type, $amount, $releasedCount + $count, $id]);

        // 4. Regenerate future installments
        $stmt = $pdo->prepare("INSERT INTO disbursements (application_id,installment_no,due_date,amount) VALUES (?,?,?,?)");
        $dt   = new DateTime($start);
        for ($i = 1; $i <= $count; $i++) {
            $instNo = $releasedCount + $i;
            $stmt->execute([$id, $instNo, $dt->format('Y-m-d'), $amount]);
            if ($type === DISB_ONE_TIME) break;
            match ($type) {
                DISB_WEEKLY       => $dt->modify('+1 week'),
                DISB_MONTHLY      => $dt->modify('+1 month'),
                DISB_QUARTERLY    => $dt->modify('+3 months'),
                DISB_HALF_YEARLY  => $dt->modify('+6 months'),
                DISB_YEARLY       => $dt->modify('+1 year'),
            };
        }

        $logger->appLog($id, $auth->id(), 'schedule_adjusted', $comment . " | New Guideline: $type, $amount x $count (remaining) starting $start");
        $logger->activity($auth->id(), 'adjust_application_schedule', 'application', $id);
        $pdo->commit();

        flash('success', 'Disbursement schedule has been adjusted. ' . $releasedCount . ' past payments were preserved.');
        redirect('index.php?page=applications.view&id='.$id);
    }

    /**
     * Internal helper to fetch a complete application dataset for rendering or logic.
     * 
     * @param PDO $pdo The database connection instance.
     * @param int $id The application ID.
     * @return array|null The application data array or null if not found.
     */
    private static function _loadApp(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare("
            SELECT a.*, ap.village_id, v.name as village_name, v.district as village_district
            FROM applications a
            JOIN applicants ap ON ap.id = a.applicant_id
            JOIN villages v ON v.id = ap.village_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Internal helper to determine which villages the current user can manage.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @return array List of villages (id and name).
     */
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
