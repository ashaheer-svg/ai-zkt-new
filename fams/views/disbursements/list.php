<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<?php if ($app): ?>
<div class="d-flex align-center gap-2 mb-2">
  <span class="text-muted text-small">Application #<?= $app['id'] ?> —</span>
  <strong><?= e($app['applicant_name']) ?></strong>
  <?= status_badge($app['status']) ?>
  <?php if ($auth->hasRole(ROLE_OVERALL_INCHARGE) && $app['status']===STATUS_APPROVED): ?>
  <a href="index.php?page=disbursements.schedule&app_id=<?= $app['id'] ?>" class="btn btn-primary btn-sm">⚙️ Set Schedule</a>
  <?php endif; ?>
</div>
<?php else: ?>
<!-- Filters for Global View -->
<div class="card mb-2">
  <form method="GET" action="index.php" class="filter-row">
    <input type="hidden" name="page" value="disbursements">
    
    <div class="filter-item">
      <label>Village</label>
      <select name="village_id">
        <option value="">All Villages</option>
        <?php foreach ($villages as $v): ?>
        <option value="<?= $v['id'] ?>" <?= (int)($_GET['village_id']??0)==$v['id']?'selected':'' ?>><?= e($v['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-item">
      <label>Period</label>
      <select name="period">
        <option value="">All Time</option>
        <option value="month" <?= ($_GET['period']??'')=='month'?'selected':'' ?>>Current Month</option>
        <option value="quarter" <?= ($_GET['period']??'')=='quarter'?'selected':'' ?>>Current Quarter</option>
        <option value="year" <?= ($_GET['period']??'')=='year'?'selected':'' ?>>Current Year</option>
      </select>
    </div>

    <div class="filter-item d-flex align-end">
      <button type="submit" class="btn btn-primary">Filter</button>
      <a href="index.php?page=disbursements" class="btn btn-outline" style="margin-left:5px">Reset</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table class="table-card">
      <thead><tr>
        <th>#</th>
        <?php if (!$app): ?>
        <th><?= sort_link('Application', 'app_id') ?></th>
        <th><?= sort_link('Village', 'village_name') ?></th>
        <?php endif; ?>
        <th><?= sort_link('Due Date', 'due_date') ?></th>
        <th><?= sort_link('Amount', 'amount') ?></th>
        <th><?= sort_link('Status', 'status') ?></th>
        <th>Authorized By</th>
        <th>Auth Date</th>
        <th>Notes</th>
        <th>Action</th>
      </tr></thead>
      <tbody>
        <?php if (empty($disbursements)): ?>
        <tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:2rem">No disbursements found.</td></tr>
        <?php endif; ?>
        <?php foreach ($disbursements as $d): ?>
        <tr>
          <td data-label="#"><?= $d['installment_no'] ?></td>
          <?php if (!$app): ?>
          <td data-label="Application"><a href="index.php?page=applications.view&id=<?= $d['app_id'] ?>">#<?= $d['app_id'] ?> — <?= e($d['applicant_name']) ?></a></td>
          <td data-label="Village"><?= e($d['village_name']) ?></td>
          <?php endif; ?>
          <td data-label="Due"><?= $d['due_date'] ? fdate($d['due_date']) : '—' ?></td>
          <td data-label="Amount"><strong><?= money($d['amount']) ?></strong></td>
          <td data-label="Status"><?= disb_badge($d['status']) ?></td>
          <td data-label="Auth By" class="muted"><?= e($d['auth_name'] ?? '—') ?></td>
          <td data-label="Auth Date" class="muted"><?= $d['authorized_at'] ? fdate($d['authorized_at']) : '—' ?></td>
          <td data-label="Notes" class="muted"><?= e($d['notes']?:'—') ?></td>
          <td data-label="Action">
            <?php if ($d['status']===DISB_PENDING && $auth->hasRole(ROLE_OVERALL_INCHARGE)): ?>
            <a href="index.php?page=disbursements.authorize&id=<?= $d['id'] ?>" class="btn btn-warning btn-sm">Authorize</a>
            <?php elseif ($d['status']===DISB_AUTHORIZED && $auth->hasRole(ROLE_OVERALL_INCHARGE)): ?>
            <form method="POST" action="index.php?page=disbursements.release" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= $d['id'] ?>">
              <button type="submit" class="btn btn-success btn-sm" data-confirm="Mark installment #<?= $d['installment_no'] ?> as released?">Release</button>
            </form>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (!$app && isset($pagination)): ?>
<div class="pagination-container mt-2">
  <?= render_pagination($pagination) ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
