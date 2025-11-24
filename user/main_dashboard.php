<?php
require_once __DIR__ . '/../backend/session_user.php';
require_auth();
if (($_SESSION['role'] ?? '') !== 'account_executive') { header('Location: login.php'); exit; }
$me = session_employee($conn);
$displayName = $me['full_name'] ?? ($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'User'));
$avatarPath = !empty($me['profile_img']) ? ('../uploads/' . $me['profile_img']) : 'https://via.placeholder.com/64?text=U';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
   <title>MZE Cellular</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/main_dashboard.css" rel="stylesheet" />
</head>
<body>
  <div class="sidebar">
    <h2>MZE Cellular</h2>
    <a class="nav-link active" href="main_dashboard.php">
      <i class="fa-solid fa-gauge me-2"></i>Dashboard
    </a>
    <a class="nav-link" href="evaluation.php">
      <i class="fa-solid fa-clipboard-list me-2"></i>Monthly Performance
    </a>
    <a class="nav-link" href="attendance_record.php">
      <i class="fa-solid fa-user-check me-2"></i>Attendance
    </a>
    <a class="nav-link" href="notification.php">
      <i class="fa-solid fa-bullhorn me-2"></i>Notification
    </a>
    <a class="nav-link" href="training.php">
      <i class="fa-solid fa-graduation-cap me-2"></i>Training
    </a>
    <a class="nav-link" href="profile.php">
      <i class="fa-solid fa-user-circle me-2"></i>Profile
    </a>
    <a class="nav-link text-danger" href="logout.php" style="position:absolute;bottom:60px;left:20px;">
      <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
    </a>
    <small style="position:absolute;bottom:20px;left:20px;opacity:0.8;font-size:12px;">© MZE Cellular</small>
  </div>

  <main class="main">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
      <div class="mb-2">
        <h2 class="page-title">Performance Dashboard</h2>
      </div>
      <div class="dropdown user-menu mb-2">
        <button class="btn btn-light d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border:1px solid #e6e6f3;">
          <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Avatar" class="user-avatar">
          <span class="user-name" id="userName"><?= htmlspecialchars($displayName) ?></span>
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
      <div class="d-flex align-items-center gap-2">
        <span class="small text-muted">Month</span>
        <input id="monthSelect" type="month" class="form-control form-control-sm" style="width:auto;" />
      </div>
      <button id="refreshBtn" class="btn btn-sm" style="background:linear-gradient(90deg,var(--accent-1),var(--accent-2));color:#fff;">Refresh</button>
      <div class="ms-auto small text-muted" id="dataMeta"></div>
    </div>

    <div class="kpi-grid">
      <div class="kpi">
        <div class="label">Latest Evaluation Grade</div>
        <div class="value" id="kpiGrade">—</div>
        <div class="sub" id="kpiGradeSub">No evaluations yet</div>
      </div>
      <div class="kpi">
        <div class="label">Total Sales (Selected Month)</div>
        <div class="value" id="kpiSales">₱ 0</div>
        <div class="sub" id="kpiSalesSub">No records</div>
      </div>
      <div class="kpi">
        <div class="label">Average Points (Selected Month)</div>
        <div class="value" id="kpiAvg">0.00%</div>
        <div class="sub" id="kpiAvgSub">—</div>
      </div>
      <div class="kpi">
        <div class="label">Quota Completion (YTD)</div>
        <div class="d-flex align-items-center gap-3">
          <div class="flex-grow-1">
            <div class="progress mb-1" style="height:12px;">
              <div class="progress-bar bg-success" id="quotaBar" role="progressbar" style="width:0%"></div>
            </div>
            <div class="sub" id="kpiQuotaSub">₱ 0 / ₱ 0</div>
          </div>
          <div class="text-end value" id="kpiQuota" style="font-size:20px;">0%</div>
        </div>
      </div>
    </div>

    <div class="grid-two">
      <div class="card chart-card">
        <div class="card-header">
          <div class="title">Monthly Sales (<span id="chartYear">—</span>)</div>
          <span class="sub" id="salesNote"></span>
        </div>
        <div class="card-body fill"><div class="chart-container chart-lg"><canvas id="salesChart"></canvas></div></div>
      </div>
      <div class="card chart-card">
        <div class="card-header"><div class="title">Evaluation Trend</div></div>
        <div class="card-body fill"><div class="chart-container chart-lg"><canvas id="gradeChart"></canvas></div></div>
      </div>
    </div>
    <div class="grid-two">
      <div class="card chart-card">
        <div class="card-header"><div class="title">Monthly Attendance</div></div>
        <div class="card-body fill"><div class="chart-container chart-lg"><canvas id="attendanceChart"></canvas></div></div>
      </div>
      <div class="card chart-card">
        <div class="card-header"><div class="title">Attendance vs Evaluation (Monthly)</div></div>
        <div class="card-body fill"><div class="chart-container chart-lg"><canvas id="compareChart"></canvas></div></div>
      </div>
    </div>

    <p class="footer-note mt-3">Data is based on your submitted evaluations. If something looks off, please contact your auditor.</p>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script>
  const state = { year: 2025, month: (new Date()).getMonth(), raw: null, attendance:{ year:null, data:Array(12).fill(0) } };
    // Chart.js global defaults to mirror admin look
    Chart.defaults.font.family = 'Poppins, sans-serif';
    Chart.defaults.color = '#4b4b5b';
    Chart.defaults.plugins.legend.position = 'top';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
    const chartAreaBorder = { id:'chartAreaBorder', beforeDraw(chart, args, opts){ const {ctx, chartArea:{left, top, width, height}} = chart; ctx.save(); ctx.strokeStyle = opts.color||'#ececf5'; ctx.lineWidth = opts.borderWidth||1; const r = opts.borderRadius||8; const right = left+width; const bottom = top+height; ctx.beginPath(); ctx.moveTo(left+r, top); ctx.lineTo(right-r, top); ctx.quadraticCurveTo(right, top, right, top+r); ctx.lineTo(right, bottom-r); ctx.quadraticCurveTo(right, bottom, right-r, bottom); ctx.lineTo(left+r, bottom); ctx.quadraticCurveTo(left, bottom, left, bottom-r); ctx.lineTo(left, top+r); ctx.quadraticCurveTo(left, top, left+r, top); ctx.closePath(); ctx.stroke(); ctx.restore(); } }; Chart.register(chartAreaBorder);
    const fmtPeso = (v)=> new Intl.NumberFormat(undefined,{ style:'currency', currency:'PHP', maximumFractionDigits:0}).format(v||0);

    function monthIndex(dateStr){ const d = new Date(dateStr); if (isNaN(d)) return null; return d.getMonth(); }
    function yearOf(dateStr){ const d = new Date(dateStr); if (isNaN(d)) return null; return d.getFullYear(); }

    function compute(year, month){
      const rows = (state.raw?.data)||[];
      const inYear = rows.filter(r=> yearOf(r.evaluation_date||r.created_at) === year);
      const inMonth = rows.filter(r=> {
        const y = yearOf(r.evaluation_date||r.created_at);
        const m = monthIndex(r.evaluation_date||r.created_at);
        return y===year && m===month;
      });
      // Monthly totals
      const monthly = Array(12).fill(0);
      const monthlyCount = Array(12).fill(0);
      let sumSales=0, sumQuota=0;
      let latest = null;
      for(const r of inYear){
        const idx = monthIndex(r.evaluation_date||r.created_at);
        if(idx!=null){ monthly[idx] += (parseFloat(r.total_sales)||0); monthlyCount[idx]++; }
        sumSales += (parseFloat(r.total_sales)||0);
        sumQuota += (parseFloat(r.company_quota)||0);
        if(!latest || new Date(r.evaluation_date||r.created_at) > new Date(latest.evaluation_date||latest.created_at)) latest = r;
      }
      // Month-only aggregations
      let mSales=0, mQuota=0, mAvg=0;
      if(inMonth.length){
        mSales = inMonth.reduce((a,b)=> a + (parseFloat(b.total_sales)||0), 0);
        mQuota = inMonth.reduce((a,b)=> a + (parseFloat(b.company_quota)||0), 0);
        mAvg   = inMonth.reduce((a,b)=> a + (parseFloat(b.total_points_percent)||0), 0) / inMonth.length;
      }
      // Evaluation grades
      const sorted = rows.slice().sort((a,b)=> new Date(a.evaluation_date||a.created_at) - new Date(b.evaluation_date||b.created_at));
      const gradeLabels = sorted.map(r=>{
        const d = new Date(r.evaluation_date||r.created_at);
        return d.toLocaleString(undefined,{ month:'short', year:'2-digit' });
      });
      const gradeValues = sorted.map(r=> (parseFloat(r.total_points_percent)||0));

      return { inYear, inMonth, monthly, monthlyCount, sumSales, sumQuota, mSales, mQuota, mAvg, latest, gradeLabels, gradeValues };
    }

  let salesChart, gradeChart, attendanceChart, compareChart;
  function render(year, month){
      const monthName = new Date(year, month, 1).toLocaleString(undefined,{ month:'long', year:'numeric' });
      document.getElementById('chartYear').textContent = year;

      const { inYear, inMonth, monthly, monthlyCount, sumSales, sumQuota, mSales, mQuota, mAvg, latest, gradeLabels, gradeValues } = compute(year, month);
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month+1, 0);
      document.getElementById('dataMeta').textContent = `${inMonth.length} evaluation(s) • ${firstDay.toLocaleDateString()} - ${lastDay.toLocaleDateString()}`;

      // KPIs
      const grade = latest ? (parseFloat(latest.total_points_percent)||0) : 0;
      const gradeDate = latest ? new Date(latest.evaluation_date||latest.created_at).toLocaleDateString() : null;
      document.getElementById('kpiGrade').textContent = `${grade.toFixed(2)}%`;
      document.getElementById('kpiGradeSub').textContent = gradeDate ? `Latest on ${gradeDate}` : 'No evaluations yet';

      document.getElementById('kpiSales').textContent = fmtPeso(mSales);
      document.getElementById('kpiSalesSub').textContent = monthName;

      document.getElementById('kpiAvg').textContent = `${(mAvg||0).toFixed(2)}%`;
      document.getElementById('kpiAvgSub').textContent = inMonth.length? `${inMonth.length} evaluation(s)` : 'No evaluations this month';

      const monthsWithData = monthlyCount.filter(x=>x>0).length;
      const quotaPct = sumQuota>0 ? Math.max(0, Math.min(100, (sumSales/sumQuota)*100)) : 0; // YTD
      document.getElementById('kpiQuota').textContent = `${quotaPct.toFixed(1)}%`;
      document.getElementById('kpiQuotaSub').textContent = `${fmtPeso(sumSales)} / ${fmtPeso(sumQuota)}`;
      document.getElementById('quotaBar').style.width = `${quotaPct}%`;

      // Charts
      const labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const salesData = monthly.map(v=> Math.round(v));
      if(salesChart){ salesChart.destroy(); }
  const salesCanvas = document.getElementById('salesChart');
  salesChart = new Chart(salesCanvas,{
        type:'bar',
        data:{ labels, datasets:[{ label:'Sales', data:salesData, backgroundColor:'rgba(106,66,194,0.65)', borderColor:'rgba(106,66,194,0.25)', borderWidth:1, borderRadius:8, borderSkipped:false, maxBarThickness:38, categoryPercentage:0.6, barPercentage:0.7 }]},
        options:{ maintainAspectRatio:false, responsive:true, plugins:{ legend:{ display:true }, chartAreaBorder:{ color:'#ebe9f7', borderWidth:1, borderRadius:8 } }, scales:{ x:{ grid:{ color:'#f0edf9' }, border:{ color:'#ebe9f7' }, ticks:{ autoSkip:false, maxRotation:0, padding:4, font:{ size:11 } } }, y:{ beginAtZero:true, grid:{ color:'#f0edf9' }, border:{ color:'#ebe9f7' }, ticks:{ padding:4, font:{ size:11 }, callback: v => Number(v).toLocaleString() } } } }
      });
      document.getElementById('salesNote').textContent = monthsWithData? '' : 'No data found for the selected year';

      if(gradeChart){ gradeChart.destroy(); }
      const gradeCanvas = document.getElementById('gradeChart');
      gradeChart = new Chart(gradeCanvas,{
        type:'line',
        data:{ labels: gradeLabels, datasets:[{ label:'Total Points %', data:gradeValues, borderColor:'#6a42c2', backgroundColor:'rgba(106,66,194,0.15)', fill:true, tension:0.3 }]},
        options:{ maintainAspectRatio:false, scales:{ y:{ suggestedMin:0, suggestedMax:100 } }, plugins:{ legend:{ display:false }, chartAreaBorder:{ color:'#ebe9f7', borderWidth:1, borderRadius:8 } } }
      });

      // If no data, overlay message
      if(monthsWithData===0){
        salesCanvas.parentElement.innerHTML = '<div class="chart-empty">No monthly sales data for selected year</div>';
      }
      if(!gradeLabels.length){
        gradeCanvas.parentElement.innerHTML = '<div class="chart-empty">No evaluation history available</div>';
      }

      // Attendance chart
      const attLabels = labels;
      const attData = (state.attendance?.year===year) ? (state.attendance.data||Array(12).fill(0)) : Array(12).fill(0);
      if(attendanceChart){ attendanceChart.destroy(); }
      const attCanvas = document.getElementById('attendanceChart');
      attendanceChart = new Chart(attCanvas, {
        type:'bar',
        data:{ labels: attLabels, datasets:[{ label:'Attendance Days', data: attData, backgroundColor:'rgba(106,66,194,0.55)', borderRadius:8, maxBarThickness:36 }]},
        options:{ maintainAspectRatio:false, scales:{ y:{ beginAtZero:true, suggestedMax:26 } }, plugins:{ legend:{ display:true }, chartAreaBorder:{ color:'#ebe9f7', borderWidth:1, borderRadius:8 } } }
      });
      if(attData.reduce((a,b)=>a+b,0)===0){ attCanvas.parentElement.innerHTML = '<div class="chart-empty">No attendance logs for selected year</div>'; }

      // Comparison chart (attendance vs avg evaluation %)
      const evalAvgMonthly = computeMonthlyEvalAvg(inYear);
      if(compareChart){ compareChart.destroy(); }
      const cmpCanvas = document.getElementById('compareChart');
      compareChart = new Chart(cmpCanvas, {
        type:'bar',
        data:{ labels, datasets:[
          { type:'bar', label:'Attendance Days', data:attData, backgroundColor:'rgba(106,66,194,0.5)', yAxisID:'y' },
          { type:'line', label:'Avg Evaluation %', data:evalAvgMonthly, borderColor:'#4f8cff', backgroundColor:'rgba(79,140,255,0.15)', fill:true, tension:0.3, yAxisID:'y1' }
        ]},
        options:{ maintainAspectRatio:false, plugins:{ legend:{ display:true }, chartAreaBorder:{ color:'#ebe9f7', borderWidth:1, borderRadius:8 } }, scales:{ y:{ beginAtZero:true, suggestedMax:26, title:{ display:true, text:'Days' } }, y1:{ beginAtZero:true, suggestedMax:100, position:'right', grid:{ drawOnChartArea:false }, title:{ display:true, text:'% Points' } } } }
      });
      if(evalAvgMonthly.every(x=>!x) && attData.reduce((a,b)=>a+b,0)===0){ cmpCanvas.parentElement.innerHTML = '<div class="chart-empty">No attendance or evaluation data to compare</div>'; }
    }

    function computeMonthlyEvalAvg(inYear){
      const sums = Array(12).fill(0), counts = Array(12).fill(0);
      for(const r of inYear){ const idx = monthIndex(r.evaluation_date||r.created_at); if(idx!=null){ sums[idx] += (parseFloat(r.total_points_percent)||0); counts[idx]++; } }
      return sums.map((v,i)=> counts[i] ? +(v/counts[i]).toFixed(2) : 0);
    }

    async function load(){
      try{
        const r = await fetch('../backend/get_my_evaluations.php?per_page=500&sort=date_desc');
        const j = await r.json();
        if(!j.success){ throw new Error(j.message||'Failed'); }
        state.raw = j;
        // Dropdown shows display name; optionally could update if changed
        await loadAttendance(state.year);
        render(state.year, state.month);
      }catch(err){
        state.raw = { data: [] };
        await loadAttendance(state.year);
        render(state.year, state.month);
      }
    }

    async function loadAttendance(year){
      try{
        const r = await fetch(`../backend/get_my_attendance_monthly.php?year=${year}`);
        const j = await r.json();
        if(j && j.success){ state.attendance = { year: j.year, data: Array.isArray(j.data)? j.data.map(n=>parseInt(n,10)||0) : Array(12).fill(0) }; }
        else{ state.attendance = { year, data: Array(12).fill(0) }; }
      }catch(e){ state.attendance = { year, data: Array(12).fill(0) }; }
    }

    document.addEventListener('DOMContentLoaded',()=>{
      // init month input default to current
      const now = new Date();
      const ym = now.toISOString().slice(0,7);
      document.getElementById('monthSelect').value = ym;
      document.getElementById('monthSelect').addEventListener('change', async (e)=>{
        const v = e.target.value; // format YYYY-MM
        const [y,m] = v.split('-').map(n=> parseInt(n,10));
        if(!isNaN(y) && !isNaN(m)) { state.year = y; state.month = m-1; await loadAttendance(state.year); render(state.year, state.month); }
      });
      document.getElementById('refreshBtn').addEventListener('click', async ()=>{ await loadAttendance(state.year); render(state.year, state.month); });
      load();
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
  </html>
