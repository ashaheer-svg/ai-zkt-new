<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<?php $action = $_GET['action'] ?? ''; $editItem = null;
if ($action==='edit' && isset($_GET['id'])) { $s=$pdo->prepare("SELECT * FROM villages WHERE id=?");$s->execute([(int)$_GET['id']]);$editItem=$s->fetch(); } ?>

<div class="card mb-2">
  <div class="card-title"><?= $editItem ? '✏️ Edit Village' : '➕ New Village' ?></div>
  <form method="POST" action="index.php?page=admin.villages&action=<?= $editItem?'edit':'create' ?>">
    <?= csrf_field() ?>
    <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="form-group"><label>Village Name *</label><input type="text" name="name" value="<?= e($editItem['name']??'') ?>" required></div>
      <div class="form-group"><label>District</label><input type="text" name="district" value="<?= e($editItem['district']??'') ?>" placeholder="District / Region"></div>
    </div>
    <div class="btn-group mt-2">
      <button type="submit" class="btn btn-primary"><?= $editItem?'💾 Update':'➕ Add Village' ?></button>
      <?php if ($editItem): ?><a href="index.php?page=admin.villages" class="btn btn-outline">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card" style="padding:0"><div class="table-wrap"><table class="table-card">
  <thead><tr><th>Village</th><th>District</th><th>Applicants</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
    <?php foreach ($villages as $v): ?>
    <tr>
      <td data-label="Village"><strong><?= e($v['name']) ?></strong></td>
      <td data-label="District" class="muted"><?= e($v['district']?:'—') ?></td>
      <td data-label="Applicants"><?= $v['applicant_count'] ?></td>
      <td data-label="Status"><?= $v['is_active']?'<span class="badge badge-green">Active</span>':'<span class="badge badge-red">Inactive</span>' ?></td>
      <td data-label="Actions"><div class="btn-group">
        <a href="index.php?page=admin.villages&action=edit&id=<?= $v['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
        <form method="POST" action="index.php?page=admin.villages&action=toggle" style="display:inline">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $v['id'] ?>">
          <button class="btn <?= $v['is_active']?'btn-danger':'btn-success' ?> btn-sm" data-confirm="Toggle village status?"><?= $v['is_active']?'Deactivate':'Activate' ?></button>
        </form>
      </div></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
