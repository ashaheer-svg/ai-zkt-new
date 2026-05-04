<?php
// $auth and $activePage must be set by the controller
$role = $auth->role();
$u    = $auth->user();

$nav = [];

// Dashboard
if (in_array($role,[ROLE_OVERALL_INCHARGE,ROLE_SYSADMIN,ROLE_VILLAGE_INCHARGE])) {
    $nav[] = ['page'=>'dashboard','icon'=>'📊','label'=>'Dashboard'];
}

// Applications
$nav[] = ['page'=>'applications','icon'=>'📋','label'=>'Projects'];

// Pending Validation
if (in_array($role,[ROLE_DATA_ENTRY,ROLE_VILLAGE_INCHARGE,ROLE_OVERALL_INCHARGE,ROLE_SYSADMIN])) {
    $nav[] = ['page'=>'applications.pending','icon'=>'🔍','label'=>'Pending Validation'];
}

// Pending Release (for 1.b)
if ($role === ROLE_VILLAGE_INCHARGE) {
    $nav[] = ['page'=>'disbursements.pending_release', 'icon'=>'📤', 'label'=>'Pending Payments'];
}

// Disbursements & Allocations
if (in_array($role,[ROLE_OVERALL_INCHARGE,ROLE_SYSADMIN])) {
    $nav[] = ['page'=>'disbursements',     'icon'=>'🏦','label'=>'Disbursements'];
    $nav[] = ['page'=>'cash.transfers',     'icon'=>'💸','label'=>'Cash Transfers'];
    $nav[] = ['page'=>'admin.allocations', 'icon'=>'💰','label'=>'Allocations'];
    $nav[] = ['page'=>'admin.village_staffing', 'icon'=>'🏘️','label'=>'Village Staffing'];
}

// Admin
if ($role === ROLE_SYSADMIN) {
    $nav[] = [
        'page'  => 'admin.settings', 
        'icon'  => '⚙️', 
        'label' => 'Settings',
        'sub'   => [
            ['page'=>'admin.users',      'label'=>'Users'],
            ['page'=>'admin.villages',   'label'=>'Villages'],
            ['page'=>'admin.categories', 'label'=>'Fund Categories'],
            ['page'=>'admin.allocations','label'=>'Allocations'],
            ['page'=>'admin.system',     'label'=>'Administration'],
            ['page'=>'admin.audit',      'label'=>'Audit Log'],
        ]
    ];
}
?>
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">N</div>
      <div class="brand-text">
        <span class="brand-name"><?= APP_SHORT ?></span>
        <span class="brand-sub">v<?= APP_VERSION ?></span>
      </div>
    </div>

    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($u['full_name'],0,1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= e($u['full_name']) ?></div>
        <div class="user-role"><?= role_label($role) ?></div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <?php foreach ($nav as $item): ?>
      <div class="nav-group">
        <a href="index.php?page=<?= e($item['page']) ?>"
           class="nav-item <?= ($activePage === $item['page']) ? 'active' : '' ?>">
          <span class="nav-icon"><?= $item['icon'] ?></span>
          <span class="nav-label"><?= e($item['label']) ?></span>
        </a>
        <?php if (!empty($item['sub'])): ?>
        <div class="nav-sub">
          <?php foreach ($item['sub'] as $sub): ?>
          <a href="index.php?page=<?= e($sub['page']) ?>" 
             class="nav-sub-item <?= ($activePage === $sub['page']) ? 'active' : '' ?>">
            <?= e($sub['label']) ?>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
      <a href="index.php?page=logout" class="nav-item nav-logout">
        <span class="nav-icon">🚪</span>
        <span class="nav-label">Sign Out</span>
      </a>
    </div>
  </aside>

  <main class="main-content" id="mainContent">
    <div class="page-header">
      <h1 class="page-title"><?= e($pageTitle ?? '') ?></h1>
      <div class="header-logo">
        <img src="assets/img/logo.png" alt="NCT Logo" onerror="this.style.display='none'">
      </div>
    </div>
    <div class="flash-messages">
      <?= flash_html() ?>
    </div>
    <div class="content-area">
