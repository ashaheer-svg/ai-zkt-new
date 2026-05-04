<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<!-- Status banner -->
<div class="d-flex align-center gap-2 mb-2" style="flex-wrap:wrap">
  <?= status_badge($app['status']) ?>
  <span class="badge badge-outline"><?= e($app['category_name'] ?? 'Uncategorized') ?></span>
  <?php if ($app['is_privileged']): ?><span class="privilege-tag">🔒 Privileged</span><?php endif; ?>
  <span class="text-small text-muted">Created <?= fdate($app['created_at']) ?></span>
  <span class="text-small text-muted">Updated <?= fdate($app['updated_at']) ?></span>
</div>

<!-- Action bar -->
<div class="btn-group mb-2">
  <?php if ($auth->canEditApplication($app)): ?>
  <a href="index.php?page=applications.edit&id=<?= $app['id'] ?>" class="btn btn-outline">✏️ Edit</a>
  <?php endif; ?>

  <?php if ($app['status'] === STATUS_SUBMITTED && $auth->hasRole(ROLE_VILLAGE_INCHARGE) && $auth->isInVillage((int)$app['village_id'])): ?>
  <button onclick="document.getElementById('reviewPanel').style.display='block'" class="btn btn-primary">🔍 Review</button>
  <?php endif; ?>

  <?php if (in_array($app['status'], [STATUS_SUBMITTED, STATUS_UNDER_REVIEW]) && $auth->hasRole(ROLE_OVERALL_INCHARGE)): ?>
  <button onclick="document.getElementById('approvePanel').style.display='block'" class="btn btn-success">✅ Approve</button>
  <?php endif; ?>

  <?php if (in_array($app['status'],[STATUS_SUBMITTED,STATUS_UNDER_REVIEW]) && $auth->hasRole([ROLE_VILLAGE_INCHARGE,ROLE_OVERALL_INCHARGE])): ?>
  <button onclick="document.getElementById('rejectPanel').style.display='block'" class="btn btn-danger">❌ Reject</button>
  <?php endif; ?>

  <?php if ($auth->hasRole([ROLE_OVERALL_INCHARGE,ROLE_SYSADMIN])): ?>
  <form method="POST" action="index.php?page=applications.privilege" style="display:inline">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $app['id'] ?>">
    <button type="submit" class="btn btn-warning" data-confirm="<?= $app['is_privileged'] ? 'Remove privileged flag?' : 'Mark as privileged? It will be hidden from other roles.' ?>">
      <?= $app['is_privileged'] ? '🔓 Un-Privilege' : '🔒 Mark Privileged' ?>
    </button>
  </form>
  <?php endif; ?>

  <?php if ($app['status'] === STATUS_DISBURSING): ?>
  <a href="index.php?page=disbursements&app_id=<?= $app['id'] ?>" class="btn btn-outline">💰 Disbursements</a>
  <?php endif; ?>

  <?php if ($auth->hasRole([ROLE_OVERALL_INCHARGE,ROLE_SYSADMIN])): ?>
  <button onclick="document.getElementById('revertPanel').style.display='block'" class="btn btn-outline">↩️ Push Back</button>
  <?php if ($app['status'] === STATUS_ON_HOLD): ?>
  <button onclick="document.getElementById('unholdPanel').style.display='block'" class="btn btn-success">▶️ Cancel Hold</button>
  <?php elseif ($app['is_valid']): ?>
  <button onclick="document.getElementById('holdPanel').style.display='block'" class="btn btn-warning">⏸️ Put On Hold</button>
  <?php endif; ?>
  <?php if ($app['status'] === STATUS_DISBURSING): ?>
  <button onclick="document.getElementById('adjustPanel').style.display='block'" class="btn btn-warning">⚙️ Adjust Schedule</button>
  <?php endif; ?>
  <?php endif; ?>

  <?php 
    $canComment = $auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN, ROLE_VERIFICATION]) 
                  || ($app['created_by'] == $auth->id()) 
                  || ($app['validated_by'] == $auth->id());
    if ($canComment): 
  ?>
  <button onclick="document.getElementById('commentPanel').style.display='block'" class="btn btn-outline">💬 Add Note</button>
  <?php endif; ?>
</div>

