<?php
require_once __DIR__ . '/../backend/session_user.php';
require_auth();
$me = session_employee($conn);
$displayName = $me['full_name'] ?? ($_SESSION['username'] ?? 'Admin');
$avatarPath = !empty($me['profile_img']) ? ('../uploads/' . basename($me['profile_img'])) : 'https://via.placeholder.com/64?text=A';
$activePage = 'dashboard';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
   <title>MZE Cellular</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link href="../assets/css/dashboard.css" rel="stylesheet" />
</head>
<body>
  <aside class="sidebar">
    <h2>MZE Cellular</h2>
    <a class="nav-link active" href="dashboard.php">
      <i class="fa-solid fa-gauge me-2"></i>Dashboard
    </a>
    <a class="nav-link" href="sales.php">
      <i class="fa-solid fa-chart-line me-2"></i>Sales Performance
    </a>
    <a class="nav-link" href="attendance.php">
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

    <a class="nav-link " href="requests.php">
      <i class="fa-solid fa-chalkboard-user"></i>Training Requests
    </a>

    <a class="nav-link" href="profile.php">
      <i class="fa-solid fa-user-circle me-2"></i>Profile
    </a>
    <a class="nav-link text-danger" href="logout.php" style="position:absolute;bottom:60px;left:20px;">
      <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
    </a>
    <small style="position:absolute;bottom:20px;left:20px;opacity:0.8;font-size:12px;">© MZE Cellular</small>
  </aside>


<main class="main">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
    <div class="mb-2">
      <h2 class="page-title">MZE Dashboard</h2>
    </div>
    <div class="dropdown user-menu">
      <button class="btn btn-light d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border:1px solid #e6e6f3;">
        <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Avatar" class="user-avatar">
        <span class="user-name"><?= htmlspecialchars($displayName) ?></span>
        <i class="fa fa-chevron-down" style="font-size:0.8rem;color:#888;"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><a class="dropdown-item" href="profile.php"><i class="fa fa-user me-2"></i>Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa fa-sign-out-alt me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
    <input id="rangeMonth" type="month" class="form-control" style="width:170px;" />
    <button id="refreshBtn" class="btn" style="background:linear-gradient(90deg,var(--accent-1),var(--accent-2));color:#fff;">Refresh</button>
  </div>

  <!-- KPI cards -->
  <div class="kpi-grid">
    <div class="kpi"><div class="label">Total Account Executives</div><div class="value" id="kTotalAE">0</div><div class="sub" id="kBranchCoverage">Branch Coverage: 0</div></div>
    <div class="kpi"><div class="label">Total Market Sales (Month)</div><div class="value" id="kMarketSales">₱ 0</div><div class="sub" id="kMarketRange"></div></div>
    <div class="kpi"><div class="label">Best Account Executive</div><div class="value" id="kBestAE">—</div><div class="sub" id="kBestPoints">—</div></div>
    <div class="kpi"><div class="label">Average Points (Best AE)</div><div class="value" id="kBestAvg">—</div><div class="sub">Across evaluations this month</div></div>
  </div>

  <!-- Charts -->
  <div class="grid-two">
    <div class="card chart-card">
      <div class="card-header">
        <div class="title">Branch Sales Performance</div>
        <div style="display:flex;align-items:center;gap:8px;">
          <span class="sub">Total sales per branch</span>
          <input type="checkbox" class="btn-check" id="toggleSalesOrientation" autocomplete="off">
          <label class="btn btn-sm btn-outline-secondary" style="padding:2px 8px;font-size:11px;" for="toggleSalesOrientation" title="Toggle horizontal/vertical">Orientation</label>
        </div>
      </div>
      <div class="card-body fill">
        <div class="chart-container" style="height:100%;"><canvas id="salesChart"></canvas></div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:12px;">
      <div class="card chart-card">
        <div class="card-header">
          <div class="title">Branch Attendance</div>
          <div class="sub">Attendance logs</div>
        </div>
        <div class="card-body">
          <div class="chart-container" style="height:170px;"><canvas id="attendanceChart"></canvas></div>
        </div>
      </div>
      <div class="card chart-card">
        <div class="card-header">
          <div class="title">Branch Comparison</div>
          <div style="display:flex; gap:8px; align-items:center;">
            <span class="sub">Sales vs Attendance</span>
            <input type="checkbox" class="btn-check" id="toggleComparisonVertical" autocomplete="off">
            <label class="btn btn-sm btn-outline-secondary" style="padding:2px 8px;font-size:11px;" for="toggleComparisonVertical" title="Toggle vertical stack">Vertical</label>
          </div>
        </div>
        <div class="card-body">
          <div class="chart-container" style="height:170px;"><canvas id="comparisonChart"></canvas></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Ranking Table -->
  <div class="card mt-3">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div style="font-weight:700;">Best Account Executive Ranking</div>
      <div class="small" style="color:var(--text-light);" id="rankingRange"></div>
    </div>
    <div style="overflow:auto;max-height:420px;" class="mt-2">
      <table class="table table-borderless mb-0">
        <thead><tr><th>Rank</th><th>Employee</th><th>Branch</th><th>Total Points</th><th>Avg Points</th><th>Evaluations</th></tr></thead>
        <tbody id="rankingBody"></tbody>
      </table>
    </div>
  </div>

  <!-- Removed spacer div to reduce bottom whitespace -->
