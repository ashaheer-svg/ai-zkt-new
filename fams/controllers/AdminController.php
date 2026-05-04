<?php
/**
 * Admin Controller
 * 
 * Central hub for system administration. Handles user management, village (Thackiya)
 * configuration, fund categories, system settings, and critical maintenance tasks
 * like backups and database resets. Access is strictly limited to Sysadmins (1.c+).
 */
class AdminController
{
    /**
     * Manages system users. 
     * Handles creation, editing, status toggling, and village-level scoping assignments.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
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
                    
                    // Assign user to managed villages
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
                
                // Refresh village assignments
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
        $page = max(1, (int)($_GET['p'] ?? 1));
        $result = paginate($pdo, "SELECT * FROM users $where ORDER BY role,full_name", $params, $page);
        $users = $result['rows'];
        $pagination = $result;

        // Map users to their assigned villages for the UI view
        $uvStmt = $pdo->query("SELECT uv.user_id, v.name, v.district FROM user_villages uv JOIN villages v ON v.id=uv.village_id");
        $uvMap  = [];
        foreach ($uvStmt->fetchAll() as $row) { 
            $thackiya = $row['district'] ? " (" . $row['district'] . ")" : "";
            $uvMap[$row['user_id']][] = $row['name'] . $thackiya; 
        }

        $pageTitle = 'User Management'; $activePage = 'admin.users';
        require __DIR__ . '/../views/admin/users.php';
    }

    /**
     * Manages villages (geographically scoped units).
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
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

        $page = max(1, (int)($_GET['p'] ?? 1));
        $sql = "SELECT v.*,COUNT(ap.id) AS applicant_count FROM villages v LEFT JOIN applicants ap ON ap.village_id=v.id GROUP BY v.id ORDER BY v.name";
        $result = paginate($pdo, $sql, [], $page);
        $villages = $result['rows'];
        $pagination = $result;
        $pageTitle = 'Village Management'; $activePage = 'admin.villages';
        require __DIR__ . '/../views/admin/villages.php';
    }

    /**
     * Configures fund categories.
     * 
     * @param PDO $pdo
     * @param Auth $auth
     * @param Logger $logger
     */
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