<!-- Inline action panels -->
<?php foreach ([
    'reviewPanel'=>['Review Application','decision','review'],
    'rejectPanel'=>['Reject Application','reject','reject'],
    'revertPanel'=>['Push Back to Unvalidated','comment','revert'],
    'holdPanel'=>['Put Application ON HOLD','comment','hold'],
    'unholdPanel'=>['Cancel HOLD Status','comment','unhold'],
    'commentPanel'=>['Add Note / Comment','comment','comment']
  ] as $panelId => [$title,$field,$actionPage]): ?>
<div id="<?= $panelId ?>" style="display:none" class="panel mb-2">
  <div class="panel-title"><?= $title ?></div>
  <form method="POST" action="index.php?page=applications.<?= $actionPage ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $app['id'] ?>">
    <?php if ($panelId === 'reviewPanel'): ?>
    <div class="form-group mb-1">
      <label>Decision</label>
      <select name="decision" required>
        <option value="approve">Forward for Final Approval</option>
        <option value="reject">Reject</option>
      </select>
    </div>
    <?php endif; ?>
    <?php if ($panelId === 'commentPanel'): ?>
    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
    <?php endif; ?>
    <div class="form-group mb-1">
      <label>Comment <?= $panelId==='rejectPanel'?'(required)':'' ?></label>
      <textarea name="comment" placeholder="Add your comment…"></textarea>
    </div>
    <div class="btn-group">
      <button type="submit" class="btn btn-primary">Submit</button>
      <button type="button" onclick="document.getElementById('<?= $panelId ?>').style.display='none'" class="btn btn-outline">Cancel</button>
    </div>
  </form>
</div>
<?php endforeach; ?>

<!-- Special Approval Panel for 1.c -->
<div id="approvePanel" style="display:none" class="panel mb-2">
  <div class="panel-title">✅ Approve Application & Set Disbursement Guidelines</div>
  <form method="POST" action="index.php?page=applications.approve">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $app['id'] ?>">
    <div class="form-grid mb-1">
      <div class="form-group">
        <label>Disbursement Type</label>
        <select name="disbursement_type" required>
          <?php foreach (DISB_LABELS as $v => $l): ?>
          <option value="<?= $v ?>" <?= ($app['requested_type']??'')==$v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Amount Per Installment</label>
        <input type="number" step="0.01" name="disbursement_amount" value="<?= e($app['requested_installment'] ?? $app['amount_requested']) ?>" required>
      </div>
      <div class="form-group">
        <label>No. of Installments (Qty)</label>
        <input type="number" name="disbursement_count" value="<?= e($app['requested_count'] ?? 1) ?>" required min="1">
      </div>
      <div class="form-group">
        <label>Start Date</label>
        <input type="date" name="disbursement_start_date" value="<?= date('Y-m-d') ?>" required>
      </div>
    </div>
    <div class="form-group mb-1">
      <label>Approval Notes / Additional Context</label>
      <textarea name="comment" placeholder="Add additional notes…"></textarea>
    </div>
    <div class="btn-group">
      <button type="submit" class="btn btn-success">✅ Approve & Schedule</button>
      <button type="button" onclick="document.getElementById('approvePanel').style.display='none'" class="btn btn-outline">Cancel</button>
    </div>
  </form>
</div>

<!-- Adjustment Panel for 1.c (Rescheduling) -->
<div id="adjustPanel" style="display:none" class="panel mb-2 border-warning">
  <div class="panel-title">⚙️ Adjust Disbursement Schedule</div>
  <p class="text-small text-muted mb-1">Adjusting the schedule will only affect <strong>future (Pending/Authorized)</strong> payments. Payments already marked as 'Released' will remain unchanged.</p>
  <form method="POST" action="index.php?page=applications.adjust">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $app['id'] ?>">
    <div class="form-grid mb-1">
      <div class="form-group">
        <label>New Disbursement Type</label>
        <select name="disbursement_type" required>
          <?php foreach (DISB_LABELS as $v => $l): ?>
          <option value="<?= $v ?>" <?= ($app['disbursement_type']??'')==$v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>New Amount Per Installment</label>
        <input type="number" step="0.01" name="disbursement_amount" value="<?= e($app['disbursement_amount']) ?>" required>
      </div>
      <div class="form-group">
        <label>New Remaining No. of Installments</label>
        <input type="number" name="disbursement_count" value="1" required min="1">
        <span class="text-tiny muted">This will add X new installments.</span>
      </div>
      <div class="form-group">
        <label>Next Payment Date</label>
        <input type="date" name="disbursement_start_date" value="<?= date('Y-m-d') ?>" required>
      </div>
    </div>
    <div class="form-group mb-1">
      <label>Reason for Adjustment (required for logs)</label>
      <textarea name="comment" required placeholder="e.g. Applicant requested change, or partial payment already made elsewhere…"></textarea>
    </div>
    <div class="btn-group">
      <button type="submit" class="btn btn-warning">⚙️ Update Schedule</button>
      <button type="button" onclick="document.getElementById('adjustPanel').style.display='none'" class="btn btn-outline">Cancel</button>
    </div>
  </form>
