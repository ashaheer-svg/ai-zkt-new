<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div class="header-actions mb-2">
  <div class="header-title">
    <h1>💰 Village Allocations</h1>
    <p class="muted">Manage the total funds allocated to each village for Projects.</p>
  </div>
</div>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table class="table-card">
      <thead>
        <tr>
          <th>Village</th>
          <th>Total Allocation</th>
          <th>Used Amount</th>
          <th>Remaining Balance</th>
          <th style="width: 250px;">Update Limit</th>
        </tr>
      </thead>
      <tbody>
        <?php 
          $totalAllocated = 0;
          $totalUsed = 0;
          foreach ($villages as $v): 
            $allocated = (float)$v['allocation_amount'];
            $used = (float)$v['used_amount'];
            $totalAllocated += $allocated;
            $totalUsed += $used;
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
          <td data-label="Used Amount">
            <span class="text-<?= $color ?>"><?= money($used) ?></span>
            <div style="width: 100px; height: 4px; background: #eee; border-radius: 2px; margin-top: 4px;">
              <div style="width: <?= min(100, $percent) ?>%; height: 100%; background: var(--<?= $color ?>); border-radius: 2px;"></div>
            </div>
          </td>
          <td data-label="Remaining Balance">
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
          <td><?= money($totalUsed) ?></td>
          <td><?= money($totalAllocated - $totalUsed) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<style>
.input-group { display: flex; gap: 4px; }
.input-group input { padding: 4px 8px; font-size: 14px; border: 1px solid #ddd; border-radius: 4px; }
.text-green { color: var(--green); }
.text-orange { color: var(--orange); }
.text-red { color: var(--red); }
</style>

<?php require __DIR__ . '/../layout/footer.php'; ?>
