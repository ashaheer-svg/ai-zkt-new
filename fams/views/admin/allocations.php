<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div class="header-actions mb-2">
  <div class="header-title">
    <h1>💰 Village Allocations</h1>
    <p class="muted">Manage the total funds allocated to each village for Projects.</p>
  </div>
</div>

<div class="tabs mb-2">
  <div class="tab-item active" data-tab="villages">🏘️ Village Summary</div>
  <div class="tab-item" data-tab="users">👤 User Fund Summary</div>
</div>

<div id="tab-villages" class="tab-pane active">
  <div class="card" style="padding:0">
    <div class="table-wrap">
      <table class="table-card">
        <thead>
          <tr>
            <th>Village</th>
            <th>Total Allocation</th>
            <th>Released</th>
            <th>Committed</th>
            <th>Total Utilization</th>
            <th>Balance</th>
            <th style="width: 250px;">Update Limit</th>
          </tr>
        </thead>
        <tbody>
          <?php 
            $totalAllocated = 0;
            $totalReleased = 0;
            $totalCommitted = 0;
            foreach ($villages as $v): 
              $allocated = (float)$v['allocation_amount'];
              $released  = (float)$v['released_amount'];
              $committed = (float)$v['committed_amount'];
              $used      = $released + $committed;
              
              $totalAllocated += $allocated;
              $totalReleased  += $released;
              $totalCommitted += $committed;
              
              $remaining = $allocated - $used;
              $percent = $allocated > 0 ? ($used / $allocated) * 100 : 0;
              $color = 'green';
              if ($percent > 70) $color = 'orange';
              if ($percent > 90) $color = 'red';
          ?>
          <tr>
            <td data-label="Village">
              <strong><?= e($v['name']) ?></strong><br>
              <small class="muted"><?= e($v['district']) ?></small>
            </td>
            <td data-label="Total Allocation">
              <span class="text-large"><?= money($allocated) ?></span>
            </td>
            <td data-label="Released">
              <span class="text-green"><?= money($released) ?></span>
            </td>
            <td data-label="Committed">
              <span class="text-orange"><?= money($committed) ?></span>
            </td>
            <td data-label="Total Utilization">
              <span class="text-<?= $color ?>"><strong><?= money($used) ?></strong></span>
              <div style="width: 100px; height: 4px; background: #eee; border-radius: 2px; margin-top: 4px;">
                <div style="width: <?= min(100, $percent) ?>%; height: 100%; background: var(--<?= $color ?>); border-radius: 2px;"></div>
              </div>
            </td>
            <td data-label="Balance">
              <strong class="<?= $remaining < 0 ? 'text-red' : '' ?>"><?= money($remaining) ?></strong>
            </td>
            <td data-label="Update Limit">
              <form method="POST" action="index.php?page=admin.allocations" class="inline-edit-form">
                <?= csrf_field() ?>
                <input type="hidden" name="village_id" value="<?= $v['id'] ?>">
                <div class="input-group">
                  <input type="number" step="0.01" name="allocation_amount" value="<?= $allocated ?>" required style="width: 120px;">
                  <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background: rgba(0,0,0,0.02); font-weight: bold;">
            <td>TOTAL</td>
            <td><?= money($totalAllocated) ?></td>
            <td><?= money($totalReleased) ?></td>
            <td><?= money($totalCommitted) ?></td>
            <td><?= money($totalReleased + $totalCommitted) ?></td>
            <td><?= money($totalAllocated - ($totalReleased + $totalCommitted)) ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Pagination for Villages -->
  <?php if ($pagination['pages'] > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
    <a href="?page=admin.allocations&p=<?= $i ?>"
       class="page-btn <?= $i === $pagination['page'] ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<div id="tab-users" class="tab-pane">
  <div class="card" style="padding:0">
    <div class="table-wrap">
      <table class="table-card table-narrow-numeric">
        <thead>
          <tr>
            <th style="width: 35%;">User / Role</th>
            <th>Cash Received</th>
            <th>Cash Disbursed</th>
            <th>Pending Release</th>
            <th>Current Balance</th>
            <th style="width: 120px;">Utilization</th>
          </tr>
        </thead>
        <tbody>
          <?php 
            $sumReceived = 0; $sumPaid = 0; $sumPending = 0; $sumBalance = 0;
            foreach ($userFunds as $uf): 
              $received = (float)$uf['total_received'];
              $paid     = (float)$uf['total_disbursed'];
              $pending  = (float)$uf['total_pending_release'];
              $balance  = (float)$uf['balance'];
              
              $sumReceived += $received; $sumPaid += $paid; $sumPending += $pending; $sumBalance += $balance;

              $uPercent = $received > 0 ? ($paid / $received) * 100 : 0;
              $uColor = 'green';
              if ($uPercent > 80) $uColor = 'orange';
              if ($uPercent > 95) $uColor = 'red';
          ?>
          <tr>
            <td data-label="User / Role">
              <strong><?= e($uf['full_name']) ?></strong><br>
              <small class="muted" style="font-size: 0.85em; display: block; line-height: 1.2; margin-bottom: 4px;"><?= e($uf['assigned_villages'] ?: 'No Villages') ?></small>
              <small class="badge badge-outline"><?= role_label($uf['role']) ?></small>
            </td>
            <td data-label="Cash Received"><?= money($received) ?></td>
            <td data-label="Cash Disbursed">
              <span class="text-green"><?= money($paid) ?></span>
            </td>
            <td data-label="Pending Release">
              <span class="text-orange"><?= money($pending) ?></span>
            </td>
            <td data-label="Current Balance">
              <strong><?= money($balance) ?></strong>
            </td>
            <td data-label="Utilization">
              <span class="text-<?= $uColor ?>"><strong><?= round($uPercent, 1) ?>%</strong></span>
              <div style="width: 100px; height: 4px; background: #eee; border-radius: 2px; margin-top: 4px;">
                <div style="width: <?= min(100, $uPercent) ?>%; height: 100%; background: var(--<?= $uColor ?>); border-radius: 2px;"></div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background: rgba(0,0,0,0.02); font-weight: bold;">
            <td>TOTAL</td>
            <td><?= money($sumReceived) ?></td>
            <td class="text-green"><?= money($sumPaid) ?></td>
            <td class="text-orange"><?= money($sumPending) ?></td>
            <td><?= money($sumBalance) ?></td>
            <td>
              <?php $totalPercent = $sumReceived > 0 ? ($sumPaid / $sumReceived) * 100 : 0; ?>
              <?= round($totalPercent, 1) ?>%
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <?php if ($staffTransfers): ?>
  <div class="card mt-2">
    <div class="card-title">📉 Staff Float Distribution (Level 1.c → 1.b)</div>
    <div class="table-wrap">
      <table class="table-card">
        <thead>
          <tr>
            <th>From (Manager)</th>
            <th>To (Field Staff)</th>
            <th class="text-right">Total Transferred</th>
          </tr>
        </thead>
        <tbody>
          <?php 
            $currentManager = '';
            $sumTransferred = 0;
            foreach ($staffTransfers as $st): 
              $sumTransferred += (float)$st['total_amount'];
          ?>
          <tr>
            <td data-label="From">
              <?php if ($st['from_name'] !== $currentManager): ?>
                <strong><?= e($st['from_name']) ?></strong>
                <?php $currentManager = $st['from_name']; ?>
              <?php else: ?>
                <span class="muted" style="padding-left: 10px;">↳</span>
              <?php endif; ?>
            </td>
            <td data-label="To"><?= e($st['to_name']) ?></td>
            <td data-label="Amount" class="text-right"><strong><?= money($st['total_amount']) ?></strong></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background: rgba(0,0,0,0.02); font-weight: bold;">
            <td colspan="2">TOTAL DISTRIBUTED</td>
            <td class="text-right"><?= money($sumTransferred) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.tab-item').forEach(item => {
  item.addEventListener('click', () => {
    document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    
    item.classList.add('active');
    document.getElementById('tab-' + item.dataset.tab).classList.add('active');
  });
});
</script>

<style>
.tabs { display: flex; gap: 20px; border-bottom: 1px solid #eee; margin-bottom: 20px; }
.tab-item { padding: 10px 5px; cursor: pointer; color: #666; border-bottom: 2px solid transparent; font-weight: 500; transition: all 0.2s; }
.tab-item:hover { color: var(--primary); }
.tab-item.active { color: var(--primary); border-bottom-color: var(--primary); }
.tab-pane { display: none; }
.tab-pane.active { display: block; }

.input-group { display: flex; gap: 4px; }
.input-group input { padding: 4px 8px; font-size: 14px; border: 1px solid #ddd; border-radius: 4px; }
.text-green { color: var(--green); }
.text-orange { color: var(--orange); }
.text-red { color: var(--red); }

.table-narrow-numeric td:not(:first-child):not(:last-child),
.table-narrow-numeric th:not(:first-child):not(:last-child) {
  width: 1%;
  white-space: nowrap;
}
</style>

<?php require __DIR__ . '/../layout/footer.php'; ?>
