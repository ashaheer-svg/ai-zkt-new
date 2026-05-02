<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form method="POST" action="index.php?page=cash.transfer">
            <?= csrf_field() ?>

            <div class="form-group mb-2">
                <label for="to_user_id">Select Village In-Charge (1.b)</label>
                <select name="to_user_id" id="to_user_id" required>
                    <option value="">-- Select User --</option>
                    <?php foreach ($villageIncharges as $u): ?>
                        <option value="<?= $u['id'] ?>">
                            <?= e($u['full_name']) ?> (<?= e($u['username']) ?>) — Current Bal: <?= money($u['balance']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint">Only active 1.b level users are listed.</p>
            </div>

            <div class="form-group mb-2">
                <label for="amount">Amount to Transfer</label>
                <input type="number" name="amount" id="amount" step="0.01" min="0.01" required placeholder="0.00">
            </div>

            <div class="form-group mb-3">
                <label for="reference">Reference / Notes</label>
                <textarea name="reference" id="reference" rows="2" placeholder="e.g. Monthly allocation for May"></textarea>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Complete Transfer</button>
                <a href="index.php?page=cash.transfers" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
