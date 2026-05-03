<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div class="header-main">
  <div class="header-title">
    <h1>📄 Document Types</h1>
    <p>Manage the types of documents and photos that can be uploaded via the mobile app.</p>
  </div>
  <div class="header-actions">
    <button onclick="document.getElementById('addTypeModal').style.display='flex'" class="btn btn-primary">➕ Add New Type</button>
  </div>
</div>

<?= flash_html() ?>

<div class="card">
  <div class="table-wrap">
    <table class="table-card">
      <thead>
        <tr>
          <th>Type Name</th>
          <th>Created Date</th>
          <th>Status</th>
          <th class="text-right">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($docTypes)): ?>
        <tr><td colspan="4" class="text-center muted">No document types defined.</td></tr>
        <?php endif; ?>
        <?php foreach ($docTypes as $dt): ?>
        <tr>
          <td><strong><?= e($dt['name']) ?></strong></td>
          <td class="text-tiny"><?= fdate($dt['created_at']) ?></td>
          <td>
            <?= $dt['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>' ?>
          </td>
          <td class="text-right">
            <form method="POST" action="index.php?page=admin.doc_types&action=toggle" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $dt['id'] ?>">
              <button type="submit" class="btn btn-outline btn-xs">
                <?= $dt['is_active'] ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Type Modal -->
<div id="addTypeModal" class="modal-overlay" style="display:none">
  <div class="modal-content" style="max-width: 400px;">
    <div class="modal-header">
      <h3>Add Document Type</h3>
      <button onclick="document.getElementById('addTypeModal').style.display='none'" class="close-btn">&times;</button>
    </div>
    <form method="POST" action="index.php?page=admin.doc_types&action=add" class="modal-body">
      <?= csrf_field() ?>
      <div class="form-group mb-2">
        <label>Type Name</label>
        <input type="text" name="name" placeholder="e.g. Identity Card, Land Deed" required class="form-control" autofocus>
      </div>
      <div class="btn-group">
        <button type="submit" class="btn btn-primary">Save Document Type</button>
        <button type="button" onclick="document.getElementById('addTypeModal').style.display='none'" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
