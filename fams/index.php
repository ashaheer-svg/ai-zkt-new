<?php
/**
 * Application Entry Point (Front Controller)
 * 
 * This file bootstraps the Zakath Financial Management System.
 * Tasks performed:
 * 1. Session initialization with isolated storage.
 * 2. Dependency loading (Config, Helpers, Core Services).
 * 3. Database connection and environment configuration (Timezone, Debug).
 * 4. Authentication state check and routing logic.
 * 5. Direct file streaming (for document security).
 * 6. Centralized Dispatching via PHP match expression.
 * 
 * @version 1.0.3-SECURE
 * @package NCT-Zakat
 */
declare(strict_types=1);

$sessPath = __DIR__ . '/sessions';
if (!is_dir($sessPath)) mkdir($sessPath, 0755, true);
session_save_path($sessPath);
session_start();
define('APP_DEPLOY_VERSION', 'v1.0.3-SECURE');

if (empty($_SESSION['session_test'])) {
    $_SESSION['session_test'] = time();
}

// ── 1. Load Core Components ──────────────────────────────────────────────────
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Logger.php';
require_once __DIR__ . '/src/Upload.php';

// ── 2. Load Action Controllers ───────────────────────────────────────────────
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/ApplicationController.php';
require_once __DIR__ . '/controllers/DisbursementController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/CashController.php';
require_once __DIR__ . '/controllers/ApiController.php';

$pdo = getDB();

// ── 3. Bootstrap Settings ────────────────────────────────────────────────────
// Load dynamic settings from the database (managed via AdminController::settings)
$sysSettings = $pdo->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$isDebug     = ($sysSettings['debug_mode'] ?? '0') === '1';
$timezone    = $sysSettings['timezone'] ?? 'Asia/Colombo';

error_reporting($isDebug ? E_ALL : 0);
ini_set('display_errors', $isDebug ? '1' : '0');
date_default_timezone_set($timezone);

// Global Service Instantiation
$auth   = new Auth($pdo);
$logger = new Logger($pdo);

// ── 4. Routing Determination ──────────────────────────────────────────────────
$page   = $_GET['page']   ?? '';
if ($page === '') {
    if (!$auth->isLoggedIn()) {
        $page = 'login';
    } else {
        // Management users default to dashboard; others to application list
        $page = $auth->hasRole([ROLE_SYSADMIN, ROLE_OVERALL_INCHARGE, ROLE_VILLAGE_INCHARGE]) ? 'dashboard' : 'applications';
    }
}

// ── 5. Protected Document Streaming ─────────────────────────────────────────
// Documents are stored outside web-root or in protected folders.
// This route acts as a proxy to stream files while enforcing RBAC.
if ($page === 'doc.download') {
    $auth->requireLogin();
    $docId  = (int)($_GET['id'] ?? 0);
    $upload = new Upload($pdo, $auth->id());
    $doc    = $upload->getById($docId);
    if (!$doc) { http_response_code(404); die('Not found.'); }
    
    // Authorization Check: Does the user have access to the parent application?
    $appStmt = $pdo->prepare('SELECT a.*, ap.village_id FROM applications a JOIN applicants ap ON ap.id = a.applicant_id WHERE a.id = ?');
    $appStmt->execute([$doc['application_id']]);
    $app = $appStmt->fetch();
    if (!$app || !$auth->canViewApplication($app)) { http_response_code(403); die('Access denied.'); }
    
    $logger->activity($auth->id(), 'download_document', 'application_document', $docId);
    $upload->stream($docId);
}

// ── 6. Authentication Gate ────────────────────────────────────────────────────
if (!$auth->isLoggedIn() && $page !== 'login') {
    redirect('index.php?page=login');
}