</div>

<!-- Tabs -->
<div class="tabs">
  <button class="tab-btn active" data-tab="tab-details">Details</button>
  <button class="tab-btn" data-tab="tab-docs">Documents (<?= count($documents) ?>)</button>
  <button class="tab-btn" data-tab="tab-timeline">Timeline</button>
  <?php if ($disbursements): ?><button class="tab-btn" data-tab="tab-disb">Disbursements</button><?php endif; ?>
  <?php if ($auth->hasRole([ROLE_OVERALL_INCHARGE,ROLE_SYSADMIN]) && $editHistory): ?><button class="tab-btn" data-tab="tab-edits">Edit History</button><?php endif; ?>
</div>

<!-- Tab: Details -->
<div id="tab-details" class="tab-pane active">
  <div class="card mb-2">
    <div class="card-title">👤 Applicant</div>
    <div class="detail-grid">
      <div class="detail-item"><div class="detail-label">Full Name</div><div class="detail-value"><?= e($applicant['full_name']) ?></div></div>
      <div class="detail-item"><div class="detail-label">Gender</div><div class="detail-value"><?= ucfirst(e($applicant['gender'])) ?></div></div>
      <div class="detail-item"><div class="detail-label">Age</div><div class="detail-value"><?= $applicant['age'] ?: '—' ?></div></div>
      <div class="detail-item"><div class="detail-label">ID / NIC</div><div class="detail-value"><?= e($applicant['id_number'] ?: '—') ?></div></div>
      <div class="detail-item"><div class="detail-label">Mobile Phone</div><div class="detail-value"><?= e($applicant['telephone'] ?: '—') ?></div></div>
      <div class="detail-item"><div class="detail-label">Home Phone</div><div class="detail-value"><?= e($applicant['telephone_home'] ?: '—') ?></div></div>
      <div class="detail-item"><div class="detail-label">Marital Status</div><div class="detail-value"><?= ucfirst(e($applicant['marital_status'] ?: '—')) ?></div></div>
      <div class="detail-item"><div class="detail-label">Residency</div><div class="detail-value"><?= ucfirst(e($applicant['residency_status'] ?: '—')) ?></div></div>
      <div class="detail-item"><div class="detail-label">Occupation</div><div class="detail-value"><?= e($applicant['occupation'] ?: '—') ?></div></div>
      <div class="detail-item"><div class="detail-label">Employer Details</div><div class="detail-value"><?= e($applicant['employer_details'] ?: '—') ?></div></div>
      <div class="detail-item full"><div class="detail-label">Address</div><div class="detail-value"><?= e($applicant['address'] ?: '—') ?></div></div>
      <?php if ($applicant['notes']): ?>
      <div class="detail-item full">
        <div class="detail-label">Internal Notes</div>
        <div class="detail-value"><?= e($applicant['notes']) ?></div>
        <div class="text-tiny muted mt-1">Recorded by <?= e($app['creator_name'] ?? 'System') ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($dependants): ?>
  <div class="card mb-2">
    <div class="card-title">👥 Dependants (<?= count($dependants) ?>)</div>
    <div class="table-wrap"><table class="table-card"><thead><tr><th>Name</th><th>Relationship</th><th>Age</th><th>Gender</th><th>Occupation</th><th>Income</th></tr></thead><tbody>
      <?php foreach ($dependants as $c): ?>
      <tr>
        <td data-label="Name"><?= e($c['full_name']) ?></td>
        <td data-label="Relationship"><span class="badge badge-outline"><?= ucfirst(e($c['relationship']?:'—')) ?></span></td>
        <td data-label="Age"><?= $c['age']?:'—' ?></td>
        <td data-label="Gender"><?= ucfirst($c['gender']?:'—') ?></td>
        <td data-label="Occupation"><?= e($c['occupation']?:'—') ?></td>
        <td data-label="Income" class="text-right"><?= $c['income'] > 0 ? money($c['income']) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <?php endif; ?>

  <div class="card mb-2">
    <div class="card-title">📝 Original Request</div>
    <div class="detail-grid">
      <div class="detail-item"><div class="detail-label">Village / Thackiya</div><div class="detail-value"><?= e($app['village_name']) ?> (<?= e($app['village_district'] ?: '—') ?>)</div></div>
      <div class="detail-item"><div class="detail-label">Category</div><div class="detail-value"><?= e($app['category_name'] ?? '—') ?></div></div>
      <div class="detail-item"><div class="detail-label">Requested Schedule</div><div class="detail-value"><?= DISB_LABELS[$app['requested_type']??''] ?? 'One Time' ?></div></div>
      <div class="detail-item"><div class="detail-label">Installment Amount</div><div class="detail-value"><?= money($app['requested_installment'] ?? $app['amount_requested']) ?></div></div>
      <div class="detail-item"><div class="detail-label">Quantity</div><div class="detail-value"><?= $app['requested_count'] ?? 1 ?></div></div>
      <div class="detail-item"><div class="detail-label">Total Amount Requested</div><div class="detail-value"><strong><?= money($app['amount_requested']) ?></strong></div></div>
      <div class="detail-item"><div class="detail-label">Expected Date</div><div class="detail-value"><?= fdate($app['expected_date']) ?></div></div>
      <div class="detail-item"><div class="detail-label">Other Fund Applications</div><div class="detail-value"><?= ucfirst(e($app['applied_other_funds'] ?: 'no')) ?></div></div>
      <div class="detail-item full">
        <div class="detail-label">Reason for Application</div>
        <div class="detail-value" style="white-space:pre-wrap"><?= e($app['reason_for_application'] ?: '—') ?></div>
        <div class="text-tiny muted mt-1">Submitted by <?= e($app['creator_name'] ?? 'System') ?> on <?= fdate($app['created_at']) ?></div>
      </div>
    </div>
  </div>

  <?php if ($app['disbursement_type']): ?>
  <div class="card border-success">
    <div class="card-title text-success">💰 Approved Disbursement</div>
    <div class="detail-grid">
      <div class="detail-item"><div class="detail-label">Approved Type</div><div class="detail-value"><?= DISB_LABELS[$app['disbursement_type']] ?? $app['disbursement_type'] ?></div></div>
      <div class="detail-item"><div class="detail-label">Per Installment</div><div class="detail-value"><?= money($app['disbursement_amount']) ?></div></div>
      <div class="detail-item"><div class="detail-label">Total Installments</div><div class="detail-value"><?= $app['disbursement_count'] ?></div></div>
      <div class="detail-item"><div class="detail-label">Total Approved Amount</div><div class="detail-value"><strong style="color:var(--success-color); font-size:1.1rem"><?= money($app['disbursement_amount'] * $app['disbursement_count']) ?></strong></div></div>
      <div class="detail-item"><div class="detail-label">Start Date</div><div class="detail-value"><?= fdate($app['disbursement_start_date']) ?></div></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Tab: Documents -->
