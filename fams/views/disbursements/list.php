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
<?php endif; ?>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table class="table-card">
      <thead><tr>
        <th>#</th>
        <?php if (!$app): ?><th>Application</th><th>Village</th><?php endif; ?>
        <th>Due Date</th><th>Amount</th><th>Status</th><th>Authorized By</th><th>Auth Date</th><th>Notes</th><th>Action</th>
      </tr></thead>
      <tbody>
        <?php if (empty($disbursements)): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:2rem">No disbursements found.</td></tr>
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

<?php require __DIR__ . '/../layout/footer.php'; ?>
