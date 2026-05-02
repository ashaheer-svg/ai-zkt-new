<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div class="card mb-2">
  <div class="card-title">💰 Disbursement Details</div>
  <div class="detail-grid">
    <div class="detail-item"><div class="detail-label">Applicant</div><div class="detail-value"><?= e($disb['applicant_name']) ?></div></div>
    <div class="detail-item"><div class="detail-label">Installment</div><div class="detail-value">#<?= $disb['installment_no'] ?></div></div>
    <div class="detail-item"><div class="detail-label">Amount</div><div class="detail-value"><strong style="font-size:1.2rem; color: var(--success);"><?= money($disb['amount']) ?></strong></div></div>
    <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value"><?= disb_badge($disb['status']) ?></div></div>
  </div>
</div>

<div class="card">
  <div class="card-title">💸 Release Payment</div>
  <p class="text-muted mb-2">Please provide the payment details to mark this installment as paid.</p>
  
  <form method="POST" action="index.php?page=disbursements.release&id=<?= $disb['id'] ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $disb['id'] ?>">
    
    <div class="form-grid">
        <div class="form-group">
            <label for="payment_method">Payment Method</label>
            <select name="payment_method" id="payment_method" required>
                <option value="">-- Select Method --</option>
                <option value="Cash">Cash</option>
                <option value="Cheque">Cheque</option>
                <option value="Transfer">Bank Transfer</option>
            </select>
        </div>

        <div class="form-group">
            <label for="payment_date">Payment Date</label>
            <input type="date" name="payment_date" id="payment_date" value="<?= date('Y-m-d') ?>" required>
        </div>
    </div>

    <div class="form-group mt-2">
        <label for="payment_reference">Reference (Cheque # / Trans ID)</label>
        <input type="text" name="payment_reference" id="payment_reference" placeholder="Optional reference…">
    </div>

    <div class="form-group mt-2 mb-3">
        <label for="notes">Internal Notes</label>
        <textarea name="notes" id="notes" placeholder="Any additional notes…"></textarea>
    </div>

    <div class="btn-group">
      <button type="submit" class="btn btn-success">💸 Confirm & Release Payment</button>
      <a href="index.php?page=disbursements&app_id=<?= $disb['app_id'] ?>" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
