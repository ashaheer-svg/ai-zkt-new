<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div class="card mb-2">
  <div class="card-title">💰 Disbursement Details</div>
  <div class="detail-grid">
    <div class="detail-item"><div class="detail-label">Applicant</div><div class="detail-value"><?= e($disb['applicant_name']) ?></div></div>
    <div class="detail-item"><div class="detail-label">Installment</div><div class="detail-value">#<?= $disb['installment_no'] ?></div></div>
    <div class="detail-item"><div class="detail-label">Due Date</div><div class="detail-value"><?= $disb['due_date'] ? fdate($disb['due_date']) : '—' ?></div></div>
    <div class="detail-item"><div class="detail-label">Amount</div><div class="detail-value"><strong style="font-size:1.2rem"><?= money($disb['amount']) ?></strong></div></div>
    <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value"><?= disb_badge($disb['status']) ?></div></div>
  </div>
</div>

<div class="card">
  <div class="card-title">✅ Authorize Disbursement</div>
  <div class="alert alert-info mb-2">Authorizing confirms this installment is approved for release. You can add a comment for audit purposes.</div>
  <form method="POST" action="index.php?page=disbursements.authorize&id=<?= $disb['id'] ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $disb['id'] ?>">
    <div class="form-group mb-2">
      <label>Authorization Comment</label>
      <textarea name="comment" placeholder="Authorization notes (optional)…"></textarea>
    </div>
    <div class="btn-group">
      <button type="submit" class="btn btn-warning">✅ Authorize Payment</button>
      <a href="index.php?page=disbursements&app_id=<?= $disb['application_id'] ?>" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