// ── 7. Route Dispatcher ───────────────────────────────────────────────────────
match (true) {
    // Authentication Endpoints
    $page === 'login'  => AuthController::login($pdo, $auth, $logger),
    $page === 'logout' => AuthController::logout($pdo, $auth, $logger),

    // Dashboard Overview
    $page === 'dashboard' => DashboardController::overview($pdo, $auth, $logger),

    // Application Lifecycle (Draft -> Pending -> Validated -> Approved -> Disbursing)
    $page === 'applications'           => ApplicationController::list($pdo, $auth, $logger),
    $page === 'applications.create'    => ApplicationController::create($pdo, $auth, $logger),
    $page === 'applications.edit'      => ApplicationController::edit($pdo, $auth, $logger),
    $page === 'applications.view'      => ApplicationController::view($pdo, $auth, $logger),
    $page === 'applications.pending'   => ApplicationController::pending($pdo, $auth, $logger),
    $page === 'applications.validate'  => ApplicationController::validateApp($pdo, $auth, $logger),
    $page === 'applications.comment'   => ApplicationController::comment($pdo, $auth, $logger),
    $page === 'applications.review'    => ApplicationController::review($pdo, $auth, $logger),
    $page === 'applications.approve'   => ApplicationController::approve($pdo, $auth, $logger),
    $page === 'applications.adjust'    => ApplicationController::adjustSchedule($pdo, $auth, $logger),
    $page === 'applications.reject'    => ApplicationController::reject($pdo, $auth, $logger),
    $page === 'applications.revert'    => ApplicationController::revert($pdo, $auth, $logger),
    $page === 'applications.hold'      => ApplicationController::hold($pdo, $auth, $logger),
    $page === 'applications.unhold'    => ApplicationController::unhold($pdo, $auth, $logger),
    $page === 'applications.privilege' => ApplicationController::setPrivileged($pdo, $auth, $logger),
    $page === 'applications.upload'    => ApplicationController::uploadDoc($pdo, $auth, $logger),
    $page === 'applications.deldoc'    => ApplicationController::deleteDoc($pdo, $auth, $logger),

    // Financial Disbursement & Authorization (Role 1.c -> 1.b)
    $page === 'disbursements'                 => DisbursementController::list($pdo, $auth, $logger),
    $page === 'disbursements.pending_release' => DisbursementController::pendingRelease($pdo, $auth, $logger),
    $page === 'disbursements.schedule'        => DisbursementController::schedule($pdo, $auth, $logger),
    $page === 'disbursements.authorize'      => DisbursementController::authorize($pdo, $auth, $logger),
    $page === 'disbursements.bulk_authorize' => DisbursementController::bulkAuthorize($pdo, $auth, $logger),
    $page === 'disbursements.release'        => DisbursementController::release($pdo, $auth, $logger),

    // Virtual Wallet & Inter-user Cash Transfers
    $page === 'cash.transfers' => CashController::index($pdo, $auth, $logger),
    $page === 'cash.transfer'  => CashController::transfer($pdo, $auth, $logger),

    // System Administration (Sysadmin Only)
    $page === 'admin.users'      => AdminController::users($pdo, $auth, $logger),
    $page === 'admin.villages'   => AdminController::villages($pdo, $auth, $logger),
    $page === 'admin.categories' => AdminController::categories($pdo, $auth, $logger),
    $page === 'admin.audit'      => AdminController::audit($pdo, $auth, $logger),
    $page === 'admin.allocations' => AdminController::allocations($pdo, $auth, $logger),
    $page === 'admin.settings'   => AdminController::settings($pdo, $auth, $logger),
    $page === 'admin.system'     => AdminController::system($pdo, $auth, $logger),
    $page === 'admin.db_backup'  => AdminController::db_backup($pdo, $auth, $logger),
    $page === 'admin.full_backup' => AdminController::fullBackup($pdo, $auth, $logger),
    $page === 'admin.reset'      => AdminController::resetDB($pdo, $auth, $logger),
    $page === 'admin.doc_types'  => AdminController::doc_types($pdo, $auth, $logger),

    // Mobile API Endpoints (Token Authenticated)
    $page === 'api.projects'       => ApiController::projects($pdo, $auth),
    $page === 'api.document-types' => ApiController::documentTypes($pdo, $auth),
    $page === 'api.upload'         => ApiController::upload($pdo, $auth, $logger),

    // 404 Handler
    default => (function() {
        http_response_code(404);
        echo '<h1>404 — Page not found</h1>';
    })(),
};
