<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div class="card mb-2">
  <div class="card-title">👤 Applicant Summary</div>
  <div class="detail-grid">
    <div class="detail-item"><div class="detail-label">Full Name</div><div class="detail-value"><?= e($applicant['full_name']) ?></div></div>
    <div class="detail-item"><div class="detail-label">Gender</div><div class="detail-value"><?= ucfirst($applicant['gender']) ?></div></div>
    <div class="detail-item"><div class="detail-label">Age</div><div class="detail-value"><?= $applicant['age']?:'—' ?></div></div>
    <div class="detail-item"><div class="detail-label">ID / NIC</div><div class="detail-value"><?= e($applicant['id_number']?:'—') ?></div></div>
    <div class="detail-item"><div class="detail-label">Telephone</div><div class="detail-value"><?= e($applicant['telephone']?:'—') ?></div></div>
    <div class="detail-item"><div class="detail-label">Amount Requested</div><div class="detail-value"><strong><?= money($app['amount_requested']) ?></strong></div></div>
  </div>
</div>

<div class="card">
  <div class="card-title">🔍 Validation Decision</div>
  <div class="alert alert-info mb-2">You are validating an application submitted by another user. Your decision will determine if it enters the main workflow.</div>
  <form method="POST" action="index.php?page=applications.validate&id=<?= $app['id'] ?>">
    <?= csrf_field() ?>
    <div class="form-group mb-2">
      <label>Decision *</label>
      <select name="decision" required>
        <option value="approve">✅ Validate — Allow into workflow</option>
        <option value="reject">❌ Reject — Return as invalid</option>
      </select>
    </div>
    <div class="form-group mb-2">
      <label>Comment</label>
      <textarea name="comment" placeholder="Add your validation notes…"></textarea>
    </div>
    <div class="btn-group">
      <button type="submit" class="btn btn-primary">Submit Decision</button>
      <a href="index.php?page=applications.pending" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
