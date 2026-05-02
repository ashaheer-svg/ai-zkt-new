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
<div class="card mb-1" style="padding: 0.75rem 1rem;">
  <form method="GET" action="index.php" class="filter-row-compact">
    <input type="hidden" name="page" value="disbursements">
    
    <div class="filter-group">
      <label>Village</label>
      <select name="village_id" class="form-control-sm">
        <option value="">All Villages</option>
        <?php foreach ($villages as $v): ?>
        <option value="<?= $v['id'] ?>" <?= (int)($_GET['village_id']??0)==$v['id']?'selected':'' ?>><?= e($v['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-group">
      <label>Period</label>
      <select name="period" class="form-control-sm">
        <option value="">All Time</option>
        <option value="month" <?= ($_GET['period']??'')=='month'?'selected':'' ?>>Month</option>
        <option value="quarter" <?= ($_GET['period']??'')=='quarter'?'selected':'' ?>>Quarter</option>
        <option value="year" <?= ($_GET['period']??'')=='year'?'selected':'' ?>>Year</option>
      </select>
    </div>

    <div class="filter-actions">
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="index.php?page=disbursements" class="btn btn-outline btn-sm">Reset</a>
    </div>
  </form>
</div>

<!-- Stats Cards -->
<?php if (isset($stats)): ?>
<div class="grid-stats mb-1">
  <div class="card stat-card-compact">
    <div class="stat-label-sm">Scheduled</div>
    <div class="stat-value-sm"><?= money($stats['total_scheduled'] ?? 0) ?></div>
  </div>
  <div class="card stat-card-compact border-green">
    <div class="stat-label-sm">Released</div>
    <div class="stat-value-sm text-green"><?= money($stats['total_released'] ?? 0) ?></div>
  </div>
  <div class="card stat-card-compact border-orange">
    <div class="stat-label-sm">Pending</div>
    <div class="stat-value-sm text-orange"><?= money(($stats['total_scheduled'] ?? 0) - ($stats['total_released'] ?? 0)) ?></div>
  </div>
</div>

<style>
.filter-row-compact { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
.filter-group { display: flex; align-items: center; gap: 0.5rem; }
.filter-group label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0; white-space: nowrap; }
.form-control-sm { padding: 4px 8px; font-size: 0.85rem; border: 1px solid #ddd; border-radius: 4px; background: #fff; }
.filter-actions { display: flex; gap: 0.5rem; }

.grid-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; }
.stat-card-compact { padding: 0.75rem; display: flex; flex-direction: column; align-items: center; border-left: 3px solid var(--primary); }
.stat-card-compact.border-green { border-left-color: var(--green); }
.stat-card-compact.border-orange { border-left-color: var(--orange); }
.stat-label-sm { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 2px; }
.stat-value-sm { font-size: 1.25rem; font-weight: 700; }

.table-compact th, .table-compact td { padding: 6px 10px !important; font-size: 0.85rem !important; }
.table-compact th { background: #f8f9fa; border-bottom: 2px solid #eee; }
</style>
<?php endif; ?>
<?php endif; ?>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table class="table-card table-compact">
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
