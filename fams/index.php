<?php
declare(strict_types=1);

session_start();
if (empty($_SESSION['session_test'])) {
    $_SESSION['session_test'] = time();
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Logger.php';
require_once __DIR__ . '/src/Upload.php';

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/ApplicationController.php';
require_once __DIR__ . '/controllers/DisbursementController.php';
require_once __DIR__ . '/controllers/AdminController.php';

$pdo    = getDB();
$auth   = new Auth($pdo);
$logger = new Logger($pdo);

$page   = $_GET['page']   ?? '';
if ($page === '') {
    if (!$auth->isLoggedIn()) {
        $page = 'login';
    } else {
        $page = $auth->hasRole([ROLE_SYSADMIN, ROLE_OVERALL_INCHARGE]) ? 'dashboard' : 'applications';
    }
}

// ── File download — no layout needed ─────────────────────────────────────────
if ($page === 'doc.download') {
    $auth->requireLogin();
    $docId  = (int)($_GET['id'] ?? 0);
    $upload = new Upload($pdo, $auth->id());
    $doc    = $upload->getById($docId);
    if (!$doc) { http_response_code(404); die('Not found.'); }
    // Gate: user must be able to view the parent application
    $appStmt = $pdo->prepare('SELECT a.*, ap.village_id FROM applications a JOIN applicants ap ON ap.id = a.applicant_id WHERE a.id = ?');
    $appStmt->execute([$doc['application_id']]);
    $app = $appStmt->fetch();
    if (!$app || !$auth->canViewApplication($app)) { http_response_code(403); die('Access denied.'); }
    $logger->activity($auth->id(), 'download_document', 'application_document', $docId);
    $upload->stream($docId);
}

// ── Auth gate ─────────────────────────────────────────────────────────────────
if (!$auth->isLoggedIn() && $page !== 'login') {
    redirect('index.php?page=login');
}

// ── Route ─────────────────────────────────────────────────────────────────────
match (true) {
    // Auth
    $page === 'login'  => AuthController::login($pdo, $auth, $logger),
    $page === 'logout' => AuthController::logout($pdo, $auth, $logger),

    // Dashboard
    $page === 'dashboard' => DashboardController::overview($pdo, $auth, $logger),

    // Applications
    $page === 'applications'           => ApplicationController::list($pdo, $auth, $logger),
    $page === 'applications.create'    => ApplicationController::create($pdo, $auth, $logger),
    $page === 'applications.edit'      => ApplicationController::edit($pdo, $auth, $logger),
    $page === 'applications.view'      => ApplicationController::view($pdo, $auth, $logger),
    $page === 'applications.pending'   => ApplicationController::pending($pdo, $auth, $logger),
    $page === 'applications.validate'  => ApplicationController::validateApp($pdo, $auth, $logger),
    $page === 'applications.comment'   => ApplicationController::comment($pdo, $auth, $logger),
    $page === 'applications.review'    => ApplicationController::review($pdo, $auth, $logger),
    $page === 'applications.approve'   => ApplicationController::approve($pdo, $auth, $logger),
    $page === 'applications.reject'    => ApplicationController::reject($pdo, $auth, $logger),
    $page === 'applications.privilege' => ApplicationController::setPrivileged($pdo, $auth, $logger),
    $page === 'applications.upload'    => ApplicationController::uploadDoc($pdo, $auth, $logger),
    $page === 'applications.deldoc'    => ApplicationController::deleteDoc($pdo, $auth, $logger),

    // Disbursements
    $page === 'disbursements'           => DisbursementController::list($pdo, $auth, $logger),
    $page === 'disbursements.schedule'  => DisbursementController::schedule($pdo, $auth, $logger),
    $page === 'disbursements.authorize' => DisbursementController::authorize($pdo, $auth, $logger),
    $page === 'disbursements.release'   => DisbursementController::release($pdo, $auth, $logger),

    // Admin
    $page === 'admin.users'      => AdminController::users($pdo, $auth, $logger),
    $page === 'admin.villages'   => AdminController::villages($pdo, $auth, $logger),
    $page === 'admin.categories' => AdminController::categories($pdo, $auth, $logger),
    $page === 'admin.audit'      => AdminController::audit($pdo, $auth, $logger),

    // Fallback
    default => (function() {
        http_response_code(404);
        echo '<h1>404 — Page not found</h1>';
    })(),
};
