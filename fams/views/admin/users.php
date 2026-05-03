<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<?php
$action = $_GET['action'] ?? '';
$editUser = null;
if ($action === 'edit' && isset($_GET['uid'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([(int)$_GET['uid']]); $editUser = $stmt->fetch();
    $stmt2 = $pdo->prepare("SELECT village_id FROM user_villages WHERE user_id=?"); $stmt2->execute([(int)$_GET['uid']]); $editUserVillages = $stmt2->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!-- Create / Edit Form -->
<div class="card mb-2">
  <div class="card-title"><?= $editUser ? '✏️ Edit User' : '➕ New User' ?></div>
  <form method="POST" action="index.php?page=admin.users&action=<?= $editUser ? 'edit' : 'create' ?>">
    <?= csrf_field() ?>
    <?php if ($editUser): ?><input type="hidden" name="user_id" value="<?= $editUser['id'] ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="full_name" value="<?= e($editUser['full_name'] ?? '') ?>" required placeholder="Full name">
      </div>
      <div class="form-group">
        <label>Username <?= !$editUser ? '*' : '(read-only)' ?></label>
        <input type="text" name="username" value="<?= e($editUser['username'] ?? '') ?>" <?= $editUser?'readonly':'' ?> required placeholder="username">
      </div>
      <div class="form-group">
        <label><?= $editUser ? 'New Password (leave blank to keep)' : 'Password *' ?></label>
        <input type="password" name="password" <?= !$editUser?'required':'' ?> placeholder="Password">
      </div>
      <div class="form-group">
        <label>Role *</label>
        <select name="role" required id="roleSelect">
          <?php foreach (ROLE_LABELS as $k => $lbl): ?>
          <option value="<?= $k ?>" <?= (($editUser['role']??'')===$k)?'selected':'' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group full" id="villageField">
        <label>Assigned Villages (hold Ctrl/Cmd to select multiple)</label>
        <select name="villages[]" multiple size="5">
          <?php foreach ($allVillages as $v): ?>
          <option value="<?= $v['id'] ?>" <?= in_array($v['id'], $editUserVillages ?? [])?'selected':'' ?>><?= e($v['name']) ?> <?= $v['district']?'('.$v['district'].')':'' ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">Required for data_entry, village_incharge, verification roles.</div>
      </div>
    </div>
    <div class="btn-group mt-2">
      <button type="submit" class="btn btn-primary"><?= $editUser ? '💾 Update' : '➕ Create User' ?></button>
      <?php if ($editUser): ?><a href="index.php?page=admin.users" class="btn btn-outline">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<!-- User List -->
<div class="toolbar">
  <form class="toolbar-search" method="GET" action="index.php">
    <input type="hidden" name="page" value="admin.users">
    <input type="text" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Search users…">
    <button type="submit" class="btn btn-outline">Search</button>
  </form>
</div>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table class="table-card">
      <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Villages</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td data-label="Name"><?= e($u['full_name']) ?></td>
          <td data-label="Username"><code><?= e($u['username']) ?></code></td>
          <td data-label="Role"><span class="badge badge-blue"><?= role_label($u['role']) ?></span></td>
          <td data-label="Villages" class="muted"><?= implode(', ', array_map('e', $uvMap[$u['id']] ?? ['—'])) ?></td>
          <td data-label="Status"><?= $u['is_active'] ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-red">Inactive</span>' ?></td>
          <td data-label="Created" class="muted"><?= fdate($u['created_at']) ?></td>
          <td data-label="Actions">
            <div class="btn-group">
              <a href="index.php?page=admin.users&action=edit&uid=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
              <?php if ($u['id'] != $auth->id()): ?>
              <form method="POST" action="index.php?page=admin.users&action=toggle" style="display:inline">
                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn <?= $u['is_active']?'btn-danger':'btn-success' ?> btn-sm" data-confirm="<?= $u['is_active']?'Deactivate':'Activate' ?> this user?">
                  <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<!-- Pagination -->
<?php if ($pagination['pages'] > 1): ?>
<div class="pagination">
  <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
  <a href="?page=admin.users&p=<?= $i ?>&search=<?= urlencode($_GET['search']??'') ?>"
     class="page-btn <?= $i === $pagination['page'] ? 'active' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<script>
// Hide village field for global roles
const roleSelect = document.getElementById('roleSelect');
const villageField = document.getElementById('villageField');
function toggleVillage() {
  const globalRoles = ['overall_incharge','sysadmin'];
  villageField.style.display = globalRoles.includes(roleSelect.value) ? 'none' : '';
}
roleSelect && roleSelect.addEventListener('change', toggleVillage);
toggleVillage();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
