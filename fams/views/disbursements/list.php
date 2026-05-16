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
  <div class="card stat-card-compact border-success">
    <div class="stat-label-sm">Released</div>
    <div class="stat-value-sm text-success"><?= money($stats['total_released'] ?? 0) ?></div>
  </div>
  <div class="card stat-card-compact border-warning">
    <div class="stat-label-sm">Pending</div>
    <div class="stat-value-sm text-warning"><?= money(($stats['total_scheduled'] ?? 0) - ($stats['total_released'] ?? 0)) ?></div>
  </div>
</div>

<!-- Modal for Comments -->
<div id="commentModal" class="modal-overlay" style="display:none">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="modalTitle">Disbursement Notes</h3>
      <button onclick="closeCommentModal()" class="close-btn">&times;</button>
    </div>
    <div class="modal-body" id="modalBody"></div>
    <div class="modal-footer">
      <button onclick="closeCommentModal()" class="btn btn-outline">Close</button>
    </div>
  </div>
</div>

<script>
function showComment(title, text, disbId) {
    document.getElementById('modalTitle').innerText = title;
    const body = document.getElementById('modalBody');
    body.innerHTML = '';

    // Build translatable wrapper
    const wrap = document.createElement('div');
    wrap.className = 'translatable-wrap';
    if (disbId) {
        wrap.setAttribute('data-translatable', '');
        wrap.setAttribute('data-table', 'disbursements');
        wrap.setAttribute('data-record-id', disbId);
        wrap.setAttribute('data-field', 'notes');
        wrap.setAttribute('data-lang', 'ta'); // default; field notes may be any lang
    }
    const p = document.createElement('p');
    p.setAttribute('data-source-text', '');
    p.style.whiteSpace = 'pre-wrap';
    p.textContent = text;
    wrap.appendChild(p);
    body.appendChild(wrap);

    document.getElementById('commentModal').style.display = 'flex';
    // Re-run translation init for the dynamically injected content
    if (disbId && typeof initTranslation === 'function') initTranslation();
}
function closeCommentModal() {
    document.getElementById('commentModal').style.display = 'none';
}
window.onclick = function(event) {
    if (event.target == document.getElementById('commentModal')) closeCommentModal();
}
</script>

