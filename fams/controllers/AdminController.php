<?php
class AdminController
{
    // ── Users ─────────────────────────────────────────────────────────────────
    public static function users(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_SYSADMIN);
        $action = $_GET['action'] ?? '';
        $allVillages = $pdo->query("SELECT * FROM villages WHERE is_active=1 ORDER BY name")->fetchAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $d = $_POST;

            if ($action === 'create') {
                if (empty($d['username']) || empty($d['password']) || empty($d['full_name']) || empty($d['role'])) {
                    flash('error','All fields are required.'); redirect('index.php?page=admin.users');
                }
                $hash = password_hash($d['password'], PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username,password_hash,full_name,role) VALUES (?,?,?,?)");
                    $stmt->execute([$d['username'],$hash,$d['full_name'],$d['role']]);
                    $uid = (int)$pdo->lastInsertId();
                    // Village assignments
                    if (!empty($d['villages'])) {
                        $sv = $pdo->prepare("INSERT OR IGNORE INTO user_villages (user_id,village_id) VALUES (?,?)");
                        foreach ((array)$d['villages'] as $vid) { $sv->execute([$uid,(int)$vid]); }
                    }
                    $logger->activity($auth->id(),'create_user','user',$uid);
                    flash('success','User created. Default credentials set.');
                } catch (Exception $e) {
                    flash('error','Username already exists.');
                }
                redirect('index.php?page=admin.users');
            }

            if ($action === 'edit') {
                $uid = (int)$d['user_id'];
                $pdo->prepare("UPDATE users SET full_name=?,role=? WHERE id=?")->execute([$d['full_name'],$d['role'],$uid]);
                if (!empty($d['password'])) {
                    $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($d['password'],PASSWORD_DEFAULT),$uid]);
                }
                $pdo->prepare("DELETE FROM user_villages WHERE user_id=?")->execute([$uid]);
                if (!empty($d['villages'])) {
                    $sv = $pdo->prepare("INSERT OR IGNORE INTO user_villages (user_id,village_id) VALUES (?,?)");
                    foreach ((array)$d['villages'] as $vid) { $sv->execute([$uid,(int)$vid]); }
                }
                $logger->activity($auth->id(),'edit_user','user',$uid);
                flash('success','User updated.'); redirect('index.php?page=admin.users');
            }

            if ($action === 'toggle') {
                $uid = (int)$d['user_id'];
                if ($uid === $auth->id()) { flash('error','Cannot deactivate yourself.'); redirect('index.php?page=admin.users'); }
                $pdo->prepare("UPDATE users SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?")->execute([$uid]);
                $logger->activity($auth->id(),'toggle_user','user',$uid);
                flash('success','User status toggled.'); redirect('index.php?page=admin.users');
            }
        }

        $search = trim($_GET['search'] ?? '');
        $params = $search ? ["%$search%","%$search%"] : [];
        $where  = $search ? "WHERE username LIKE ? OR full_name LIKE ?" : "";
        $users  = $pdo->prepare("SELECT * FROM users $where ORDER BY role,full_name");
        $users->execute($params); $users = $users->fetchAll();

        // Load village assignments
        $uvStmt = $pdo->query("SELECT uv.user_id, v.name FROM user_villages uv JOIN villages v ON v.id=uv.village_id");
        $uvMap  = [];
        foreach ($uvStmt->fetchAll() as $row) { $uvMap[$row['user_id']][] = $row['name']; }

        $pageTitle = 'User Management'; $activePage = 'admin.users';
        require __DIR__ . '/../views/admin/users.php';
    }

    // ── Villages ──────────────────────────────────────────────────────────────
    public static function villages(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_SYSADMIN);
        $action = $_GET['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $d = $_POST;
            if ($action === 'create') {
                if (empty($d['name'])) { flash('error','Village name required.'); redirect('index.php?page=admin.villages'); }
                $pdo->prepare("INSERT INTO villages (name,district,allocation_amount) VALUES (?,?,?)")->execute([$d['name'],$d['district']??'',$d['allocation_amount']?? 0]);
                $logger->activity($auth->id(),'create_village','village',(int)$pdo->lastInsertId());
                flash('success','Village added.'); redirect('index.php?page=admin.villages');
            }
            if ($action === 'edit') {
                $pdo->prepare("UPDATE villages SET name=?,district=?,allocation_amount=? WHERE id=?")->execute([$d['name'],$d['district']??'',$d['allocation_amount']?? 0,(int)$d['id']]);
                flash('success','Village updated.'); redirect('index.php?page=admin.villages');
            }
            if ($action === 'toggle') {
                $pdo->prepare("UPDATE villages SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?")->execute([(int)$d['id']]);
                flash('success','Village toggled.'); redirect('index.php?page=admin.villages');
            }
        }

        $villages = $pdo->query("SELECT v.*,COUNT(ap.id) AS applicant_count FROM villages v LEFT JOIN applicants ap ON ap.village_id=v.id GROUP BY v.id ORDER BY v.name")->fetchAll();
        $pageTitle = 'Village Management'; $activePage = 'admin.villages';
        require __DIR__ . '/../views/admin/villages.php';
    }

    // ── Fund Categories ───────────────────────────────────────────────────────
    public static function categories(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_SYSADMIN);
        $action = $_GET['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $d = $_POST;
            if ($action === 'create') {
                if (empty($d['name'])) { flash('error','Category name required.'); redirect('index.php?page=admin.categories'); }
                $pdo->prepare("INSERT INTO fund_categories (name,description) VALUES (?,?)")->execute([$d['name'],$d['description']??'']);
                flash('success','Category added.'); redirect('index.php?page=admin.categories');
            }
            if ($action === 'edit') {
                $pdo->prepare("UPDATE fund_categories SET name=?,description=? WHERE id=?")->execute([$d['name'],$d['description']??'',(int)$d['id']]);
                flash('success','Category updated.'); redirect('index.php?page=admin.categories');
            }
            if ($action === 'toggle') {
                $pdo->prepare("UPDATE fund_categories SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?")->execute([(int)$d['id']]);
                flash('success','Category toggled.'); redirect('index.php?page=admin.categories');
            }
        }

        $categories = $pdo->query("SELECT fc.*,COUNT(a.id) AS usage_count FROM fund_categories fc LEFT JOIN applications a ON a.fund_category_id=fc.id GROUP BY fc.id ORDER BY fc.name")->fetchAll();
        $pageTitle = 'Fund Categories'; $activePage = 'admin.categories';
        require __DIR__ . '/../views/admin/categories.php';
    }

    // ── Audit Log ─────────────────────────────────────────────────────────────
    public static function audit(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_SYSADMIN);
        $where  = ['1=1']; $params = [];

        $filterUser   = trim($_GET['user'] ?? '');
        $filterAction = trim($_GET['action_filter'] ?? '');
        $filterDate   = trim($_GET['date'] ?? '');

        if ($filterUser)   { $where[] = 'u.full_name LIKE ?'; $params[] = "%$filterUser%"; }
        if ($filterAction) { $where[] = 'al.action LIKE ?';   $params[] = "%$filterAction%"; }
        if ($filterDate)   { $where[] = 'DATE(al.created_at) = ?'; $params[] = $filterDate; }

        $sql = "SELECT al.*,u.full_name,u.role FROM activity_log al LEFT JOIN users u ON u.id=al.user_id WHERE ".implode(' AND ',$where)." ORDER BY al.created_at DESC";
        $page   = max(1,(int)($_GET['p']??1));
        $result = paginate($pdo,$sql,$params,$page);

        $pageTitle = 'Audit Log'; $activePage = 'admin.audit';
        require __DIR__ . '/../views/admin/audit_log.php';
    }

    public static function allocations(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $id = (int)$_POST['village_id'];
            $amount = (float)$_POST['allocation_amount'];
            $pdo->prepare("UPDATE villages SET allocation_amount = ? WHERE id = ?")->execute([$amount, $id]);
            $logger->activity($auth->id(), 'update_allocation', 'village', $id);
            flash('success', 'Allocation updated.');
            redirect('index.php?page=admin.allocations');
        }

        // Fetch villages with total used amount
        $sql = "SELECT v.*, 
                (SELECT SUM(d.amount) 
                 FROM disbursements d 
                 JOIN applications a ON a.id = d.application_id 
                 JOIN applicants ap ON ap.id = a.applicant_id
                 WHERE ap.village_id = v.id 
                 AND a.status IN ('approved', 'disbursing', 'completed')
                 AND d.status = 'released'
                ) as used_amount
                FROM villages v
                WHERE v.is_active = 1
                ORDER BY v.name ASC";
        
        $villages = $pdo->query($sql)->fetchAll();

        $pageTitle = 'Project Allocations'; 
        $activePage = 'admin.allocations';
        require __DIR__ . '/../views/admin/allocations.php';
    }
}
