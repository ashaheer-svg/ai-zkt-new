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
          <option value="divorced"  <?= ($d['marital_status']??'')==='divorced'  ?'selected':'' ?>>Divorced</option>
          <option value="widowed"   <?= ($d['marital_status']??'')==='widowed'   ?'selected':'' ?>>Widowed</option>
          <option value="unmarried" <?= ($d['marital_status']??'')==='unmarried' ?'selected':'' ?>>Unmarried</option>
        </select>
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
        <label>Amount Requested *</label>
        <input type="number" name="amount_requested" step="0.01" min="0"
               value="<?= e($d['amount_requested'] ?? $app['amount_requested'] ?? '') ?>" required placeholder="0.00">
      </div>
      <div class="form-group full">
        <label>Notes</label>
        <textarea name="notes" placeholder="Additional notes or context…"><?= e($d['notes'] ?? $applicant['notes'] ?? '') ?></textarea>
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