        $page = max(1, (int)($_GET['p'] ?? 1));
        $sql = "SELECT fc.*,COUNT(a.id) AS usage_count FROM fund_categories fc LEFT JOIN applications a ON a.fund_category_id=fc.id GROUP BY fc.id ORDER BY fc.name";
        $result = paginate($pdo, $sql, [], $page);
        $categories = $result['rows'];
        $pagination = $result;
        $pageTitle = 'Fund Categories'; $activePage = 'admin.categories';
        require __DIR__ . '/../views/admin/categories.php';
    }

    /**
     * Displays a financial summary of all village allocations.
     * Shows committed vs released funds per geographic unit.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
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

    /**
     * Manages geographic budget allocations.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
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

        // Aggregate released vs committed funds per village
        $sql = "SELECT v.*, 
                (SELECT SUM(d.amount) 
                 FROM disbursements d 
                 JOIN applications a ON a.id = d.application_id 
                 JOIN applicants ap ON ap.id = a.applicant_id
                 WHERE ap.village_id = v.id 
                 AND d.status = 'released'
                ) as released_amount,
                (SELECT SUM(d.amount) 
                 FROM disbursements d 
                 JOIN applications a ON a.id = d.application_id 
                 JOIN applicants ap ON ap.id = a.applicant_id
                 WHERE ap.village_id = v.id 
                 AND d.status IN ('pending', 'authorized')
                 AND a.status IN ('approved', 'disbursing')
                ) as committed_amount
                FROM villages v
                WHERE v.is_active = 1
                ORDER BY v.name ASC";
        
        $page = max(1, (int)($_GET['p'] ?? 1));
        $result = paginate($pdo, $sql, [], $page);
        $villages = $result['rows'];
        $pagination = $result;

        // User Fund Summary (For 1.c and 1.b)
        $userFundSql = "
            SELECT 
                u.id, 
                u.full_name, 
                u.role, 
                u.balance,
                (SELECT COALESCE(SUM(ct.amount), 0) FROM cash_transfers ct WHERE ct.to_user_id = u.id) as total_received,
                (SELECT COALESCE(SUM(ct.amount), 0) FROM cash_transfers ct WHERE ct.from_user_id = u.id) as total_transferred_out,
                (SELECT COALESCE(SUM(d.amount), 0) FROM disbursements d WHERE d.paid_by = u.id AND d.status = 'released') as total_disbursed,
                (SELECT COALESCE(SUM(d.amount), 0) FROM disbursements d WHERE d.assigned_to = u.id AND d.status = 'authorized') as total_pending_release,
                (SELECT GROUP_CONCAT(v.name || ' (' || COALESCE(v.district, '—') || ')', ', ') FROM user_villages uv JOIN villages v ON v.id = uv.village_id WHERE uv.user_id = u.id) as assigned_villages
            FROM users u
            WHERE u.role IN (?, ?) AND u.is_active = 1
            ORDER BY u.role DESC, u.full_name ASC
        ";
        $userSummaryStmt = $pdo->prepare($userFundSql);
        $userSummaryStmt->execute([ROLE_VILLAGE_INCHARGE, ROLE_OVERALL_INCHARGE]);
        $userFunds = $userSummaryStmt->fetchAll();

        // Staff Transfer Breakdown (1.c to 1.b)
        $transferSql = "
            SELECT 
                f.full_name as from_name,
                t.full_name as to_name,
                SUM(ct.amount) as total_amount
            FROM cash_transfers ct
            JOIN users f ON f.id = ct.from_user_id
            JOIN users t ON t.id = ct.to_user_id
            GROUP BY ct.from_user_id, ct.to_user_id
            ORDER BY f.full_name ASC, total_amount DESC
        ";
        $staffTransfers = $pdo->query($transferSql)->fetchAll();

        $pageTitle = 'Project Allocations'; 
        $activePage = 'admin.allocations';
        require __DIR__ . '/../views/admin/allocations.php';
    }

    /**
     * Manages system-wide configuration and API token generation.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function settings(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_SYSADMIN);
        $action = $_GET['sub_action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            if ($action === 'general') {
                $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key = ?");
                $debugValue = isset($_POST['debug_mode']) ? '1' : '0';
                $stmt->execute([$debugValue, 'debug_mode']);
                if (!empty($_POST['timezone'])) {
                    $stmt->execute([$_POST['timezone'], 'timezone']);
                }
                flash('success', 'General settings updated.');
            }

            if ($action === 'generate_token') {
                $uid = (int)$_POST['user_id'];
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+365 days'));
                
                // One active token per user
                $pdo->prepare("DELETE FROM api_tokens WHERE user_id=?")->execute([$uid]);
                $pdo->prepare("INSERT INTO api_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
                    ->execute([$uid, $token, $expires]);
                
                flash('success', 'New API token generated for 365 days.');
            }

            redirect('index.php?page=admin.settings');
        }

        $settings = $pdo->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $timezones = DateTimeZone::listIdentifiers();
        $users = $pdo->query("SELECT id, username, full_name, role FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();
        $tokens = $pdo->query("SELECT t.*, u.full_name FROM api_tokens t JOIN users u ON u.id = t.user_id")->fetchAll();

        $pageTitle = 'System Settings'; $activePage = 'admin.settings';
        require __DIR__ . '/../views/admin/settings.php';
    }

    /**
     * Configures allowed document types.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function doc_types(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_SYSADMIN);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = $_GET['action'] ?? '';

            if ($action === 'add') {
                $name = trim($_POST['name'] ?? '');
                if ($name) {
                    $pdo->prepare("INSERT OR IGNORE INTO document_types (name) VALUES (?)")->execute([$name]);
                    flash('success', 'Document type added.');
                }
            }

            if ($action === 'toggle') {
                $id = (int)$_POST['id'];
                $pdo->prepare("UPDATE document_types SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?")->execute([$id]);
                flash('success', 'Document type status updated.');
            }

            redirect('index.php?page=admin.doc_types');
        }

        $docTypes = $pdo->query("SELECT * FROM document_types ORDER BY name")->fetchAll();
        
        $pageTitle = 'Document Types'; $activePage = 'admin.doc_types';
        require __DIR__ . '/../views/admin/doc_types.php';
    }

    /**
     * Disaster Recovery: Performs a Factory Reset of the entire system.
     * Wipes all data tables and re-seeds default admin credentials.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function system(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_SYSADMIN);
        
        $dbPath = __DIR__ . '/../database/fams.sqlite';
        $dbSize = file_exists($dbPath) ? filesize($dbPath) : 0;
        
        $diskTotal = disk_total_space("/");
        $diskFree  = disk_free_space("/");
        $diskUsed  = $diskTotal - $diskFree;
        $diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 2) : 0;

        $sysInfo = [
            'db_size' => $dbSize,
            'disk_total' => $diskTotal,
            'disk_free' => $diskFree,
            'disk_used' => $diskUsed,
            'disk_percent' => $diskPercent,
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os' => PHP_OS,
        ];

        $pageTitle = 'Administration'; $activePage = 'admin.system';
        require __DIR__ . '/../views/admin/system.php';
    }

    /**
     * Wipes all data and re-initializes the database.
     * CRITICAL: Irreversible operation. Requires typing "RESET" in UI.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function resetDB(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_SYSADMIN);
        csrf_verify();

        if (($_POST['confirm_reset'] ?? '') !== 'RESET') {
            flash('error', 'Database reset failed. You must type RESET to confirm.');
            redirect('index.php?page=admin.settings');
        }

        try {
            dropAllTables($pdo);
            _createSchema($pdo);
            _migrate($pdo);
            _seedAdmin($pdo);

            $logger->activity($auth->id(), 'reset_database', 'system', 0);
            
            session_destroy();
            header('Location: index.php?page=login&reset=success');
            exit;
        } catch (Exception $e) {
            flash('error', 'Error resetting database: ' . $e->getMessage());
            redirect('index.php?page=admin.settings');
        }
    }

    /**
     * Creates a full system ZIP backup.
     * Includes all code, uploads, and the active database.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function fullBackup(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_SYSADMIN);
        
        $rootPath = realpath(__DIR__ . '/../');
        $zipFile  = sys_get_temp_dir() . '/fams_full_backup_' . date('Y-m-d_His') . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            flash('error', 'Could not create ZIP archive.');
            redirect('index.php?page=admin.settings');
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);

                // Exclude temporary/sensitive directories
                if (str_contains($relativePath, 'sessions' . DIRECTORY_SEPARATOR)) continue;
                if (str_contains($relativePath, '.git' . DIRECTORY_SEPARATOR)) continue;
                if (str_contains($relativePath, '.gemini' . DIRECTORY_SEPARATOR)) continue;
                if (basename($filePath) === '.env') continue;

                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        $logger->activity($auth->id(), 'full_system_backup', 'system', 0);

        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($zipFile));
        readfile($zipFile);
        unlink($zipFile);
        exit;
    }

    /**
     * Performs a full backup of the SQLite database file.
     * 
     * @param PDO $pdo The database connection instance.
     * @param Auth $auth The authentication service.
     * @param Logger $logger The activity logging service.
     * @return void
     */
    public static function db_backup(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $auth->requireRole(ROLE_SYSADMIN);
        $dbPath = __DIR__ . '/../database/fams.sqlite';
        if (!file_exists($dbPath)) { flash('error', 'DB file not found.'); redirect('index.php?page=admin.system'); }

        $logger->activity($auth->id(), 'db_backup', 'system', 0);
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="nct_backup_'.date('Y-m-d_His').'.sqlite"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($dbPath));
        readfile($dbPath);
        exit;
    }
}
