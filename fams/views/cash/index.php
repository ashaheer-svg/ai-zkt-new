<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="page-header">
    <div class="d-flex align-center" style="justify-content: space-between; width: 100%;">
        <h1 class="page-title"><?= e($pageTitle) ?></h1>
        <a href="index.php?page=cash.transfer" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Transfer
        </a>
    </div>
</div>

<div class="content-area">
    <?= flash_html() ?>

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

<?php require __DIR__ . '/../layout/footer.php'; ?>