<div id="tab-docs" class="tab-pane">
  <div class="card mb-2">
    <div class="card-title">📎 Uploaded Documents</div>
    <?php if ($documents): ?>
    <div class="doc-grid">
      <?php foreach ($documents as $doc): ?>
      <div class="doc-item">
        <?php if (str_starts_with($doc['mime_type'],'image/')): ?>
        <img src="index.php?page=doc.download&id=<?= $doc['id'] ?>" class="doc-thumb" alt="<?= e($doc['original_filename']) ?>">
        <?php else: ?>
        <div class="doc-icon">📄</div>
        <?php endif; ?>
        <div class="doc-name"><?= e($doc['original_filename']) ?></div>
        <div class="doc-actions">
          <a href="index.php?page=doc.download&id=<?= $doc['id'] ?>" class="btn btn-outline btn-sm" target="_blank">View</a>
          <?php if ($doc['uploaded_by']==$auth->id() || $auth->hasRole(ROLE_SYSADMIN)): ?>
          <form method="POST" action="index.php?page=applications.deldoc" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this document?">Del</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p class="text-muted text-small">No documents uploaded.</p><?php endif; ?>
  </div>

  <!-- Upload more -->
  <?php 
    $canUpload = $auth->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]) 
                 || ($app['created_by'] == $auth->id()) 
                 || ($app['validated_by'] == $auth->id());
    if ($canUpload): 
  ?>
  <div class="card">
    <div class="card-title">📤 Upload More Documents</div>
    <form method="POST" action="index.php?page=applications.upload" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="application_id" value="<?= $app['id'] ?>">
      <div class="upload-zone mb-1">
        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.tiff,.tif">
        <div class="upload-zone-icon">📂</div>
        <div class="upload-zone-text">Click or drag files here</div>
        <div class="upload-zone-hint">PDF, JPG, PNG, TIFF — max 10 MB each</div>
      </div>
      <div class="form-group mb-1"><label>Description</label><input type="text" name="doc_description" placeholder="e.g. ID scan, proof of income…"></div>
      <button type="submit" class="btn btn-primary">Upload</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<!-- Tab: Timeline -->