<style>
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; }
.modal-content { background: #fff; width: 90%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); overflow: hidden; }
.modal-header { padding: 1rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; }
.modal-header h3 { margin: 0; font-size: 1.1rem; }
.close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #999; }
.modal-body { padding: 1.5rem; font-size: 1rem; line-height: 1.6; white-space: pre-wrap; color: #444; }
.modal-footer { padding: 0.75rem 1rem; border-top: 1px solid #eee; text-align: right; }

.filter-row-compact { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
.filter-group { display: flex; align-items: center; gap: 0.5rem; }
.filter-group label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0; white-space: nowrap; }
.form-control-sm { padding: 4px 8px; font-size: 0.85rem; border: 1px solid #ddd; border-radius: 4px; background: #fff; color: #333 !important; }
.filter-actions { display: flex; gap: 0.5rem; }

.grid-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; }
.stat-card-compact { padding: 0.75rem; display: flex; flex-direction: column; align-items: center; border-left: 3px solid var(--primary); }
.stat-card-compact.border-green { border-left-color: var(--green); }
.stat-card-compact.border-orange { border-left-color: var(--orange); }
.stat-label-sm { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 2px; }
.stat-value-sm { font-size: 1.25rem; font-weight: 700; }

.table-compact th, .table-compact td { padding: 6px 10px !important; font-size: 0.85rem !important; }
.table-compact th { background: #f8f9fa; border-bottom: 2px solid #eee; }

.note-link { color: var(--primary); cursor: pointer; text-decoration: underline; font-size: 0.8rem; }
.note-link:hover { color: var(--primary-dark); }
</style>
<?php endif; ?>
<?php endif; ?>

<form id="bulkForm" method="POST" action="index.php?page=disbursements.bulk_authorize">
  <?= csrf_field() ?>
  <div class="d-flex align-center gap-2 mb-1" style="justify-content: space-between;">
    <div>
      <?php if ($auth->hasRole(ROLE_OVERALL_INCHARGE)): ?>
      <button type="submit" class="btn btn-warning btn-sm" id="bulkAuthBtn" disabled>
        Authorize Selected
      </button>
      <?php endif; ?>
    </div>
    <div class="text-muted text-small" id="selectedCount">0 items selected</div>
  </div>
  <div class="table-wrap">
    <table class="table-card table-compact">
      <thead><tr>
        <?php if ($auth->hasRole(ROLE_OVERALL_INCHARGE)): ?>
        <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
        <?php endif; ?>
        <th>#</th>
        <?php if (!$app): ?>
        <th><?= sort_link('Application', 'app_id') ?></th>
        <th><?= sort_link('Village', 'village_name') ?></th>
        <?php endif; ?>
        <th><?= sort_link('Due Date', 'due_date') ?></th>
        <th class="text-right"><?= sort_link('Amount', 'amount') ?></th>
        <th><?= sort_link('Status', 'status') ?></th>
        <th>Assigned To</th>
        <th>Auth By</th>
        <th>Notes</th>
        <th>Action</th>
      </tr></thead>
      <tbody>
        <?php if (empty($disbursements)): ?>
        <tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:2rem">No disbursements found.</td></tr>
        <?php endif; ?>
        <?php foreach ($disbursements as $d): ?>
        <tr>
          <?php if ($auth->hasRole(ROLE_OVERALL_INCHARGE)): ?>
          <td>
            <?php if ($d['status'] === DISB_PENDING): ?>
            <input type="checkbox" name="disb_ids[]" value="<?= $d['id'] ?>" class="disb-checkbox">
            <?php endif; ?>
          </td>
          <?php endif; ?>
          <td data-label="#"><?= $d['installment_no'] ?></td>
          <?php if (!$app): ?>
          <td data-label="Application"><a href="index.php?page=applications.view&id=<?= $d['app_id'] ?>">#<?= $d['app_id'] ?> — <?= e($d['applicant_name']) ?></a></td>
          <td data-label="Village"><?= e($d['village_name']) ?><br><span class="text-tiny muted"><?= e($d['village_district'] ?: '') ?></span></td>
          <?php endif; ?>
          <td data-label="Due"><?= $d['due_date'] ? fdate($d['due_date']) : '—' ?></td>
          <td data-label="Amount" class="text-right"><strong><?= money($d['amount']) ?></strong></td>
          <td data-label="Status"><?= disb_badge($d['status']) ?></td>
          <td data-label="Assigned" class="muted">
            <?= e($d['assigned_name'] ?? '—') ?>
          </td>
          <td data-label="Auth By" class="muted">
            <?= e($d['auth_name'] ?? '—') ?>
            <div style="font-size: 0.7rem;"><?= $d['authorized_at'] ? fdate($d['authorized_at']) : '' ?></div>
          </td>
          <td data-label="Notes" class="muted">
            <?php if ($d['notes']): ?>
              <span class="note-link" onclick="showComment('Notes: Installment #<?= $d['installment_no'] ?>', '<?= e(addslashes($d['notes'])) ?>', <?= $d['id'] ?>)">View Note</span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td data-label="Action">
            <?php if ($d['status']===DISB_PENDING && $auth->hasRole(ROLE_OVERALL_INCHARGE)): ?>
            <a href="index.php?page=disbursements.authorize&id=<?= $d['id'] ?>" class="btn btn-warning btn-sm">Authorize</a>
            <?php elseif ($d['status']===DISB_AUTHORIZED && ($auth->hasRole(ROLE_OVERALL_INCHARGE) || $auth->id() == $d['assigned_to'])): ?>
            <a href="index.php?page=disbursements.release&id=<?= $d['id'] ?>" class="btn btn-success btn-sm">Release</a>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</form>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
  const checkboxes = document.querySelectorAll('.disb-checkbox');
  checkboxes.forEach(cb => cb.checked = this.checked);
  updateSelectedCount();
});

document.querySelectorAll('.disb-checkbox').forEach(cb => {
  cb.addEventListener('change', updateSelectedCount);
});

function updateSelectedCount() {
  const checked = document.querySelectorAll('.disb-checkbox:checked').length;
  document.getElementById('selectedCount').innerText = checked + ' items selected';
  document.getElementById('bulkAuthBtn').disabled = checked === 0;
}
</script>

<?php if (!$app && isset($pagination)): ?>
<div class="pagination-container mt-2">
  <?= render_pagination($pagination) ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