</main>

<script>
// Chart.js global styling to match Sales page look
Chart.defaults.font.family = 'Poppins, sans-serif';
Chart.defaults.color = '#4b4b5b';
Chart.defaults.plugins.legend.position = 'top';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
Chart.defaults.plugins.legend.labels.padding = 12;
// Plugin: subtle border around chart area (inside the card)
const chartAreaBorder = {
  id: 'chartAreaBorder',
  beforeDraw(chart, args, opts){
    const {ctx, chartArea:{left, top, width, height}} = chart;
    ctx.save();
    ctx.strokeStyle = opts.color || '#ececf5';
    ctx.lineWidth = opts.borderWidth || 1;
    const r = opts.borderRadius || 8;
    const right = left + width; const bottom = top + height;
    ctx.beginPath();
    ctx.moveTo(left + r, top);
    ctx.lineTo(right - r, top);
    ctx.quadraticCurveTo(right, top, right, top + r);
    ctx.lineTo(right, bottom - r);
    ctx.quadraticCurveTo(right, bottom, right - r, bottom);
    ctx.lineTo(left + r, bottom);
    ctx.quadraticCurveTo(left, bottom, left, bottom - r);
    ctx.lineTo(left, top + r);
    ctx.quadraticCurveTo(left, top, left + r, top);
    ctx.closePath();
    ctx.stroke();
    ctx.restore();
  }
};
Chart.register(chartAreaBorder);
const fmtPeso = v => `₱ ${Number(v||0).toLocaleString('en-PH',{maximumFractionDigits:2})}`;
function lastNDaysRange(n){
  const end = new Date();
  const start = new Date();
  start.setDate(end.getDate() - (n-1));
  const toISO = dt => dt.toISOString().slice(0,10);
  return { start: toISO(start), end: toISO(end) };
}
function monthRange(val){
  const d = val ? new Date(val+'-01') : new Date();
  const y=d.getFullYear(); const m=d.getMonth();
  const first=new Date(y,m,1); const last=new Date(y,m+1,0);
  const toISO = dt => dt.toISOString().slice(0,10);
  return { start: toISO(first), end: toISO(last) };
}
function getMonthInput(){ const el=document.getElementById('rangeMonth'); if(!el.value){ const now=new Date(); el.value=`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`; } return el.value; }

