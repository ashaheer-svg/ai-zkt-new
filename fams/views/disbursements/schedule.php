<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div class="card mb-2">
  <div class="card-title">ℹ️ Application</div>
  <div class="detail-grid">
    <div class="detail-item"><div class="detail-label">Applicant</div><div class="detail-value"><?= e($app['applicant_name']) ?></div></div>
    <div class="detail-item"><div class="detail-label">Amount Requested</div><div class="detail-value"><strong><?= money($app['amount_requested']) ?></strong></div></div>
    <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value"><?= status_badge($app['status']) ?></div></div>
  </div>
</div>

<div class="card">
  <div class="card-title">⚙️ Create Disbursement Schedule</div>
  <?php if ($errors): ?><div class="alert alert-error"><?= implode('<br>',array_map('e',$errors)) ?></div><?php endif; ?>

  <form method="POST" action="index.php?page=disbursements.schedule&app_id=<?= $appId ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-group">
        <label>Disbursement Type *</label>
        <select name="disbursement_type" required>
          <option value="">Select type…</option>
          <?php foreach (DISB_LABELS as $k=>$lbl): ?>
          <option value="<?= $k ?>" <?= (($_POST['disbursement_type']??'')===$k)?'selected':'' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Amount Per Installment *</label>
        <input type="number" name="disbursement_amount" step="0.01" min="0.01"
               value="<?= e($_POST['disbursement_amount'] ?? $app['amount_requested']) ?>" required>
      </div>
      <div class="form-group">
        <label>Number of Installments *</label>
        <input type="number" name="disbursement_count" min="1" max="120"
               value="<?= e($_POST['disbursement_count'] ?? 1) ?>" required>
        <div class="form-hint">Use 1 for one-time payments.</div>
      </div>
      <div class="form-group">
        <label>First Payment Date *</label>
        <input type="date" name="disbursement_start_date"
               value="<?= e($_POST['disbursement_start_date'] ?? date('Y-m-d')) ?>" required>
      </div>
    </div>
    <div class="btn-group mt-2">
      <button type="submit" class="btn btn-primary">💾 Create Schedule</button>
      <a href="index.php?page=applications.view&id=<?= $appId ?>" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
