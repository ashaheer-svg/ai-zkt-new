<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<p class="text-muted mb-2">Applications from your assigned villages awaiting peer validation. You cannot validate your own submissions.</p>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table class="table-card">
      <thead><tr><th>#</th><th>Applicant</th><th>Village</th><th>Category</th><th>Amount</th><th>Submitted By</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
        <?php if (empty($result['rows'])): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem">No applications pending validation.</td></tr>
        <?php endif; ?>
        <?php foreach ($result['rows'] as $a): ?>
        <tr>
          <td data-label="#"><?= $a['id'] ?></td>
          <td data-label="Applicant"><strong><?= e($a['applicant_name']) ?></strong></td>
          <td data-label="Village"><?= e($a['village_name']) ?></td>
          <td data-label="Category"><?= e($a['category_name']) ?></td>
          <td data-label="Amount"><?= money($a['amount_requested']) ?></td>
          <td data-label="Submitted By" class="muted"><?= e($a['creator_name']) ?></td>
          <td data-label="Date" class="muted"><?= fdate($a['created_at']) ?></td>
          <td data-label="Action">
            <a href="index.php?page=applications.validate&id=<?= $a['id'] ?>" class="btn btn-primary btn-sm">Validate</a>
            <a href="index.php?page=applications.view&id=<?= $a['id'] ?>" class="btn btn-outline btn-sm">View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($result['pages'] > 1): ?>
<div class="pagination">
  <?php for ($i=1;$i<=$result['pages'];$i++): ?>
  <a href="?page=applications.pending&p=<?= $i ?>" class="page-btn <?= $i===$result['page']?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
