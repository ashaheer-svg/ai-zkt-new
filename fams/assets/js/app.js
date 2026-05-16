/* global app.js */
(function () {
  'use strict';

  // ── Sidebar toggle (mobile) ─────────────────────────────────────────────────
  const sidebar  = document.getElementById('sidebar');
  const toggle   = document.getElementById('menuToggle');
  const overlay  = document.createElement('div');
  overlay.className = 'sidebar-overlay';
  document.body.appendChild(overlay);

  function openSidebar()  { sidebar && sidebar.classList.add('open');     overlay.classList.add('visible'); }
  function closeSidebar() { sidebar && sidebar.classList.remove('open');  overlay.classList.remove('visible'); }

  toggle  && toggle.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
  overlay.addEventListener('click', closeSidebar);

  // ── Spouse section toggle ────────────────────────────────────────────────────
  function initSpouse() {
    const cb   = document.getElementById('has_spouse');
    const sec  = document.getElementById('spouse-section');
    if (!cb || !sec) return;
    const toggle = () => { sec.style.display = cb.checked ? 'block' : 'none'; };
    cb.addEventListener('change', toggle);
    toggle();
  }

  // ── Dynamic dependants rows ──────────────────────────────────────────────────
  function initDependants() {
    const container = document.getElementById('dependants-container');
    const addBtn    = document.getElementById('add-dep');
    if (!container || !addBtn) return;

    function bindRelOther(row) {
      const select = row.querySelector('.dep-rel-select');
      const other  = row.querySelector('.dep-rel-other');
      if (!select || !other) return;
      select.addEventListener('change', () => {
        other.style.display = select.value === 'other' ? 'block' : 'none';
        if (select.value !== 'other') other.value = '';
      });
    }

    addBtn.addEventListener('click', () => {
      const row  = document.createElement('div');
      row.className = 'dep-row form-grid mb-1 panel-muted p-1';
      row.style.position = 'relative';
      row.innerHTML = `
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="dep_name[]" placeholder="Full name">
        </div>
        <div class="form-group">
          <label>Age</label>
          <input type="number" name="dep_age[]" min="0" max="120" placeholder="Age">
        </div>
        <div class="form-group">
          <label>Relationship</label>
          <select name="dep_rel[]" class="dep-rel-select">
            <option value="husband">Husband</option>
            <option value="wife">Wife</option>
            <option value="child">Child</option>
            <option value="parent">Parent</option>
            <option value="grandparent">Grand Parent</option>
            <option value="brother">Brother</option>
            <option value="sister">Sister</option>
            <option value="other">Other</option>
          </select>
          <input type="text" name="dep_rel_other[]" class="dep-rel-other mt-1" style="display:none" placeholder="Specify relationship…">
        </div>
        <div class="form-group">
          <label>Gender</label>
          <select name="dep_gender[]">
            <option value="">—</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
          </select>
        </div>
        <div class="form-group">
          <label>Occupation</label>
          <input type="text" name="dep_occ[]" placeholder="Job">
        </div>
        <div class="form-group">
          <label>Income</label>
          <input type="number" name="dep_inc[]" step="0.01" min="0" placeholder="0.00">
        </div>
        <button type="button" class="btn btn-danger btn-sm remove-dep" style="position:absolute;top:0.5rem;right:0.5rem">×</button>
      `;
      container.appendChild(row);
      bindRelOther(row);
      row.querySelector('.remove-dep').addEventListener('click', () => row.remove());
    });

    container.querySelectorAll('.dep-row').forEach(row => {
      bindRelOther(row);
      row.querySelector('.remove-dep').addEventListener('click', () => row.remove());
    });
  }

  // ── Confirm dialogs ──────────────────────────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

  // ── Tab system ───────────────────────────────────────────────────────────────
  function initTabs() {
    document.querySelectorAll('.tabs').forEach(tabs => {
      const btns  = tabs.querySelectorAll('.tab-btn');
      const panes = document.querySelectorAll('.tab-pane');

      btns.forEach(btn => {
        btn.addEventListener('click', () => {
          btns.forEach(b => b.classList.remove('active'));
          panes.forEach(p => p.classList.remove('active'));
          btn.classList.add('active');
          const target = document.getElementById(btn.dataset.tab);
          if (target) target.classList.add('active');
        });
      });
    });
  }

  // ── Collapsible panels ───────────────────────────────────────────────────────
  document.querySelectorAll('.collapse-toggle').forEach(el => {
    el.addEventListener('click', () => {
      const body = document.getElementById(el.dataset.target);
      if (!body) return;
      const open = body.style.display !== 'none';
      body.style.display = open ? 'none' : 'block';
      el.querySelector('.collapse-icon') && (el.querySelector('.collapse-icon').textContent = open ? '▶' : '▼');
    });
  });

  // ── Upload zone drag & drop ──────────────────────────────────────────────────
  document.querySelectorAll('.upload-zone').forEach(zone => {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', e => {
      e.preventDefault(); zone.classList.remove('dragover');
      const input = zone.querySelector('input[type=file]');
      if (input && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        updateFileLabel(zone, input);
      }
    });
    const input = zone.querySelector('input[type=file]');
    if (input) input.addEventListener('change', () => updateFileLabel(zone, input));
  });

  function updateFileLabel(zone, input) {
    const textEl = zone.querySelector('.upload-zone-text');
    if (!textEl) return;
    const count = input.files.length;
    textEl.textContent = count ? `${count} file(s) selected` : 'Click or drag files here';
  }

  // ── Auto-dismiss alerts ──────────────────────────────────────────────────────
  document.querySelectorAll('.alert').forEach(a => {
    setTimeout(() => {
      a.style.transition = 'opacity .5s';
      a.style.opacity = '0';
      setTimeout(() => a.remove(), 500);
    }, 5000);
  });

  // ── Chart.js dashboard ───────────────────────────────────────────────────────
  if (typeof Chart !== 'undefined') {
    const statusChart = document.getElementById('statusChart');
    if (statusChart) {
      new Chart(statusChart, {
        type: 'doughnut',
        data: {
          labels: JSON.parse(statusChart.dataset.labels || '[]'),
          datasets: [{
            data: JSON.parse(statusChart.dataset.values || '[]'),
            backgroundColor: ['#475569','#fbbf24','#60a5fa','#a78bfa','#4ade80','#f87171','#fb923c','#2dd4bf'],
            borderWidth: 0,
          }]
        },
        options: {
          cutout: '72%',
          plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', padding: 14, font: { size: 12 } } } }
        }
      });
    }

    const cashflowChart = document.getElementById('cashflowChart');
    if (cashflowChart) {
      new Chart(cashflowChart, {
        type: 'bar',
        data: {
          labels: JSON.parse(cashflowChart.dataset.labels || '[]'),
          datasets: [{
            label: 'Amount Due',
            data: JSON.parse(cashflowChart.dataset.values || '[]'),
            backgroundColor: 'rgba(20,184,166,.6)',
            borderColor: '#14b8a6',
            borderWidth: 1,
            borderRadius: 4,
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: {
            x: { ticks: { color: '#7a8faa' }, grid: { color: '#2d3a50' } },
            y: { ticks: { color: '#7a8faa' }, grid: { color: '#2d3a50' } }
          }
        }
      });
    }
  }

  function initCalculations() {
    const inst = document.getElementById('req_inst');
    const count = document.getElementById('req_count');
    const total = document.getElementById('total_req');
    const type = document.getElementById('req_type');

    if (!inst || !count || !total) return;

    function calc() {
      const i = parseFloat(inst.value) || 0;
      const c = parseInt(count.value) || 0;
      total.value = (i * c).toFixed(2);
    }

    inst.addEventListener('input', calc);
    count.addEventListener('input', calc);
    if (type) {
        type.addEventListener('change', function() {
            if (this.value === 'one_time') {
                count.value = 1;
                count.readOnly = true;
                count.style.background = 'var(--bg-dim)';
            } else {
                count.readOnly = false;
                count.style.background = '';
            }
            calc();
        });
        // trigger initial state
        if (type.value === 'one_time') {
            count.value = 1;
            count.readOnly = true;
            count.style.background = 'var(--bg-dim)';
        }
    }
    calc();
  }

  // Init
  initDependants();
  initTabs();
  initCalculations();
  initTranslation();
  initLangDetect();
})();

