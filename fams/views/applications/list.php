<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<!-- Toolbar -->
<div class="toolbar">
  <form class="toolbar-search" method="GET" action="index.php">
    <input type="hidden" name="page" value="applications">
    <input type="text" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Search name, ID, phone…">
    <select name="status">
      <option value="">All Statuses</option>
      <?php foreach (STATUS_LABELS as $k => $lbl): ?>
      <option value="<?= e($k) ?>" <?= (($_GET['status']??'')===$k)?'selected':'' ?>><?= e($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Filter</button>
    <?php if (!empty($_GET['search']) || !empty($_GET['status'])): ?>
    <a href="index.php?page=applications" class="btn btn-outline">Clear</a>
    <?php endif; ?>
  </form>

  <div class="d-flex gap-1">
    <?php if ($auth->hasRole([ROLE_DATA_ENTRY,ROLE_VILLAGE_INCHARGE,ROLE_OVERALL_INCHARGE,ROLE_SYSADMIN])): ?>
    <a href="index.php?page=applications.create" class="btn btn-primary">+ New Application</a>
    <?php endif; ?>

    <?php if ($auth->hasRole([ROLE_OVERALL_INCHARGE,ROLE_SYSADMIN])): ?>
    <?php if (isset($_GET['show_all'])): ?>
    <a href="index.php?page=applications" class="btn btn-outline">Hide Pre-Validation</a>
    <?php else: ?>
    <a href="index.php?page=applications&show_all=1" class="btn btn-outline">Show Pipeline</a>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Table -->
<div class="card" style="padding:0">
  <div class="table-wrap">
    <table class="table-card">
      <thead>
        <tr>
          <th>#</th><th>Applicant</th><th>Village</th><th>Category</th>
          <th>Amount</th><th>Status</th><th>Created By</th><th>Date</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($result['rows'])): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:2rem">No applications found.</td></tr>
        <?php endif; ?>
        <?php foreach ($result['rows'] as $a): ?>
        <tr class="<?= in_array($a['status'],[STATUS_DRAFT,STATUS_PENDING_VALIDATION]) ? 'draft-row' : '' ?>">
          <td data-label="#"><?= $a['id'] ?><?= $a['is_privileged'] ? ' <span class="privilege-tag">🔒 Priv</span>' : '' ?></td>
          <td data-label="Applicant"><strong><?= e($a['applicant_name']) ?></strong><br><span class="text-small text-muted"><?= e($a['id_number'] ?? '') ?></span></td>
          <td data-label="Village"><?= e($a['village_name']) ?></td>
          <td data-label="Category"><?= e($a['category_name']) ?></td>
          <td data-label="Amount"><?= money($a['amount_requested']) ?></td>
          <td data-label="Status"><?= status_badge($a['status']) ?></td>
          <td data-label="Created By" class="muted"><?= e($a['creator_name']) ?></td>
          <td data-label="Date" class="muted"><?= fdate($a['created_at']) ?></td>
          <td data-label="Action">
            <a href="index.php?page=applications.view&id=<?= $a['id'] ?>" class="btn btn-outline btn-sm">View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($result['pages'] > 1): ?>
<div class="pagination">
  <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
  <a href="?page=applications&p=<?= $i ?>&search=<?= urlencode($_GET['search']??'') ?>&status=<?= urlencode($_GET['status']??'') ?>"
     class="page-btn <?= $i === $result['page'] ? 'active' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<style>.draft-row td { opacity:.65; }</style>

<?php require __DIR__ . '/../layout/footer.php'; ?>
