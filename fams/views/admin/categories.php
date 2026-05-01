<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<?php $action = $_GET['action'] ?? ''; $editItem = null;
if ($action==='edit' && isset($_GET['id'])) { $s=$pdo->prepare("SELECT * FROM fund_categories WHERE id=?");$s->execute([(int)$_GET['id']]);$editItem=$s->fetch(); } ?>

<div class="card mb-2">
  <div class="card-title"><?= $editItem ? '✏️ Edit Category' : '➕ New Fund Category' ?></div>
  <form method="POST" action="index.php?page=admin.categories&action=<?= $editItem?'edit':'create' ?>">
    <?= csrf_field() ?>
    <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="form-group"><label>Category Name *</label><input type="text" name="name" value="<?= e($editItem['name']??'') ?>" required placeholder="e.g. Housing, Education, Medical"></div>
      <div class="form-group full"><label>Description</label><textarea name="description"><?= e($editItem['description']??'') ?></textarea></div>
    </div>
    <div class="btn-group mt-2">
      <button type="submit" class="btn btn-primary"><?= $editItem?'💾 Update':'➕ Add Category' ?></button>
      <?php if ($editItem): ?><a href="index.php?page=admin.categories" class="btn btn-outline">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card" style="padding:0"><div class="table-wrap"><table class="table-card">
  <thead><tr><th>Category</th><th>Description</th><th>Applications</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
    <?php foreach ($categories as $c): ?>
    <tr>
      <td data-label="Category"><strong><?= e($c['name']) ?></strong></td>
      <td data-label="Description" class="muted"><?= e($c['description']?:'—') ?></td>
      <td data-label="Applications"><?= $c['usage_count'] ?></td>
      <td data-label="Status"><?= $c['is_active']?'<span class="badge badge-green">Active</span>':'<span class="badge badge-red">Inactive</span>' ?></td>
      <td data-label="Actions"><div class="btn-group">
        <a href="index.php?page=admin.categories&action=edit&id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
        <form method="POST" action="index.php?page=admin.categories&action=toggle" style="display:inline">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button class="btn <?= $c['is_active']?'btn-danger':'btn-success' ?> btn-sm" data-confirm="Toggle category?"><?= $c['is_active']?'Deactivate':'Activate' ?></button>
        </form>
      </div></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