<div id="tab-timeline" class="tab-pane">
  <div class="card">
    <div class="card-title">🕒 Application Timeline</div>
    <?php if ($timeline): ?>
    <div class="timeline">
      <?php foreach ($timeline as $t): ?>
      <div class="timeline-item">
        <div class="timeline-dot <?= $t['action']==='advisory_comment'?'advisory':($t['action']==='rejected'?'rejected':'') ?>"></div>
        <div class="timeline-meta"><?= fdate($t['created_at'],'d M Y H:i') ?> — <?= e($t['full_name']) ?> (<?= role_label($t['role']) ?>)</div>
        <div class="timeline-action"><?= e(str_replace('_',' ',$t['action'])) ?></div>
        <?php if ($t['comment']): ?><div class="timeline-comment">"<?= e($t['comment']) ?>"</div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p class="text-muted text-small">No log entries.</p><?php endif; ?>
  </div>
</div>

<!-- Tab: Disbursements -->
<?php if ($disbursements): ?>
<div id="tab-disb" class="tab-pane">
  <div class="card">
    <div class="card-title">💰 Disbursement Schedule</div>
    <div class="table-wrap"><table class="table-card">
      <thead><tr><th>#</th><th>Due Date</th><th class="text-right">Amount</th><th>Status</th><th>Authorized By</th><th>Date</th><th>Notes</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($disbursements as $d): ?>
        <tr>
          <td data-label="#"><?= $d['installment_no'] ?></td>
          <td data-label="Due"><?= $d['due_date'] ? fdate($d['due_date']) : '—' ?></td>
          <td data-label="Amount" class="text-right"><strong><?= money($d['amount']) ?></strong></td>
          <td data-label="Status"><?= disb_badge($d['status']) ?></td>
          <td data-label="Auth By" class="muted"><?= e($d['auth_name'] ?? '—') ?></td>
          <td data-label="Auth Date" class="muted"><?= $d['authorized_at'] ? fdate($d['authorized_at']) : '—' ?></td>
          <td data-label="Notes" class="muted"><?= e($d['notes'] ?? '') ?></td>
          <td data-label="Action">
            <?php if ($d['status']===DISB_PENDING && $auth->hasRole(ROLE_OVERALL_INCHARGE)): ?>
            <a href="index.php?page=disbursements.authorize&id=<?= $d['id'] ?>" class="btn btn-warning btn-sm">Authorize</a>
            <?php elseif ($d['status']===DISB_AUTHORIZED && $auth->hasRole(ROLE_OVERALL_INCHARGE)): ?>
            <form method="POST" action="index.php?page=disbursements.release" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= $d['id'] ?>">
              <button type="submit" class="btn btn-success btn-sm" data-confirm="Mark as released?">Release</button>
            </form>
            <?php else: ?><span class="text-muted text-small">—</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>

<!-- Tab: Edit History (1.c/sysadmin) -->
<?php if ($auth->hasRole([ROLE_OVERALL_INCHARGE,ROLE_SYSADMIN]) && $editHistory): ?>
<div id="tab-edits" class="tab-pane">
  <div class="card">
    <div class="card-title">✏️ Edit History</div>
    <div class="table-wrap"><table class="table-card">
      <thead><tr><th>Date</th><th>Edited By</th><th>Field</th><th>Old Value</th><th>New Value</th></tr></thead>
      <tbody>
        <?php foreach ($editHistory as $e): ?>
        <tr>
          <td data-label="Date" class="muted"><?= fdate($e['created_at'],'d M Y H:i') ?></td>
          <td data-label="By"><?= e($e['full_name']) ?></td>
          <td data-label="Field"><code><?= e(str_replace('_',' ',$e['field_name'])) ?></code></td>
          <td data-label="Old" class="muted"><?= e($e['old_value'] ?? '—') ?></td>
          <td data-label="New"><?= e($e['new_value'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
