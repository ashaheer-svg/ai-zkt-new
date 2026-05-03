<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div style="max-width: 1400px; margin: 0 auto;">
    <div class="card mb-3">
        <div class="card-title">🏘️ Cash Holding & Financial Summary (1.b & 1.c)</div>
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
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    <span class="badge badge-gray"><?= e(role_label($u['role'])) ?></span>
                                    @<?= e($u['username']) ?>
                                </div>
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

    <div class="divider"></div>

    <div class="d-flex justify-between align-center mb-2 mt-3" style="flex-wrap: wrap; gap: 1rem;">
        <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text); margin: 0;">📜 Transfer History</h2>
        <a href="index.php?page=cash.transfer" class="btn btn-primary" style="padding: 0.75rem 1.5rem; box-shadow: 0 4px 12px var(--primary-glow);">
            <i class="fas fa-plus"></i> New Fund Transfer
        </a>
    </div>

    <!-- Filters -->
    <div class="card mb-2" style="padding: 1rem;">
        <form method="GET" action="index.php" class="filter-row-compact">
            <input type="hidden" name="page" value="cash.transfers">
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="start_date" value="<?= e($startDate) ?>" style="max-width: 150px;">
            </div>
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="end_date" value="<?= e($endDate) ?>" style="max-width: 150px;">
            </div>
            <div class="filter-group">
                <label>From</label>
                <select name="from">
                    <option value="">All Senders</option>
                    <?php foreach ($senders as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($_GET['from']??'') == $s['id'] ? 'selected' : '' ?>><?= e($s['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>To</label>
                <select name="to">
                    <option value="">All Recipients</option>
                    <?php foreach ($receivers as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= ($_GET['to']??'') == $r['id'] ? 'selected' : '' ?>><?= e($r['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-outline">🔍 Filter</button>
                <?php if (!empty($_GET['start_date']) || !empty($_GET['end_date']) || !empty($_GET['from']) || !empty($_GET['to'])): ?>
                    <a href="index.php?page=cash.transfers" class="btn btn-outline" title="Clear Filters">✕</a>
                <?php endif; ?>
            </div>
        </form>
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
