<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div class="header-actions mb-2">
  <div class="header-title">
    <h1>⚙️ System Control Panel</h1>
    <p class="muted">Manage user access, project configuration, and system maintenance.</p>
  </div>
</div>

<div class="divider"></div>

<div class="mb-3">
  <h3 class="mb-1" style="font-size:0.9rem;color:var(--primary);text-transform:uppercase;letter-spacing:0.05em">User & Access Control</h3>
  <div class="grid grid-3">
    <a href="index.php?page=admin.users" class="card card-interactive">
      <div class="d-flex align-center gap-1 mb-1">
        <span style="font-size:1.5rem">👥</span>
        <div style="font-weight:600">User Management</div>
      </div>
      <p class="text-small muted">Create and manage system users, roles, and village assignments.</p>
    </a>
    <a href="index.php?page=admin.audit" class="card card-interactive">
      <div class="d-flex align-center gap-1 mb-1">
        <span style="font-size:1.5rem">📜</span>
        <div style="font-weight:600">Audit Logs</div>
      </div>
      <p class="text-small muted">Track all system activities and administrative changes.</p>
    </a>
  </div>
</div>

<div class="mb-3">
  <h3 class="mb-1" style="font-size:0.9rem;color:var(--primary);text-transform:uppercase;letter-spacing:0.05em">Project Configuration</h3>
  <div class="grid grid-3">
    <a href="index.php?page=admin.villages" class="card card-interactive">
      <div class="d-flex align-center gap-1 mb-1">
        <span style="font-size:1.5rem">🏘️</span>
        <div style="font-weight:600">Village Management</div>
      </div>
      <p class="text-small muted">Add or edit villages and set their basic status.</p>
    </a>
    <a href="index.php?page=admin.allocations" class="card card-interactive">
      <div class="d-flex align-center gap-1 mb-1">
        <span style="font-size:1.5rem">💰</span>
        <div style="font-weight:600">Project Allocations</div>
      </div>
      <p class="text-small muted">Set and monitor financial limits for each village.</p>
    </a>
    <a href="index.php?page=admin.categories" class="card card-interactive">
      <div class="d-flex align-center gap-1 mb-1">
        <span style="font-size:1.5rem">🗂️</span>
        <div style="font-weight:600">Fund Categories</div>
      </div>
      <p class="text-small muted">Define assistance types like Medical, Education, etc.</p>
    </a>
  </div>
</div>

<div class="mb-3">
  <h3 class="mb-1" style="font-size:0.9rem;color:var(--primary);text-transform:uppercase;letter-spacing:0.05em">System Maintenance</h3>
  <div class="grid grid-3">
    <a href="index.php?page=admin.system" class="card card-interactive">
      <div class="d-flex align-center gap-1 mb-1">
        <span style="font-size:1.5rem">🛡️</span>
        <div style="font-weight:600">Administration</div>
      </div>
      <p class="text-small muted">System health, disk usage, and database backups.</p>
    </a>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
