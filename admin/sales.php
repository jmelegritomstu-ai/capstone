<?php
// Session + user info
include '../backend/session_user.php';
require_auth();
$me = session_employee($conn);
$displayName = $me['full_name'] ?? ($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'User'));
$avatarPath = !empty($me['profile_img']) ? ('../uploads/' . $me['profile_img']) : 'https://via.placeholder.com/64?text=U';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>MZE Cellular</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome (icons) -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <!-- Custom CSS --> 
  <link href="../assets/css/sales.css" rel="stylesheet">
</head>
<body>

 <!-- Sidebar -->
<aside class="sidebar">
  <h2>MZE Cellular</h2>

  <a class="nav-link" href="dashboard.php">
    <i class="fa-solid fa-gauge me-2"></i>Dashboard
  </a>

  <a class="nav-link active" href="sales.php">
    <i class="fa-solid fa-chart-line me-2"></i>Sales Performance
  </a>

  <a class="nav-link " href="attendance.php">
    <i class="fa-solid fa-user-check me-2"></i>Attendance
  </a>

  <a class="nav-link" href="employees.php">
    <i class="fa-solid fa-users me-2"></i>People
  </a>

  <a class="nav-link" href="forms.php">
    <i class="fa-solid fa-file-pen me-2"></i>Forms / Evaluations
  </a>

  <a class="nav-link" href="notices.php">
    <i class="fa-solid fa-bullhorn me-2"></i>Notices
  </a>

  <a class="nav-link " href="requests.php"><i class="fa-solid fa-chalkboard-user"></i>Training Requests</a>

  <a class="nav-link" href="profile.php">
    <i class="fa-solid fa-user-circle me-2"></i>Profile
  </a>

  <a class="nav-link text-danger" href="logout.php" style="position:absolute;bottom:60px;left:20px;">
    <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
  </a>

  <small style="position:absolute;bottom:20px;left:20px;opacity:0.8;font-size:12px;">
    © MZE Cellular
  </small>