async function loadOverview(){
  const rng = monthRange(getMonthInput());
  let url = `../backend/analytics_overview.php?start_date=${rng.start}&end_date=${rng.end}`;
  let res = await fetch(url); let data = await res.json(); if(!data.success) return;
  document.getElementById('kTotalAE').textContent = data.total_account_executives;
  document.getElementById('kBranchCoverage').textContent = `Branch Coverage: ${data.branch_coverage}`;
  document.getElementById('kMarketSales').textContent = fmtPeso(data.total_market_sales);
  document.getElementById('kMarketRange').textContent = `${rng.start} to ${rng.end}`;
  if (data.best_account_executive){
    document.getElementById('kBestAE').textContent = `${data.best_account_executive.full_name} (${data.best_account_executive.employee_id})`;
    document.getElementById('kBestPoints').textContent = `Total Points: ${data.best_account_executive.pts}`;
    document.getElementById('kBestAvg').textContent = data.best_account_executive.avg_pts;
  } else {
    document.getElementById('kBestAE').textContent='—';
    document.getElementById('kBestPoints').textContent='—';
    document.getElementById('kBestAvg').textContent='—';
  }
  return data;
}

let salesChartRef=null, attendanceChartRef=null, comparisonChartRef=null;
function buildBarChart(ctx, labels, data, label, color, horizontal=false){
  return new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets: [{ label, data, backgroundColor: color, borderColor: 'rgba(106,66,194,0.25)', borderWidth: 1, borderRadius:8, maxBarThickness: 38, categoryPercentage: 0.6, barPercentage: 0.7, borderSkipped:false }] },
    options: {
      indexAxis: horizontal ? 'y' : 'x',
      maintainAspectRatio: false,
      responsive: true,
      animation: { duration: 350 },
      layout: { padding: { top: 6, right: 6, bottom: 6, left: 6 } },
      plugins: { 
        legend: { display: true },
        tooltip: { callbacks: { label: (ctx)=> `${ctx.dataset.label}: ${Number(ctx.raw).toLocaleString()}` } },
        chartAreaBorder: { color:'#ebe9f7', borderWidth:1, borderRadius:8 }
      },
      scales: {
  x: { grid:{ color:'#f0edf9' }, border:{ color:'#ebe9f7' }, ticks:{ autoSkip:false, maxRotation:0, padding:4, font:{ size:11 } } },
  y: { beginAtZero:true, grid:{ color:'#f0edf9' }, border:{ color:'#ebe9f7' }, ticks:{ padding:4, font:{ size:11 }, callback: v => Number(v).toLocaleString() } }
      }
    }
  });
}
function buildComparisonChart(ctx, labels, sales, attendance, vertical=false){
  return new Chart(ctx, {
    type:'bar',
    data:{ labels, datasets:[
      { label:'Sales', data:sales, backgroundColor:'rgba(124,86,255,0.70)', borderColor:'rgba(124,86,255,0.25)', borderWidth:1, borderRadius:8, borderSkipped:false },
      { label:'Attendance', data:attendance, backgroundColor:'rgba(164,140,255,0.45)', borderColor:'rgba(164,140,255,0.25)', borderWidth:1, borderRadius:8, borderSkipped:false }
    ]},
    options:{
      indexAxis: vertical ? 'x' : 'y',
      maintainAspectRatio:false,
      responsive:true,
      layout: { padding: { top: 6, right: 6, bottom: 6, left: 6 } },
  plugins:{ legend:{ position:'top', labels:{ boxWidth:10, boxHeight:10, padding:8, font:{ size:11 } } }, tooltip:{ callbacks:{ label:(ctx)=> `${ctx.dataset.label}: ${Number(ctx.raw).toLocaleString()}` } }, chartAreaBorder: { color:'#ebe9f7', borderWidth:1, borderRadius:8 } },
      scales:{
  x:{ grid:{ color:'#f0edf9' }, border:{ color:'#ebe9f7' }, ticks:{ autoSkip:false, maxRotation:0, padding:4, font:{ size:11 }, callback: v => Number(v).toLocaleString() } },
  y:{ grid:{ color:'#f0edf9' }, border:{ color:'#ebe9f7' }, ticks:{ autoSkip:false, maxRotation:0, padding:4, font:{ size:11 } } }
      }
    }
  });
}
// stray lines removed in previous patch
function abbreviateNumber(val){
  const n = Number(val||0);
  if (n >= 1_000_000) return (n/1_000_000).toFixed(1)+'M';
  if (n >= 1_000) return (n/1_000).toFixed(1)+'k';
  return n.toLocaleString();
}
// Fixed chart height; no dynamic adaptation needed

