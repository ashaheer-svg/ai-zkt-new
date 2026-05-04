<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div class="card mb-2" style="padding: 1.25rem;">
    <div class="card-title">🏘️ Village & Staffing Coverage</div>
    <p class="text-muted mb-0">Overview of all active villages and their assigned field personnel (Data Entry 1.a and Village In-Charge 1.b). Villages highlighted in red indicate a lack of assigned staff.</p>
</div>

<div class="card" style="padding:0">
    <div class="table-wrap">
        <table class="table-card">
            <thead>
                <tr>
                    <th style="width: 15%;">District</th>
                    <th style="width: 25%;">Village / Thackiya</th>
                    <th>Assigned Staff (1.a / 1.b)</th>
                    <th style="width: 15%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($villages as $v): 
                    $hasStaff = !empty($v['staff']);
                    $rowClass = $hasStaff ? '' : 'bg-warning-soft';
                ?>
                <tr class="<?= $rowClass ?>">
                    <td data-label="District">
                        <strong><?= e($v['district'] ?: '—') ?></strong>
                    </td>
                    <td data-label="Village">
                        <strong><?= e($v['name']) ?></strong>
                    </td>
                    <td data-label="Staff">
                        <?php if ($hasStaff): ?>
                            <div class="staff-list">
                                <?php foreach ($v['staff'] as $s): ?>
                                    <div class="staff-item mb-1">
                                        <strong><?= e($s['name']) ?></strong>
                                        <span class="text-muted ml-1" style="font-size: 0.85em;">(<?= role_label($s['role']) ?>)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="text-danger"><strong>⚠️ No staff assigned</strong></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Status">
                        <?php if ($hasStaff): ?>
                            <span class="badge badge-green">Operational</span>
                        <?php else: ?>
                            <span class="badge badge-red">Coverage Gap</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.bg-warning-soft { background-color: rgba(245, 158, 11, 0.05); }
.staff-item { display: block; margin-bottom: 4px; }
.ml-1 { margin-left: 0.5rem; }
tr.bg-warning-soft:hover { background-color: rgba(245, 158, 11, 0.1); }
</style>

<?php require __DIR__ . '/../layout/footer.php'; ?>
