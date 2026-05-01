<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div class="header-actions mb-2">
  <div class="header-title">
    <h1>⚙️ Settings</h1>
    <p class="muted">Manage application users, villages, and fund categories.</p>
  </div>
</div>

<div class="grid grid-3">
  <a href="index.php?page=admin.users" class="card card-interactive">
    <div class="d-flex align-center gap-1 mb-1">
      <span style="font-size:2rem">👥</span>
      <div style="font-weight:600;font-size:1.1rem">User Management</div>
    </div>
    <p class="text-small muted">Create and manage system users, roles, and village assignments.</p>
  </a>

  <a href="index.php?page=admin.villages" class="card card-interactive">
    <div class="d-flex align-center gap-1 mb-1">
      <span style="font-size:2rem">🏘️</span>
      <div style="font-weight:600;font-size:1.1rem">Village Management</div>
    </div>
    <p class="text-small muted">Add or edit villages and set their financial allocation limits.</p>
  </a>

  <a href="index.php?page=admin.categories" class="card card-interactive">
    <div class="d-flex align-center gap-1 mb-1">
      <span style="font-size:2rem">📂</span>
      <div style="font-weight:600;font-size:1.1rem">Fund Categories</div>
    </div>
    <p class="text-small muted">Define types of assistance (e.g., Medical, Education, Housing).</p>
  </a>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