/* ── Translation ──────────────────────────────────────────────────────────── */
/* Runs outside the main IIFE so it can be called from inline scripts too.   */
function initTranslation() {
  const LANG_LABELS = { ta: 'Tamil', si: 'Sinhala', en: 'English' };

  document.querySelectorAll('[data-translatable]').forEach(function (wrap) {
    const lang      = wrap.dataset.lang   || 'en';
    const table     = wrap.dataset.table  || '';
    const recordId  = wrap.dataset.recordId || '';
    const field     = wrap.dataset.field  || '';
    const textEl    = wrap.querySelector('[data-source-text]') || wrap.firstElementChild;

    // Show language badge next to label (if a label exists)
    const label = wrap.previousElementSibling;
    if (label && (label.tagName === 'LABEL' || label.classList.contains('detail-label'))) {
      const badge = document.createElement('span');
      badge.className = 'lang-badge ' + lang;
      badge.textContent = lang.toUpperCase();
      badge.title = LANG_LABELS[lang] || lang;
      label.appendChild(badge);
    }

    // Only add translate button for non-English content
    if (lang === 'en' || !textEl || !textEl.textContent.trim() || textEl.textContent.trim() === '—') {
      return;
    }

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'translate-btn';
    btn.innerHTML = '🌐 Translate to English';
    wrap.appendChild(btn);

    btn.addEventListener('click', function () {
      const text = (textEl.dataset.rawText || textEl.textContent || '').trim();
      if (!text) return;

      // Show loading state
      btn.disabled = true;
      btn.innerHTML = '<span class="translate-spinner"></span> Translating…';

      // Remove any previous result/error
      const existing = wrap.querySelector('.translation-result, .translation-error');
      if (existing) existing.remove();

      // POST to PHP proxy
      const fd = new FormData();
      fd.append('table',      table);
      fd.append('record_id',  recordId);
      fd.append('field',      field);
      fd.append('source_lang', lang);
      fd.append('text',       text);

      fetch('index.php?page=api.translate', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.error) {
            const err = document.createElement('div');
            err.className = 'translation-error';
            err.textContent = '⚠ ' + data.error;
            wrap.appendChild(err);
            btn.disabled = false;
            btn.innerHTML = '🌐 Translate to English';
            return;
          }

          const box = document.createElement('div');
          box.className = 'translation-result';

          const lbl = document.createElement('div');
          lbl.className = 'translation-result-label';
          lbl.textContent = (data.cached ? '📋 Cached' : '🌐 Translated') + ' · English';

          const txt = document.createElement('div');
          txt.textContent = data.translated;

          box.appendChild(lbl);
          box.appendChild(txt);
          wrap.appendChild(box);

          btn.className = 'translate-btn done';
          btn.innerHTML = '✅ Translated';
        })
        .catch(function () {
          const err = document.createElement('div');
          err.className = 'translation-error';
          err.textContent = '⚠ Translation service unavailable. Please try again.';
          wrap.appendChild(err);
          btn.disabled = false;
          btn.innerHTML = '🌐 Translate to English';
        });
    });
  });
}

