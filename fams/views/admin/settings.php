<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<div class="header-main">
  <div class="header-title">
    <h1>⚙️ System Settings</h1>
    <p>Manage global application configurations, security flags, and mobile access.</p>
  </div>
</div>

<?= flash_html() ?>

<div class="grid-2">
  <!-- General Settings -->
  <form method="POST" action="index.php?page=admin.settings&sub_action=general" class="card">
    <?= csrf_field() ?>
    <div class="card-title">🌐 Localization & Environment</div>
    
    <div class="form-group mb-2">
      <label>Application Timezone</label>
      <select name="timezone" class="select2" required>
        <?php foreach ($timezones as $tz): ?>
        <option value="<?= e($tz) ?>" <?= ($settings['timezone'] ?? '') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group mb-2">
      <label class="d-flex align-center gap-1">
        <input type="checkbox" name="debug_mode" value="1" <?= ($settings['debug_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
        Enable Debug Mode
      </label>
      <p class="text-tiny text-danger mt-1">
        <strong>⚠️ WARNING:</strong> Debug Mode displays detailed errors. Disable in production.
      </p>
    </div>

    <div class="btn-group">
      <button type="submit" class="btn btn-primary">💾 Save General Settings</button>
    </div>
  </form>

  <!-- Document Types -->
  <div class="card">
    <div class="card-title">📄 Document Types</div>
    <form method="POST" action="index.php?page=admin.settings&sub_action=doc_type_add" class="d-flex gap-1 mb-2">
      <?= csrf_field() ?>
      <input type="text" name="name" placeholder="New Type (e.g. ID Copy)" required class="form-control">
      <button type="submit" class="btn btn-success">Add</button>
    </form>
    
    <div class="table-wrap">
      <table class="table-card">
        <thead><tr><th>Type Name</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($docTypes as $dt): ?>
          <tr>
            <td><?= e($dt['name']) ?></td>
            <td><?= $dt['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>' ?></td>
            <td>
              <form method="POST" action="index.php?page=admin.settings&sub_action=doc_type_toggle" style="display:inline">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $dt['id'] ?>">
                <button type="submit" class="btn btn-outline btn-xs"><?= $dt['is_active']?'Deactivate':'Activate' ?></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="grid-2 mt-2">
  <!-- Mobile Access QR -->
  <div class="card">
    <div class="card-title">📱 Mobile API Access</div>
    <p class="text-small muted mb-2">Generate a 365-day access token for a mobile device. Pair by scanning the QR code.</p>
    
    <form method="POST" action="index.php?page=admin.settings&sub_action=generate_token" class="d-flex gap-1 mb-2">
      <?= csrf_field() ?>
      <select name="user_id" class="form-control" required>
        <option value="">-- Select User to Pair --</option>
        <?php foreach ($users as $u): ?>
        <option value="<?= $u['id'] ?>"><?= e($u['full_name']) ?> (<?= $u['role'] ?>)</option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary">Generate QR</button>
    </form>

    <div class="table-wrap">
      <table class="table-card">
        <thead><tr><th>User</th><th>Expires</th><th>Last Used</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($tokens as $tk): ?>
          <tr>
            <td><?= e($tk['full_name']) ?></td>
            <td class="text-tiny"><?= date('Y-m-d', strtotime($tk['expires_at'])) ?></td>
            <td class="text-tiny"><?= $tk['last_used_at'] ? fdate($tk['last_used_at']) : 'Never' ?></td>
            <td>
              <button class="btn btn-warning btn-xs" onclick="showQR('<?= e($tk['full_name']) ?>', '<?= $tk['token'] ?>')">👁️ Show QR</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Advanced Maintenance -->
  <div class="card border-danger">
    <div class="card-title text-danger">⚠️ Advanced Maintenance</div>
    <div class="mb-2">
      <h3>Full System Backup</h3>
      <p class="text-tiny muted mb-1">ZIP of all code and the database for migration.</p>
      <a href="index.php?page=admin.full_backup" class="btn btn-outline btn-sm">📦 Download ZIP</a>
    </div>
    <hr class="my-1">
    <div>
      <h3 class="text-danger">Factory Reset</h3>
      <p class="text-tiny muted mb-1">Delete ALL data. Irreversible.</p>
      <button onclick="document.getElementById('resetModal').style.display='flex'" class="btn btn-danger btn-sm">🔥 Reset All Data</button>
    </div>
  </div>
</div>

<!-- QR Modal -->
<div id="qrModal" class="modal-overlay" style="display:none">
  <div class="modal-content" style="max-width: 450px; text-align: center;">
    <div class="modal-header">
      <h3 id="qrUser">Mobile Access</h3>
      <button onclick="document.getElementById('qrModal').style.display='none'" class="close-btn">&times;</button>
    </div>
    <div class="modal-body">
      <div id="qrcode" style="margin: 0 auto; padding: 1rem; background: white; display: inline-block;"></div>
      <p class="text-small muted mt-2">Scan this code in the <strong>NCT FAMS Mobile App</strong> to sign in.</p>
      <div class="alert alert-info text-tiny mt-1" style="word-break: break-all;" id="configRaw"></div>
    </div>
  </div>
</div>

<!-- Reset Modal -->
<div id="resetModal" class="modal-overlay" style="display:none">
  <div class="modal-content" style="max-width: 450px;">
    <div class="modal-header">
      <h3 class="text-danger">🔥 Critical: Factory Reset</h3>
      <button onclick="document.getElementById('resetModal').style.display='none'" class="close-btn">&times;</button>
    </div>
    <form method="POST" action="index.php?page=admin.reset" class="modal-body">
      <?= csrf_field() ?>
      <div class="alert alert-danger mb-2">
        <strong>⚠️ This action is irreversible!</strong>
        <p class="text-small mt-1">The following data will be permanently deleted:</p>
        <ul class="text-tiny mt-1" style="list-style: disc; padding-left: 1.5rem;">
          <li>All <strong>Applicants</strong> and their Personal Details</li>
          <li>All <strong>Applications</strong> and their Status History</li>
          <li>All <strong>Disbursements</strong> and Payment Records</li>
          <li>All <strong>Documents</strong> and Uploaded Files</li>
          <li>All <strong>Village Scoping</strong> and Assignments</li>
          <li>All <strong>Activity Logs</strong> and System Audit Trails</li>
          <li>All <strong>Mobile API Tokens</strong></li>
        </ul>
        <p class="text-small mt-1"><em>Settings and your SysAdmin account will be preserved.</em></p>
      </div>
      
      <p class="text-small mb-1">To confirm this catastrophic action, please type <strong>RESET</strong> in the box below:</p>
      <input type="text" name="confirm_reset" placeholder="Type RESET to confirm" required class="form-control mb-2" autocomplete="off" style="border-color: var(--danger);">
      
      <div class="btn-group">
        <button type="submit" class="btn btn-danger w-100">I Understand - Wipe Everything</button>
        <button type="button" onclick="document.getElementById('resetModal').style.display='none'" class="btn btn-outline w-100">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function showQR(name, token) {
    const baseUrl = window.location.origin + window.location.pathname;
    const config = {
        api_url: baseUrl,
        token: token
    };
    const configStr = JSON.stringify(config);
    
    document.getElementById('qrUser').innerText = 'Access for: ' + name;
    document.getElementById('configRaw').innerText = configStr;
    document.getElementById('qrcode').innerHTML = '';
    
    new QRCode(document.getElementById("qrcode"), {
        text: configStr,
        width: 256,
        height: 256
    });
    
    document.getElementById('qrModal').style.display = 'flex';
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
