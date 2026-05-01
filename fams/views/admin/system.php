<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div class="header-actions mb-2">
  <div class="header-title">
    <h1>🛡️ Administration</h1>
    <p class="muted">System health, maintenance, and database backups.</p>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <div class="card-title">💾 Database Management</div>
    <div class="d-flex flex-column gap-1">
      <div class="detail-item">
        <div class="detail-label">SQLite Database Size</div>
        <div class="detail-value"><?= round($sysInfo['db_size'] / 1024 / 1024, 2) ?> MB</div>
      </div>
      <div class="mt-1">
        <a href="index.php?page=admin.db_backup" class="btn btn-success">📥 Download DB Backup</a>
      </div>
      <p class="text-small muted mt-1">
        <span class="badge badge-outline">Tip</span> Frequent backups are recommended before major data operations.
      </p>
    </div>
  </div>

  <div class="card">
    <div class="card-title">📊 Server Storage</div>
    <div class="mb-1">
      <div class="d-flex justify-between text-small mb-05">
        <span>Disk Usage</span>
        <span><?= round($sysInfo['disk_used'] / 1024 / 1024 / 1024, 2) ?> GB / <?= round($sysInfo['disk_total'] / 1024 / 1024 / 1024, 2) ?> GB</span>
      </div>
      <div class="progress-container">
        <div class="progress-bar <?= $sysInfo['disk_percent'] > 90 ? 'bg-danger' : ($sysInfo['disk_percent'] > 70 ? 'bg-warning' : 'bg-primary') ?>" 
             style="width: <?= $sysInfo['disk_percent'] ?>%"></div>
      </div>
    </div>
    <div class="detail-grid">
        <div class="detail-item">
            <div class="detail-label">Free Space</div>
            <div class="detail-value"><?= round($sysInfo['disk_free'] / 1024 / 1024 / 1024, 2) ?> GB</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Usage Percentage</div>
            <div class="detail-value"><?= $sysInfo['disk_percent'] ?>%</div>
        </div>
    </div>
  </div>
</div>

<div class="card mt-2">
    <div class="card-title">⚙️ System Information</div>
    <div class="detail-grid grid-3">
        <div class="detail-item">
            <div class="detail-label">PHP Version</div>
            <div class="detail-value"><?= e($sysInfo['php_version']) ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Server Software</div>
            <div class="detail-value"><?= e($sysInfo['server_software']) ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Operating System</div>
            <div class="detail-value"><?= e($sysInfo['os']) ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Deployment Version</div>
            <div class="detail-value"><code><?= defined('APP_DEPLOY_VERSION') ? APP_DEPLOY_VERSION : 'N/A' ?></code></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