async function loadCharts(){
  const rng = monthRange(getMonthInput());
  let url = `../backend/analytics_charts.php?start_date=${rng.start}&end_date=${rng.end}`;
  let res = await fetch(url); let data = await res.json(); if(!data.success) return;
  // Branch Sales Performance (single dataset total sales only)
  const salesLabels = data.branch_sales.map(r=>r.branch);
  const salesVals = data.branch_sales.map(r=>r.total_sales);
  const salesHorizontal = document.getElementById('toggleSalesOrientation').checked;
  const salesCtx = document.getElementById('salesChart').getContext('2d');
  if(salesChartRef) salesChartRef.destroy();
  salesChartRef = buildBarChart(salesCtx, salesLabels, salesVals, 'Sales', 'rgba(106,66,194,0.65)', salesHorizontal);
  // Attendance
  const attLabels = data.branch_attendance.map(r=>r.branch); const attVals = data.branch_attendance.map(r=>r.attendance_count);
  if(attendanceChartRef) attendanceChartRef.destroy(); attendanceChartRef = buildBarChart(document.getElementById('attendanceChart').getContext('2d'), attLabels, attVals, 'Attendance', 'rgba(138,106,214,0.55)', true);
  // Comparison (vertical toggle)
  const cmpLabels = data.branch_comparison.map(r=>r.branch); const cmpSales = data.branch_comparison.map(r=>r.sales); const cmpAtt = data.branch_comparison.map(r=>r.attendance);
  const comparisonVertical = document.getElementById('toggleComparisonVertical').checked;
  if(comparisonChartRef) comparisonChartRef.destroy(); comparisonChartRef = buildComparisonChart(document.getElementById('comparisonChart').getContext('2d'), cmpLabels, cmpSales, cmpAtt, comparisonVertical);
  return data;
}

async function loadRanking(){
  const rng = monthRange(getMonthInput());
  const url = `../backend/analytics_evaluation_ranking.php?start_date=${rng.start}&end_date=${rng.end}&limit=200`;
  const res = await fetch(url); const data = await res.json(); if(!data.success) return;
  document.getElementById('rankingRange').textContent = `${rng.start} to ${rng.end}`;
  const tbody = document.getElementById('rankingBody'); tbody.innerHTML='';
  data.data.forEach(r=>{
    const tr=document.createElement('tr');
    tr.innerHTML = `<td>${r.rank}</td><td>${r.full_name} (${r.employee_id})</td><td>${r.branch}</td><td>${r.total_points.toFixed(2)}</td><td>${r.avg_points.toFixed(2)}</td><td>${r.evaluations}</td>`;
    tbody.appendChild(tr);
  });
  return data;
}

async function refreshAll(){ await Promise.all([loadOverview(), loadCharts(), loadRanking()]); }
// Refresh button and month input wiring
document.getElementById('refreshBtn').addEventListener('click', (e)=>{ e.preventDefault(); refreshAll(); });
const monthEl = document.getElementById('rangeMonth');
monthEl.addEventListener('change', ()=> refreshAll());
monthEl.addEventListener('keyup', (e)=>{ if(e.key==='Enter'){ refreshAll(); }});
document.getElementById('toggleSalesOrientation').addEventListener('change', ()=> loadCharts());
document.getElementById('toggleComparisonVertical').addEventListener('change', ()=> loadCharts());
// Initial month value
getMonthInput(); refreshAll();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
