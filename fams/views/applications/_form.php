<?php
// Shared form partial used by both create.php and edit.php
// Variables expected: $villages, $categories, $errors, $d (post data or existing), $spouse, $children, $isEdit
$d = $d ?? $_POST;
$isEdit = $isEdit ?? false;
?>
<?php if ($errors): ?>
<div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<form method="POST" action="index.php?page=<?= $isEdit ? 'applications.edit&id='.$appId : 'applications.create' ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <!-- Applicant Details -->
  <div class="form-section">
    <div class="form-section-title">👤 Applicant Details</div>
    <div class="form-grid">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="full_name" value="<?= e($d['full_name'] ?? '') ?>" required placeholder="Full legal name">
      </div>
      <div class="form-group">
        <label>Gender *</label>
        <select name="gender" required>
          <option value="">Select…</option>
          <option value="male"   <?= ($d['gender']??'')==='male'   ?'selected':'' ?>>Male</option>
          <option value="female" <?= ($d['gender']??'')==='female' ?'selected':'' ?>>Female</option>
        </select>
      </div>
      <div class="form-group">
        <label>Age</label>
        <input type="number" name="age" value="<?= e($d['age'] ?? '') ?>" min="0" max="120" placeholder="Age">
      </div>
      <div class="form-group">
        <label>ID / NIC Number</label>
        <input type="text" name="id_number" value="<?= e($d['id_number'] ?? '') ?>" placeholder="National ID">
      </div>
      <div class="form-group">
        <label>Telephone</label>
        <input type="tel" name="telephone" value="<?= e($d['telephone'] ?? '') ?>" placeholder="+xxx xxx xxxx">
      </div>
      <div class="form-group">
        <label>Village *</label>
        <select name="village_id" required <?= $isEdit?'disabled':'' ?>>
          <option value="">Select village…</option>
          <?php foreach ($villages as $v): ?>
          <option value="<?= $v['id'] ?>" <?= (($d['village_id']??'')==$v['id'])?'selected':'' ?>><?= e($v['name']) ?> <?= $v['district'] ? '('.$v['district'].')' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($isEdit): ?><input type="hidden" name="village_id" value="<?= e($d['village_id']??'') ?>"><?php endif; ?>
      </div>
      <div class="form-group">
        <label>Marital Status</label>
        <select name="marital_status">
          <option value="">Select…</option>
          <option value="married"   <?= ($d['marital_status']??'')==='married'   ?'selected':'' ?>>Married</option>
          <option value="single"    <?= ($d['marital_status']??'')==='single'    ?'selected':'' ?>>Single</option>
          <option value="divorced"  <?= ($d['marital_status']??'')==='divorced'  ?'selected':'' ?>>Divorced</option>
          <option value="widowed"   <?= ($d['marital_status']??'')==='widowed'   ?'selected':'' ?>>Widowed</option>
        </select>
      </div>
      <div class="form-group">
        <label>Home Telephone</label>
        <input type="tel" name="telephone_home" value="<?= e($d['telephone_home'] ?? '') ?>" placeholder="+xxx xxx xxxx">
      </div>
      <div class="form-group">
        <label>Residency Status</label>
        <select name="residency_status">
          <option value="">Select…</option>
          <option value="own"    <?= ($d['residency_status']??'')==='own'    ?'selected':'' ?>>Own House</option>
          <option value="rented" <?= ($d['residency_status']??'')==='rented' ?'selected':'' ?>>Rented House</option>
          <option value="other"  <?= ($d['residency_status']??'')==='other'  ?'selected':'' ?>>Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Occupation</label>
        <input type="text" name="occupation" value="<?= e($d['occupation'] ?? '') ?>" placeholder="Applicant's job">
      </div>
      <div class="form-group full">
        <label>Employer & Contact Details</label>
        <input type="text" name="employer_details" value="<?= e($d['employer_details'] ?? '') ?>" placeholder="Name and contact of employer">
      </div>
      <div class="form-group full">
        <label>Address</label>
        <textarea name="address" placeholder="Full address"><?= e($d['address'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <!-- Dependant Details -->
  <div class="form-section">
    <div class="form-section-title">👥 Family & Dependants</div>
    
    <div id="dependants-container">
      <?php foreach (($dependants ?? []) as $c): ?>
      <div class="dep-row form-grid mb-1 panel-muted p-1" style="position:relative">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="dep_name[]" value="<?= e($c['full_name']) ?>" placeholder="Full name">
        </div>
        <div class="form-group">
          <label>Age</label>
          <input type="number" name="dep_age[]" value="<?= e($c['age']) ?>" min="0" max="120">
        </div>
        <div class="form-group">
          <label>Relationship</label>
          <select name="dep_rel[]" class="dep-rel-select">
            <?php 
              $rels = ['husband'=>'Husband', 'wife'=>'Wife', 'child'=>'Child', 'parent'=>'Parent', 'grandparent'=>'Grand Parent', 'brother'=>'Brother', 'sister'=>'Sister', 'other'=>'Other'];
              foreach ($rels as $val => $lbl):
                $sel = ($c['relationship']??'') === $val ? 'selected' : '';
                if ($val === 'other' && !isset($rels[$c['relationship']??'']) && !empty($c['relationship'])) $sel = 'selected';
            ?>
              <option value="<?= $val ?>" <?= $sel ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="dep_rel_other[]" class="dep-rel-other mt-1" 
                 value="<?= (!isset($rels[$c['relationship']??''])) ? e($c['relationship']) : '' ?>"
                 style="<?= (!isset($rels[$c['relationship']??'']) && !empty($c['relationship'])) ? '' : 'display:none' ?>"
                 placeholder="Specify relationship…">
        </div>
        <div class="form-group">
          <label>Gender</label>
          <select name="dep_gender[]">
            <option value="">—</option>
            <option value="male"   <?= ($c['gender']??'')==='male'   ?'selected':'' ?>>Male</option>
            <option value="female" <?= ($c['gender']??'')==='female' ?'selected':'' ?>>Female</option>
          </select>
        </div>
        <div class="form-group">
          <label>Occupation</label>
          <input type="text" name="dep_occ[]" value="<?= e($c['occupation']??'') ?>" placeholder="Job">
        </div>
        <div class="form-group">
          <label>Income</label>
          <input type="number" name="dep_inc[]" value="<?= e($c['income']??'') ?>" step="0.01" min="0" placeholder="0.00">
        </div>
        <button type="button" class="btn btn-danger btn-sm remove-dep" style="position:absolute;top:0.5rem;right:0.5rem">×</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" id="add-dep" class="btn btn-outline btn-sm mt-1">+ Add Dependant</button>
  </div>

  <!-- Assistance Details -->
  <div class="form-section">
    <div class="form-section-title">💰 Assistance Details</div>
    <div class="form-grid">
      <div class="form-group">
        <label>Fund Category *</label>
        <select name="fund_category_id" required>
          <option value="">Select category…</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= (($d['fund_category_id']??$app['fund_category_id']??'')==$c['id'])?'selected':'' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Payment Schedule Requested *</label>
        <select name="requested_type" id="req_type" required>
          <?php foreach (DISB_LABELS as $val => $lbl): ?>
          <option value="<?= $val ?>" <?= (($d['requested_type']??$app['requested_type']??'')==$val)?'selected':'' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Installment Amount *</label>
        <input type="number" name="requested_installment" id="req_inst" step="0.01" min="0"
               value="<?= e($d['requested_installment'] ?? $app['requested_installment'] ?? '') ?>" required placeholder="0.00">
      </div>
      <div class="form-group">
        <label>Quantity (No. of Payouts) *</label>
        <input type="number" name="requested_count" id="req_count" min="1"
               value="<?= e($d['requested_count'] ?? $app['requested_count'] ?? '1') ?>" required>
      </div>
      <div class="form-group">
        <label>Total Amount Requested</label>
        <input type="number" name="amount_requested" id="total_req" step="0.01" readonly 
               value="<?= e($d['amount_requested'] ?? $app['amount_requested'] ?? '') ?>" style="background:var(--bg-dim); font-weight:bold">
      </div>
      <div class="form-group full">
        <label>Reason for application to Nabaviyyah Charitable Trust *</label>
        <textarea name="reason_for_application" required placeholder="Provide a detailed justification…"><?= e($d['reason_for_application'] ?? $app['reason_for_application'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>Applied to other Zakath Funds? *</label>
        <select name="applied_other_funds" required>
            <option value="no"  <?= ($d['applied_other_funds']??'')==='no'  ?'selected':'' ?>>No</option>
            <option value="yes" <?= ($d['applied_other_funds']??'')==='yes' ?'selected':'' ?>>Yes</option>
        </select>
      </div>
      <div class="form-group">
        <label>Expected Date of Funds</label>
        <input type="date" name="expected_date" value="<?= e($d['expected_date'] ?? $app['expected_date'] ?? '') ?>">
      </div>
      <div class="form-group full">
        <label>Internal Office Notes</label>
        <textarea name="notes" placeholder="Additional internal context…"><?= e($d['notes'] ?? $applicant['notes'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <!-- Documents -->
  <div class="form-section">
    <div class="form-section-title">📎 Supporting Documents</div>
    <div class="upload-zone">
      <input type="file" name="documents[]" id="docInput" multiple accept=".pdf,.jpg,.jpeg,.png,.tiff,.tif">
      <div class="upload-zone-icon">📂</div>
      <div class="upload-zone-text">Click or drag files here</div>
      <div class="upload-zone-hint">PDF, JPG, PNG, TIFF — max 10 MB each</div>
    </div>
    <div class="form-group mt-1">
      <label>Document Description (optional)</label>
      <input type="text" name="doc_description" placeholder="e.g. ID scan, income proof…">
    </div>
  </div>

  <div class="btn-group mt-2">
    <button type="submit" class="btn btn-primary">
      <?= $isEdit ? '💾 Save Changes' : '📤 Submit Application' ?>
    </button>
    <a href="index.php?page=applications" class="btn btn-outline">Cancel</a>
  </div>
</form>
