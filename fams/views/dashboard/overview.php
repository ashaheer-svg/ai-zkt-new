<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<!-- 1.b Specific Section -->
<?php if ($auth->role() === ROLE_VILLAGE_INCHARGE): ?>
<div class="card mb-2" style="background: linear-gradient(135deg, var(--surface), var(--card)); border-left: 5px solid var(--primary);">
    <div class="d-flex align-center" style="justify-content: space-between;">
        <div>
            <div class="stat-label">My Available Balance</div>
            <div class="stat-value" style="font-size: 2rem; color: var(--primary);"><?= money($myBalance) ?></div>
            <div class="stat-sub">Funds allocated for village distribution</div>
        </div>
        <div style="font-size: 3rem; opacity: 0.2;">💰</div>
    </div>
</div>

<div class="card mb-2">
    <div class="card-title">📥 Pending Payment Instructions</div>
    <?php if ($myInstructions): ?>
    <div class="table-wrap">
        <table class="table-card">
            <thead>
                <tr>
                    <th>Applicant</th><th>Village</th><th>Installment</th><th>Amount</th><th>Due Date</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($myInstructions as $inst): ?>
                <tr>
                    <td data-label="Applicant"><?= e($inst['applicant_name']) ?></td>
                    <td data-label="Village"><?= e($inst['village_name']) ?></td>
                    <td data-label="Installment">#<?= $inst['installment_no'] ?></td>
                    <td data-label="Amount"><strong><?= money($inst['amount']) ?></strong></td>
                    <td data-label="Due"><?= fdate($inst['due_date']) ?></td>
                    <td data-label="Action">
                        <a href="index.php?page=disbursements.release&id=<?= $inst['id'] ?>" class="btn btn-success btn-sm">Mark as Paid</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-muted" style="padding: 1rem; text-align: center;">No pending payment instructions assigned to you.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 1.c / Admin Section -->
<?php if ($auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Stat cards -->
<div class="card-grid mb-2">
  <?php
  $allStatuses = [STATUS_SUBMITTED,STATUS_UNDER_REVIEW,STATUS_APPROVED,STATUS_DISBURSING,STATUS_COMPLETED,STATUS_REJECTED];
  $icons = ['📥','🔍','✅','💰','🏁','❌'];
  foreach ($allStatuses as $i => $s):
    $cnt = $statusCounts[$s] ?? 0;
  ?>
  <div class="stat-card">
    <div class="stat-label"><?= $icons[$i] ?> <?= STATUS_LABELS[$s] ?></div>
    <div class="stat-value"><?= $cnt ?></div>
    <a href="index.php?page=applications&status=<?= $s ?>" class="text-small text-muted">View →</a>
  </div>
  <?php endforeach; ?>

  <div class="stat-card">
    <div class="stat-label">💵 Total Disbursed</div>
    <div class="stat-value" style="font-size:1.4rem"><?= money($totalDisbursed) ?></div>
    <div class="stat-sub">Released payments</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">🔒 Authorized (pending release)</div>
    <div class="stat-value" style="font-size:1.4rem"><?= money($totalAuthorized) ?></div>
    <div class="stat-sub">Next 90-day liability</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">📅 90-Day Cash Flow</div>
    <div class="stat-value" style="font-size:1.4rem"><?= money($cashFlow90) ?></div>
    <div class="stat-sub">Upcoming pending disbursements</div>
  </div>
</div>

<!-- Charts row -->
<div style="display:grid;grid-template-columns:1fr 2fr;gap:1rem;margin-bottom:1.25rem">
  <div class="card">
    <div class="card-title">📊 Application Status</div>
    <?php
    $chartLabels = [];
    $chartValues = [];
    foreach ($allStatuses as $s) {
      $chartLabels[] = STATUS_LABELS[$s];
      $chartValues[] = $statusCounts[$s] ?? 0;
    }
    ?>
    <canvas id="statusChart" height="220"
      data-labels='<?= json_encode($chartLabels) ?>'
      data-values='<?= json_encode($chartValues) ?>'></canvas>
  </div>
  <div class="card">
    <div class="card-title">📅 Upcoming Disbursements (90 days)</div>
    <?php
    $cfLabels = []; $cfValues = [];
    foreach ($upcomingDisbursements as $d) {
      $lbl = $d['due_date'] ? fdate($d['due_date'],'M d') : 'No Date';
      $cfLabels[] = $lbl; $cfValues[] = $d['amount'];
    }
    ?>
    <canvas id="cashflowChart" height="220"
      data-labels='<?= json_encode(array_slice($cfLabels,0,12)) ?>'
      data-values='<?= json_encode(array_slice($cfValues,0,12)) ?>'></canvas>
  </div>
</div>

<!-- Upcoming disbursements table -->
<div class="card mb-2">
  <div class="card-title">💰 Pending Disbursement Schedule</div>
  <?php if ($upcomingDisbursements): ?>
  <div class="table-wrap">
    <table class="table-card">
      <thead>
        <tr>
          <th>Applicant</th><th>Village</th><th>Category</th>
          <th>Installment</th><th>Due Date</th><th>Amount</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($upcomingDisbursements as $d): ?>
        <tr>
          <td data-label="Applicant"><?= e($d['applicant_name']) ?></td>
          <td data-label="Village"><?= e($d['village_name']) ?></td>
          <td data-label="Category"><?= e($d['category_name']) ?></td>
          <td data-label="Installment">#<?= $d['installment_no'] ?></td>
          <td data-label="Due Date"><?= $d['due_date'] ? fdate($d['due_date']) : '—' ?></td>
          <td data-label="Amount"><strong><?= money($d['amount']) ?></strong></td>
          <td data-label="Action">
            <a href="index.php?page=disbursements.authorize&id=<?= $d['id'] ?>" class="btn btn-warning btn-sm">Authorize</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <p class="text-muted text-small">No pending disbursements in the next 90 days.</p>
  <?php endif; ?>
</div>

<!-- Village summary -->
<div class="card mb-2">
  <div class="card-title">🏘️ Village Summary</div>
  <div class="table-wrap">
    <table class="table-card">
      <thead><tr><th>Village</th><th>Total Apps</th><th>Approved</th><th>Disbursed</th></tr></thead>
      <tbody>
        <?php foreach ($villageSummary as $v): ?>
        <tr>
          <td data-label="Village"><?= e($v['village']) ?></td>
          <td data-label="Total"><?= $v['total'] ?></td>
          <td data-label="Approved"><?= $v['approved'] ?></td>
          <td data-label="Disbursed"><strong><?= money($v['disbursed']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Recent activity -->
<div class="card">
  <div class="card-title">🕒 Recent Activity</div>
  <?php if ($recentActivity): ?>
  <div class="timeline">
    <?php foreach ($recentActivity as $a): ?>
    <div class="timeline-item">
      <div class="timeline-dot"></div>
      <div class="timeline-meta"><?= fdate($a['created_at'],'d M Y H:i') ?> — <?= e($a['full_name'] ?? 'System') ?> (<?= role_label($a['role'] ?? '') ?>)</div>
      <div class="timeline-action"><?= e(str_replace('_',' ',$a['action'])) ?></div>
      <?php if ($a['entity_type'] && $a['entity_id']): ?>
      <div class="timeline-comment"><?= e($a['entity_type']) ?> #<?= $a['entity_id'] ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="text-muted text-small">No activity yet.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
