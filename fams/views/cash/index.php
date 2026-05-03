<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div style="max-width: 1400px; margin: 0 auto;">
    <div class="card mb-3">
        <div class="card-title">🏘️ Village In-Charge (1.b) Financial Summary</div>
        <div class="table-wrap">
            <table class="table-card">
                <thead>
                    <tr>
                        <th>Village In-Charge</th>
                        <th>Cash in Hand<br><small class="text-muted">(Balance)</small></th>
                        <th>Awaiting Payments<br><small class="text-muted">(Authorized)</small></th>
                        <th>Approved<br><small class="text-muted">(Scheduled)</small></th>
                        <th>Net Req.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userSummary as $u): 
                        $net = ($u['authorized_amount'] + $u['pending_amount']) - $u['balance'];
                    ?>
                        <tr>
                            <td data-label="User">
                                <strong><?= e($u['full_name']) ?></strong>
                                <div class="text-muted" style="font-size: 0.8rem;">@<?= e($u['username']) ?></div>
                            </td>
                            <td data-label="Cash in Hand" style="font-weight: 600; color: var(--primary);">
                                <?= money($u['balance']) ?>
                            </td>
                            <td data-label="Awaiting Payments" style="color: var(--orange);">
                                <?= money($u['authorized_amount']) ?>
                            </td>
                            <td data-label="Approved" class="muted">
                                <?= money($u['pending_amount']) ?>
                            </td>
                            <td data-label="Net Requirement">
                                <?php if ($net > 0): ?>
                                    <span style="color: var(--red); font-weight: 600;">+ <?= money($net) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--success);">Surplus: <?= money(abs($net)) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-between align-center mb-2">
        <h2 style="font-size: 1.1rem; margin: 0;">📜 Transfer History</h2>
        <a href="index.php?page=cash.transfer" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Transfer
        </a>
    </div>

    <div class="card">
        <div class="table-wrap">
                <table class="table-card">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>From (1.c)</th>
                            <th>To (1.b)</th>
                            <th>Amount</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transfers)): ?>
                            <tr><td colspan="5" class="text-muted" style="text-align: center; padding: 2rem;">No transfers found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($transfers as $t): ?>
                                <tr>
                                    <td data-label="Date"><?= fdate($t['created_at'], 'd M Y H:i') ?></td>
                                    <td data-label="From"><?= e($t['from_name']) ?></td>
                                    <td data-label="To"><?= e($t['to_name']) ?></td>
                                    <td data-label="Amount" style="font-weight: 600; color: var(--success);">
                                        <?= money($t['amount']) ?>
                                    </td>
                                    <td data-label="Reference" class="muted"><?= e($t['reference']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= render_pagination($pagination) ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
