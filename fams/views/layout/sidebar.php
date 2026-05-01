<?php
// $auth and $activePage must be set by the controller
$role = $auth->role();
$u    = $auth->user();

$nav = [];

// Dashboard
if (in_array($role,[ROLE_OVERALL_INCHARGE,ROLE_SYSADMIN])) {
    $nav[] = ['page'=>'dashboard','icon'=>'📊','label'=>'Dashboard'];
}

// Applications
$nav[] = ['page'=>'applications','icon'=>'📋','label'=>'NCTs'];

// Pending Validation
if (in_array($role,[ROLE_DATA_ENTRY,ROLE_VILLAGE_INCHARGE,ROLE_OVERALL_INCHARGE,ROLE_SYSADMIN])) {
    $nav[] = ['page'=>'applications.pending','icon'=>'🔍','label'=>'Pending Validation'];
}

// Disbursements & Allocations
if (in_array($role,[ROLE_OVERALL_INCHARGE,ROLE_SYSADMIN])) {
    $nav[] = ['page'=>'disbursements',     'icon'=>'🏦','label'=>'Disbursements'];
    $nav[] = ['page'=>'admin.allocations', 'icon'=>'💰','label'=>'Allocations'];
}

// Admin
if ($role === ROLE_SYSADMIN) {
    $nav[] = ['page'=>'admin.users',     'icon'=>'👥','label'=>'Users'];
    $nav[] = ['page'=>'admin.villages',  'icon'=>'🏘️','label'=>'Villages'];
    $nav[] = ['page'=>'admin.categories','icon'=>'🗂️','label'=>'Fund Categories'];
    $nav[] = ['page'=>'admin.audit',     'icon'=>'📜','label'=>'Audit Log'];
}
?>
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">F</div>
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
      <a href="index.php?page=<?= e($item['page']) ?>"
         class="nav-item <?= ($activePage === $item['page']) ? 'active' : '' ?>">
        <span class="nav-icon"><?= $item['icon'] ?></span>
        <span class="nav-label"><?= e($item['label']) ?></span>
      </a>
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
    </div>
    <div class="flash-messages">
      <?= flash_html() ?>
    </div>
    <div class="content-area">
