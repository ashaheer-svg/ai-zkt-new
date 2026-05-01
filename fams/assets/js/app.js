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

  // ── Dynamic children rows ────────────────────────────────────────────────────
  function initChildren() {
    const container = document.getElementById('children-container');
    const addBtn    = document.getElementById('add-child');
    if (!container || !addBtn) return;

    addBtn.addEventListener('click', () => {
      const idx  = container.querySelectorAll('.child-row').length;
      const row  = document.createElement('div');
      row.className = 'child-row form-grid mb-1';
      row.style.position = 'relative';
      row.innerHTML = `
        <div class="form-group">
          <label>Child Full Name</label>
          <input type="text" name="child_name[]" placeholder="Full name">
        </div>
        <div class="form-group">
          <label>Age</label>
          <input type="number" name="child_age[]" min="0" max="25" placeholder="Age">
        </div>
        <div class="form-group">
          <label>Gender</label>
          <select name="child_gender[]">
            <option value="">—</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
          </select>
        </div>
        <button type="button" class="btn btn-danger btn-sm remove-child" style="align-self:flex-end;margin-bottom:0">Remove</button>
      `;
      container.appendChild(row);
      row.querySelector('.remove-child').addEventListener('click', () => row.remove());
    });

    // Existing remove buttons
    container.querySelectorAll('.remove-child').forEach(btn => {
      btn.addEventListener('click', () => btn.closest('.child-row').remove());
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

  // Init
  initSpouse();
  initChildren();
  initTabs();
})();