/* ── Language Auto-detection ──────────────────────────────────────────────── */
/**
 * Watches translatable input fields (reason_for_application, notes, comment
 * textareas) and automatically updates the #input_language selector when
 * Tamil or Sinhala Unicode characters are detected.
 *
 * Tamil   : U+0B80–U+0BFF
 * Sinhala : U+0D80–U+0DFF
 * Defaults to 'en' if neither script is found.
 *
 * Shows a small live indicator so the user sees the detected language.
 */
function initLangDetect() {
  var langSelect = document.getElementById('input_language');
  if (!langSelect) return; // only runs on create/edit forms

  // Fields to watch — names that hold free-text entered by staff
  var watchedNames = ['reason_for_application', 'notes', 'comment'];

  // Build the indicator badge element once
  var indicator = document.createElement('span');
  indicator.id = 'lang-detect-indicator';
  indicator.style.cssText = 'font-size:.72rem;margin-left:.75rem;padding:.15rem .5rem;border-radius:4px;transition:opacity .3s;opacity:0;font-weight:600;';
  langSelect.parentNode.insertBefore(indicator, langSelect.nextSibling);

  var debounceTimer = null;

  function detectLang(text) {
    // Count chars in each Unicode block
    var tamilCount    = (text.match(/[\u0B80-\u0BFF]/g) || []).length;
    var sinhalaCount  = (text.match(/[\u0D80-\u0DFF]/g) || []).length;
    var total         = text.replace(/\s/g, '').length;

    if (total === 0) return null; // empty — don't change

    var tamilRatio   = tamilCount   / total;
    var sinhalaRatio = sinhalaCount / total;

    // Require at least 10% of non-space chars to be in a script to trigger
    if (tamilRatio > 0.10)   return 'ta';
    if (sinhalaRatio > 0.10) return 'si';
    return 'en';
  }

  function showIndicator(lang) {
    var labels = { ta: { text: '🟡 Tamil detected',   bg: 'rgba(251,191,36,.15)', color: '#fbbf24' },
                   si: { text: '🟣 Sinhala detected',  bg: 'rgba(139,92,246,.15)',  color: '#a78bfa' },
                   en: { text: '🟢 English detected',  bg: 'rgba(20,184,166,.12)',  color: '#2dd4bf' } };
    var info = labels[lang] || labels['en'];
    indicator.textContent      = info.text;
    indicator.style.background = info.bg;
    indicator.style.color      = info.color;
    indicator.style.opacity    = '1';
  }

  function onInput(e) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      var detected = detectLang(e.target.value || '');
      if (detected && detected !== langSelect.value) {
        langSelect.value = detected;
        showIndicator(detected);
        // Flash the select border to draw attention
        langSelect.style.borderColor = detected === 'ta' ? '#fbbf24' : (detected === 'si' ? '#a78bfa' : '#2dd4bf');
        setTimeout(function () { langSelect.style.borderColor = ''; }, 1500);
      } else if (detected) {
        showIndicator(detected);
      }
    }, 400);
  }

  // Attach to all matching textareas/inputs currently in the DOM
  watchedNames.forEach(function (name) {
    document.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
      el.addEventListener('input', onInput);
      // Also run once on load so edit-form pre-fills the indicator
      if (el.value && el.value.trim()) {
        var detected = detectLang(el.value);
        if (detected) showIndicator(detected);
      }
    });
  });

  // Update indicator whenever the select is changed manually too
  langSelect.addEventListener('change', function () {
    showIndicator(langSelect.value);
  });

  // Run initial indicator for the current selector value on page load
  showIndicator(langSelect.value);
}
