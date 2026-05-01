<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<!-- Filters -->
<form method="GET" action="index.php" class="toolbar mb-2">
  <input type="hidden" name="page" value="admin.audit">
  <div class="toolbar-search">
    <input type="text" name="user" value="<?= e($_GET['user']??'') ?>" placeholder="Filter by user…">
    <input type="text" name="action_filter" value="<?= e($_GET['action_filter']??'') ?>" placeholder="Filter by action…">
    <input type="date" name="date" value="<?= e($_GET['date']??'') ?>">
    <button type="submit" class="btn btn-outline">Filter</button>
    <a href="index.php?page=admin.audit" class="btn btn-outline">Clear</a>
  </div>
</form>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table class="table-card">
      <thead><tr><th>Date/Time</th><th>User</th><th>Role</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
      <tbody>
        <?php if (empty($result['rows'])): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem">No log entries found.</td></tr>
        <?php endif; ?>
        <?php foreach ($result['rows'] as $log): ?>
        <tr>
          <td data-label="Date" class="muted"><?= fdate($log['created_at'],'d M Y H:i:s') ?></td>
          <td data-label="User"><?= e($log['full_name'] ?? 'System') ?></td>
          <td data-label="Role"><span class="badge badge-blue"><?= role_label($log['role'] ?? '') ?></span></td>
          <td data-label="Action"><code><?= e(str_replace('_',' ',$log['action'])) ?></code></td>
          <td data-label="Entity" class="muted">
            <?php if ($log['entity_type'] && $log['entity_id']): ?>
            <?= e($log['entity_type']) ?> #<?= $log['entity_id'] ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td data-label="IP" class="muted"><?= e($log['ip_address'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($result['pages'] > 1): ?>
<div class="pagination">
  <?php for ($i=1;$i<=$result['pages'];$i++): ?>
  <a href="?page=admin.audit&p=<?= $i ?>&user=<?= urlencode($_GET['user']??'') ?>&action_filter=<?= urlencode($_GET['action_filter']??'') ?>&date=<?= urlencode($_GET['date']??'') ?>"
     class="page-btn <?= $i===$result['page']?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