</aside>

  <main class="main">
    <!-- header: plain text like People Directory -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
      <div class="mb-2">
        <h2 class="page-title">Sales &amp; Vault Performance</h2>
      </div>
      <div class="dropdown user-menu">
        <button class="btn btn-light d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border:1px solid #e6e6f3;">
          <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Avatar" class="user-avatar">
          <span class="user-name" style="color:var(--text-dark);">
            <?= htmlspecialchars($displayName) ?>
          </span>
          <i class="fa fa-chevron-down" style="font-size:0.8rem;color:#888;"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
          <li><a class="dropdown-item" href="profile.php"><i class="fa fa-user me-2"></i>Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>

    <!-- filters -->
    <div class="filters" style="margin-top:12px;">
      <select id="branchSelect" class="form-select" style="width:220px;">
        <option value="" selected>All branches</option>
        <option value="Manila">Manila</option>
        <option value="QC">Quezon City (QC)</option>
        <option value="Caloocan">Caloocan</option>
        <option value="Paranaque">Parañaque</option>
        <option value="Marikina">Marikina</option>
        <option value="Taguig">Taguig</option>
        <option value="Cainta/Antipolo">Cainta / Antipolo</option>
        <option value="Bicutan">Bicutan</option>
        <option value="Imus">Imus</option>
        <option value="Pedro Gil">Pedro Gil</option>
      </select>

  <!-- Month/Year picker to avoid confusion about exact day -->
  <input id="dateSelect" type="month" class="form-control" style="width:160px" />

      <div class="controls" style="margin-left:6px;">
        <button id="refreshBtn" class="btn" style="background:linear-gradient(90deg,var(--accent-1),var(--accent-2)); color:#fff;">Refresh</button>
        <button id="exportCsvBtn" class="btn btn-outline-secondary">Export CSV</button>
      </div>
      <div id="dataNotice" class="small-muted" style="margin-left:8px;"></div>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
      <div class="kpi">
        <div class="label">Total Branch Sales</div>
        <div class="value" id="kTotal">₱ 0</div>
        <div class="sub-value" id="kTotalChange">Daily Target: ₱ 50,000</div>
        <div class="quota-indicator">
          <span class="small-muted">Target Progress</span>
          <div class="quota-bar">
            <div class="quota-fill" id="salesTargetProgress" style="width: 0%"></div>
          </div>
          <span class="small-muted" id="salesTargetPercent">0%</span>
        </div>
      </div>

      <div class="kpi">
        <div class="label">Sales Quota Performance</div>
        <div class="value" id="kSalesQuota">0%</div>
        <div class="sub-value" id="kSalesQuotaDetail">Account Executive Meeting Quota</div>
        <div class="progress">
          <div class="progress-bar" id="salesQuotaProgress" style="width: 0%"></div>
        </div>
      </div>

      <div class="kpi">
        <div class="label">Vault Performance</div>
        <div class="value" id="kVault">₱ 0</div>
        <div class="sub-value" id="kVaultQuota">Quota: ₱ 25,000</div>
        <div class="progress">
          <div class="progress-bar" id="vaultProgress" style="width: 0%"></div>
        </div>
      </div>

      <div class="kpi">
        <div class="label">Top Sales Account Executive</div>
        <div class="value" id="kTop">—</div>
        <div class="sub-value" id="kTopVal">—</div>
        <div class="quota-indicator">
          <span class="small-muted">Quota: </span>
          <span class="small-muted" id="kTopQuota">—</span>
        </div>
      </div>

      <div class="kpi">
        <div class="label">Vault Leader</div>
        <div class="value" id="kVaultTop">—</div>
        <div class="sub-value" id="kVaultTopVal">—</div>
        <div class="quota-indicator">
          <span class="small-muted">Quota: </span>
          <span class="small-muted" id="kVaultTopQuota">—</span>
        </div>
      </div>
    </div>

    <!-- Charts: left big, right small -->
    <div class="grid-two">
      <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <div style="font-weight:700">Sales vs Quota Performance</div>
          <div class="small-muted">Account Executive Sales vs their individual quotas</div>
        </div>

        <div class="chart-container chart-lg" style="margin-top:12px;">
          <canvas id="quotaChart"></canvas>
        </div>
      </div>

      <div style="display:flex; flex-direction:column; gap:12px;">
        <div class="card">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div style="font-weight:700">Quota Completion</div>
            <div class="small-muted">Sales & Vault Status</div>
          </div>
          <div class="chart-container chart-md" style="margin-top:10px;">
            <canvas id="quotaStatusChart"></canvas>
          </div>
        </div>

        <div class="card">
          <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
            <div style="font-weight:700">Branch Comparison</div>
            <div style="display:flex; align-items:center; gap:10px;">
              <div class="small-muted">Quota Achievement Rate</div>
              <!-- Toggle button to show all branches -->
              <input type="checkbox" class="btn-check" id="toggleAllBranches" autocomplete="off">
              <label class="btn btn-sm btn-outline-secondary" for="toggleAllBranches" title="Show all branches in comparison">All branches</label>
            </div>
          </div>
          <div class="chart-container chart-sm" style="margin-top:10px;">
            <canvas id="branchChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card" style="margin-top:12px;">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div style="font-weight:700">Employee Monthly Performance</div>
        <div class="small-muted">Sales Quotas & Vault Tracking</div>
      </div>

      <div style="overflow:auto; margin-top:12px;">
        <table class="table table-borderless">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Employee</th>
              <th>Employee ID</th>
              <th>Monthly Sales</th>
              <th>Sales Quota</th>
              <th>Sales Status</th>
              <th>Vault Amount</th>
              <th>Vault Quota</th>
              <th>Vault Status</th>
            </tr>
          </thead>
          <tbody id="empTbody"></tbody>
        </table>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
        <small id="tableInfo" class="small-muted"></small>
        <div>
          <button id="prevPage" class="btn btn-sm btn-outline-secondary me-2">Prev</button>
          <button id="nextPage" class="btn btn-sm btn-outline-secondary">Next</button>
        </div>
      </div>
    </div>

    <div style="height:36px"></div>
  </main>

  <script>
  /*****************************************************************
   * Live data: fetch from backend/get_sales_performance.php
   *****************************************************************/
  // Today's representations
  const today = new Date().toISOString().slice(0,10);
  const thisMonth = (()=>{ const d = new Date(); const y=d.getFullYear(); const m=String(d.getMonth()+1).padStart(2,'0'); return `${y}-${m}`; })();
  let currentDate = today;
  let currentPage = 1;
  let totalPages = 1;
  let lastResult = { rows: [], summary: {} };
  let lastFallback = { used:false, reason:"", forMonth:"" };
  let debugMode = true; // toggle to false to silence console debugging

  function formatCurrency(v){
    const n = Number(v || 0);
    return `₱ ${n.toLocaleString('en-PH', { maximumFractionDigits: 2, minimumFractionDigits: 0 })}`;
  }

  function monthRangeFromDateStr(dStr){
    // Accepts 'YYYY-MM' (from <input type="month">) or 'YYYY-MM-DD'
    if (!dStr) {
      const d=new Date();
      const y=d.getFullYear(); const m=d.getMonth();
      const first=new Date(y,m,1); const last=new Date(y,m+1,0);
      const toISO=(dt)=> dt.toISOString().slice(0,10);
      return { start: toISO(first), end: toISO(last) };
    }
    const mm = /^\d{4}-\d{2}$/.test(dStr);
    if (mm) {
      const [yS,mS] = dStr.split('-');
      const y = parseInt(yS,10); const m = parseInt(mS,10)-1;
      const first=new Date(y,m,1); const last=new Date(y,m+1,0);
      const toISO=(dt)=> dt.toISOString().slice(0,10);
      return { start: toISO(first), end: toISO(last) };
    } else {
      const d = new Date(dStr);
      const y = d.getFullYear(); const m = d.getMonth();
      const first=new Date(y,m,1); const last=new Date(y,m+1,0);
      const toISO=(dt)=> dt.toISOString().slice(0,10);
      return { start: toISO(first), end: toISO(last) };
    }
  }

  async function fetchSales(page=1){
    try {
      const params = new URLSearchParams();
      // date filter: WHOLE month from month picker (input type="month")
      const dateStr = dateSelect.value || thisMonth;
      const rng = monthRangeFromDateStr(dateStr);
      params.set('start_date', rng.start);
      params.set('end_date', rng.end);

      // Branch filter handling; skip explicit filter if value is empty (All branches)
      const selected = branchSelect.value.trim();
      if (selected) {
        if (selected === 'Cainta/Antipolo') {
          // Provide both possible locations for union of data
          params.set('branch_locations', ['Cainta','Antipolo'].join(','));
        } else if (selected === 'Paranaque') {
          params.set('branch_locations', ['Paranaque','Parañaque'].join(','));
        } else {
          params.set('branch_location', selected);
        }
      }

      params.set('page', page);
      params.set('per_page', 200); // Larger page to reduce pagination zero states
      params.set('sort', 'date_desc');

      const url = `../backend/get_sales_monthly.php?${params.toString()}`;
      const res = await fetch(url);
      const textRaw = await res.text();
      let data = {};
      try { data = JSON.parse(textRaw); } catch(parseErr){
        if (debugMode) console.error('Failed to parse JSON from get_sales_monthly:', parseErr, textRaw);
        throw new Error('Invalid JSON from backend');
      }
      if (debugMode) console.log('[sales] primary fetch params=', Object.fromEntries(params.entries()), 'response=', data);
      if (!data.success) throw new Error(data.message || 'Failed to load');

      // Stepwise fallback to surface any available data
      // IMPORTANT: When a specific branch is selected and returns no rows, DO NOT fallback — show empty state
      // Only fallback to all-time if no branch is selected (All branches)
      if (!selected && (!Array.isArray(data.data) || data.data.length === 0)) {
        const fb2 = new URLSearchParams(params.toString());
        fb2.set('start_date', '1970-01-01');
        fb2.set('end_date', '2100-12-31');
        fb2.delete('branch_location');
        fb2.delete('branch_locations');
        const resFb2 = await fetch(`../backend/get_sales_monthly.php?${fb2.toString()}`);
        const raw2 = await resFb2.text();
        let dataFb2 = {}; try { dataFb2 = JSON.parse(raw2); } catch(e){ if (debugMode) console.warn('Parse fallback2 failed', raw2); }
        if (debugMode) console.log('[sales] fallback2 all-time response=', dataFb2);
        if (dataFb2 && dataFb2.success && Array.isArray(dataFb2.data) && dataFb2.data.length) {
          lastFallback = { used:true, reason:'No evaluations found for the selected month. Showing all-time results.', forMonth: dateStr };
          currentPage = dataFb2.summary.page;
          totalPages = dataFb2.summary.pages;
          lastResult = { rows: dataFb2.data, summary: dataFb2.summary };
          return lastResult;
        }
      }

      // If a branch is selected and still empty, keep empty results and show a note
      if (selected && (!Array.isArray(data.data) || data.data.length === 0)) {
        lastFallback = { used:true, reason:'No data for the selected branch this month.', forMonth: dateStr };
      } else {
        lastFallback = { used:false, reason:'', forMonth: dateStr };
      }

      // Normal path (no fallback or handled above)
      currentPage = data.summary.page;
      totalPages = data.summary.pages;
      lastResult = { rows: data.data, summary: data.summary };
      return lastResult;
    } catch(err){
      console.warn('Sales fetch failed:', err);
      currentPage = 1;
      totalPages = 1;
      lastResult = { rows: [], summary: { page:1, pages:1 } };
      const note = document.getElementById('dataNotice');
      if (note) note.textContent = 'Load error: '+ (err.message||err);
      return lastResult;
    }
  }

  // Cache employees to enrich missing names when API returns null name
  let employeesCache = null;
  async function ensureEmployeesCache(){
    if (employeesCache) return employeesCache;
    try {
      const res = await fetch('../backend/get_employees.php');
      const data = await res.json();
      if (data && data.success) {
        // Build maps by normalized emp_id and full_name
        const mapById = {};
        const mapByName = {};
        (data.data || []).forEach(e => {
          const keyId = String(e.emp_id || '').trim().toUpperCase();
          if (keyId) mapById[keyId] = e;
          const keyName = String(e.full_name || '').trim().toUpperCase();
          if (keyName) mapByName[keyName] = e;
        });
        employeesCache = { byId: mapById, byName: mapByName };
      } else {
        employeesCache = { byId: {}, byName: {} };
      }
    } catch(_e){ employeesCache = { byId: {}, byName: {} }; }
    return employeesCache;
  }

  async function enrichMissingNames(rows){
    if (!rows || !rows.length) return rows;
    const maps = await ensureEmployeesCache();
    rows.forEach(r => {
      // Fill name from ID if missing
      if (!r.employee_name && r.employee_id) {
        const key = String(r.employee_id).trim().toUpperCase();
        const found = maps.byId[key];
        if (found && found.full_name) {
          r.employee_name = found.full_name;
          if (!r.branch_name) r.branch_name = found.branch_name || r.branch_name;
          if (!r.branch_location) r.branch_location = found.branch_location || r.branch_location;
        }
      }
      // Fill ID from name if missing
      if (!r.employee_id && r.employee_name) {
        const keyN = String(r.employee_name).trim().toUpperCase();
        const foundN = maps.byName[keyN];
        if (foundN && foundN.emp_id) {
          r.employee_id = foundN.emp_id;
          if (!r.branch_name) r.branch_name = foundN.branch_name || r.branch_name;
          if (!r.branch_location) r.branch_location = foundN.branch_location || r.branch_location;
        }
      }
    });
    return rows;
  }

  // Helper for combined display: "Name (ID)"
  function displayNameWithId(r){
    const name = r.employee_name || '';
    const id = r.employee_id || '';
    if (name && id) return `${name} (${id})`;
    return name || id || '—';
  }

  function renderKPIs(rows){
    const totalSales = rows.reduce((a,r)=> a + (Number(r.total_sales)||0), 0);
    const totalQuota = rows.reduce((a,r)=> a + (Number(r.company_quota)||0), 0);
    const totalVault = rows.reduce((a,r)=> a + (Number(r.vault_collected)||0), 0);
    const totalVaultQuota = rows.reduce((a,r)=> a + (Number(r.vault_quota)||0), 0);
    const achieved = rows.filter(r => Number(r.total_sales)>=Number(r.company_quota)).length;
    const quotaPct = rows.length ? Math.round((achieved/rows.length)*100) : 0;
    const targetProgress = totalQuota>0 ? Math.min(100, Math.round((totalSales/totalQuota)*100)) : 0;
    document.getElementById('kTotal').textContent = formatCurrency(totalSales);
    document.getElementById('kTotalChange').textContent = `Monthly Target: ${formatCurrency(totalQuota)}`;
    document.getElementById('salesTargetProgress').style.width = `${targetProgress}%`;
    document.getElementById('salesTargetPercent').textContent = `${targetProgress}%`;

    document.getElementById('kSalesQuota').textContent = `${quotaPct}%`;
    document.getElementById('salesQuotaProgress').style.width = `${quotaPct}%`;
    document.getElementById('kSalesQuotaDetail').textContent = `Employees Meeting Quota: ${achieved}/${rows.length}`;

    document.getElementById('kVault').textContent = formatCurrency(totalVault);
    document.getElementById('kVaultQuota').textContent = `Quota: ${formatCurrency(totalVaultQuota)}`;
    const vaultPct = totalVaultQuota>0 ? Math.min(100, Math.round((totalVault/totalVaultQuota)*100)) : 0;
    document.getElementById('vaultProgress').style.width = `${vaultPct}%`;

    // Top performers
    if (rows.length){
      const topSales = [...rows].sort((a,b)=> Number(b.total_sales)-Number(a.total_sales))[0];
      document.getElementById('kTop').textContent = displayNameWithId(topSales);
      document.getElementById('kTopVal').textContent = formatCurrency(topSales.total_sales);
      document.getElementById('kTopQuota').textContent = formatCurrency(topSales.company_quota);

      const topVault = [...rows].sort((a,b)=> Number(b.vault_collected)-Number(a.vault_collected))[0];
      document.getElementById('kVaultTop').textContent = displayNameWithId(topVault);
      document.getElementById('kVaultTopVal').textContent = formatCurrency(topVault.vault_collected);
      document.getElementById('kVaultTopQuota').textContent = formatCurrency(topVault.vault_quota);
    } else {
      document.getElementById('kTop').textContent = '—';
      document.getElementById('kTopVal').textContent = '—';
      document.getElementById('kTopQuota').textContent = '—';
      document.getElementById('kVaultTop').textContent = '—';
      document.getElementById('kVaultTopVal').textContent = '—';
      document.getElementById('kVaultTopQuota').textContent = '—';
    }
  }

  let quotaChartRef = null;
  let quotaStatusRef = null;
  let branchChartRef = null;
  function renderChartsBasic(rows){
  const labels = rows.map(r=> displayNameWithId(r));
  const salesData = rows.map(r=> Number(r.total_sales)||0);
  const quotaData = rows.map(r=> Number(r.company_quota)||0);
    const ctx = document.getElementById('quotaChart').getContext('2d');
    if (quotaChartRef) { quotaChartRef.destroy(); }
    quotaChartRef = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Sales', data: salesData, backgroundColor: 'rgba(74,0,224,0.5)' },
          { label: 'Quota', data: quotaData, backgroundColor: 'rgba(142,45,226,0.3)' }
        ]
      },
      options: { responsive:true, plugins:{ legend:{ position:'top' } } }
    });

    // Quota status pie
  const met = rows.filter(r=> Number(r.total_sales)>=Number(r.company_quota) && Number(r.company_quota)>0).length;
    const notMet = rows.length - met;
    const ctx2 = document.getElementById('quotaStatusChart').getContext('2d');
    if (quotaStatusRef) { quotaStatusRef.destroy(); }
    quotaStatusRef = new Chart(ctx2, {
      type: 'doughnut',
      data: { labels:['Met','Not Met'], datasets:[{ data:[met, notMet], backgroundColor:['#4ade80','#fb7185'] }] },
      options: { responsive:true, plugins:{ legend:{ position:'bottom' } } }
    });

    // Branch chart rendered separately (supports 'All branches' toggle)
  }

  // Render Branch Comparison, optionally across ALL branches
  async function renderBranchChart(){
    const useAll = document.getElementById('toggleAllBranches')?.checked;
    let rowsForBranch = lastResult.rows || [];
    if (useAll) {
      try {
        const all = await fetchSalesAll(currentPage); // page not important, we set big per_page
        rowsForBranch = all.rows || all.data || [];
      } catch(e){ /* fall back to current rows */ }
    }

    const branchMap = {};
    (rowsForBranch||[]).forEach(r => {
      const key = r.branch_location || r.branch_name || 'Unknown';
      const sales = Number(r.total_sales)||0;
      const quota = Number(r.company_quota)||0;
      if (!branchMap[key]) branchMap[key] = { sales:0, quota:0 };
      branchMap[key].sales += sales;
      branchMap[key].quota += quota;
    });
    const bLabels = Object.keys(branchMap);
    const bData = bLabels.map(k => {
      const { sales, quota } = branchMap[k];
      const pct = quota>0 ? Math.min(100, Math.round((sales/quota)*100)) : 0;
      return pct;
    });
    const ctx3 = document.getElementById('branchChart').getContext('2d');
    if (branchChartRef) { branchChartRef.destroy(); }
    branchChartRef = new Chart(ctx3, {
      type: 'bar',
      data: { labels: bLabels, datasets: [{ label:'Quota Achievement %', data: bData, backgroundColor:'#60a5fa' }] },
      options: { responsive:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, max:100, ticks:{ callback:v=> v+'%' } } } }
    });
  }

  // Fetch all branches data (ignores branch filter)
  async function fetchSalesAll(page=1){
    try {
      const params = new URLSearchParams();
  const dateStr = dateSelect.value || today;
  const rng = monthRangeFromDateStr(dateStr);
  params.set('start_date', rng.start);
  params.set('end_date', rng.end);
      params.set('page', 1);
      params.set('per_page', 10000);
      params.set('sort', 'date_desc');
  const url = `../backend/get_sales_monthly.php?${params.toString()}`;
      const res = await fetch(url);
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Failed to load');
      return { rows: data.data, summary: data.summary };
    } catch(err){
      console.warn('Sales-all fetch failed:', err);
      return { rows: [], summary: { page:1, pages:1 } };
    }
  }

  function renderTable(rows){
    const tbody = document.getElementById('empTbody');
    const filtered = rows; // No search filtering (employee search removed)
    tbody.innerHTML = '';
    filtered
      .map((r,i)=> ({ rank:i+1, ...r }))
      .forEach(r=>{
        const salesStatus = Number(r.total_sales) >= Number(r.company_quota) ? '<span class="badge-good">Met</span>' : '<span class="badge-warning">Below</span>';
        const vaultPct = Number(r.vault_quota)>0 ? Math.round((Number(r.vault_collected)/Number(r.vault_quota))*100) : 0;
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${r.rank}</td>
          <td>${r.employee_name || r.employee_id || '—'}</td>
          <td>${r.employee_id || '—'}</td>
          <td>${formatCurrency(r.total_sales || r.daily_sales || 0)}</td>
          <td>${formatCurrency(r.company_quota || 0)}</td>
          <td>${salesStatus}</td>
          <td>${formatCurrency(r.vault_collected || 0)}</td>
          <td>${formatCurrency(r.vault_quota || 0)}</td>
          <td>${vaultPct}%</td>
        `;
        tbody.appendChild(tr);
      });

    document.getElementById('tableInfo').textContent = `Page ${currentPage} of ${totalPages} — ${filtered.length} shown (filtered)`;
  }

  async function refreshAll(toPage){
    const { rows } = await fetchSales(toPage || currentPage);
    await enrichMissingNames(rows);
    renderKPIs(rows);
    renderChartsBasic(rows);
    renderTable(rows);
    renderBranchChart();
    // Show a small notice if we displayed fallback data
    const note = document.getElementById('dataNotice');
    if (note) {
      if (lastFallback.used) {
        const m = lastFallback.forMonth || '';
        note.textContent = `${lastFallback.reason} (Selected: ${m})`;
      } else {
        note.textContent = rows.length ? `Loaded ${rows.length} employee record(s).` : 'No records found for the selected filters.';
      }
    }
  }

  // Hotkey: press Shift+D to toggle debug console logs
  document.addEventListener('keydown', e => { if (e.shiftKey && e.key.toLowerCase()==='d'){ debugMode=!debugMode; console.log('[sales] debugMode=', debugMode); } });

  /*****************************************************************
   * UI references
   *****************************************************************/
  const branchSelect = document.getElementById('branchSelect');
  const dateSelect = document.getElementById('dateSelect');
  const refreshBtn = document.getElementById('refreshBtn');

  // Ensure month picker initialized (YYYY-MM) and default branch = All
  if (!dateSelect.value) { dateSelect.value = thisMonth; }
  if (branchSelect) { branchSelect.value = ''; }

  // Wire controls
  refreshBtn.addEventListener('click', () => { currentPage = 1; refreshAll(1); });
  document.getElementById('prevPage').addEventListener('click', () => {
    if (currentPage > 1) { currentPage -= 1; refreshAll(currentPage); }
  });
  document.getElementById('nextPage').addEventListener('click', () => {
    if (currentPage < totalPages) { currentPage += 1; refreshAll(currentPage); }
  });
  dateSelect.addEventListener('change', () => { currentPage = 1; refreshAll(1); });
  branchSelect.addEventListener('change', () => { currentPage = 1; refreshAll(1); });
  document.getElementById('toggleAllBranches').addEventListener('change', () => { renderBranchChart(); });
  // Delegate click for view buttons
  // Removed action buttons; evaluation modal trigger via table no longer applicable

  // Initial load
  // Initial load after ensuring month has default value
  refreshAll(1);

  /* Removed obsolete demo filtering + old chart code block that referenced undefined variables (employees, dailyData, etc.). */

  // Simple CSV export based on last fetched rows
  function exportCsv(){
    const headers = ['Employee Name','Employee ID','Evaluation Date','Total Sales','Company Quota','Sales Gap','Sales % (0-20)','Vault Collected','Vault Quota','Vault % (0-20)','Daily Sales'];
    const rows = (lastResult.rows || []).map(r => [
      r.employee_name || '',
      r.employee_id || '',
      r.evaluation_date || '',
      r.total_sales || 0,
      r.company_quota || 0,
      (typeof r.sales_gap !== 'undefined' ? r.sales_gap : (r.sales_gap_calc||0)),
      (typeof r.sales_percent !== 'undefined' ? r.sales_percent : (r.sales_percent_calc||0)),
      r.vault_collected || 0,
      r.vault_quota || 0,
      r.vault_percent || 0,
      r.daily_sales || 0
    ]);
    const csv = [headers, ...rows]
      .map(r => r.map(c => '"' + String(c).replace(/"/g,'""') + '"').join(','))
      .join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `sales_performance_${(dateSelect.value||today)}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
  }
  document.getElementById('exportCsvBtn').addEventListener('click', exportCsv);

  // Removed broken modal injection block that caused script parse errors and prevented data from loading.
  </script>
  <!-- Bootstrap JS for dropdown -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>