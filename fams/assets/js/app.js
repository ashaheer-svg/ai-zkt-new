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
})();
