<?php
include '../backend/session_user.php';
require_auth();
$me = session_employee($conn);
$displayName = $me['full_name'] ?? ($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'User'));
$avatarPath = !empty($me['profile_img']) ? ('../uploads/' . $me['profile_img']) : 'https://via.placeholder.com/64?text=U';
$auditorName = $me['auditor_name'] ?? '—';
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
  <link href="../assets/css/attendance.css" rel="stylesheet" />
  <style>
    .badge-leave{ display:inline-block; padding:6px 10px; border-radius:14px; background:#6c757d; color:#fff; font-size:0.85rem; }
    /* Breakdown legend layout improvements: wrap items and align neatly under the chart */
    .breakdown-legend{
      display:flex;
      flex-wrap:wrap;
      gap:8px 14px;
      align-items:center;
      margin-top:12px;
      font-size:0.95rem;
      color:var(--text-dark, #333);
      padding:6px 8px;
    }
    .breakdown-legend > div{ display:inline-flex; align-items:center; gap:8px; white-space:nowrap; }
    .legend-dot{ width:12px; height:12px; border-radius:50%; display:inline-block; }
    @media (max-width: 900px){ .breakdown-legend{ justify-content:center; } }
  </style>

</head>
<body>
  <aside class="sidebar">
    <h2>MZE Cellular</h2>
    <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a>
    <a class="nav-link" href="sales.php"><i class="fa-solid fa-chart-line me-2"></i>Sales Performance</a>
    <a class="nav-link active" href="attendance.php"><i class="fa-solid fa-user-check me-2"></i>Attendance</a>
    <a class="nav-link" href="employees.php"><i class="fa-solid fa-users me-2"></i>People</a>
    <a class="nav-link" href="forms.php"><i class="fa-solid fa-file-pen me-2"></i>Forms / Evaluations</a>
    <a class="nav-link" href="notices.php"><i class="fa-solid fa-bullhorn me-2"></i>Notices</a>
    <a class="nav-link" href="requests.php"><i class="fa-solid fa-chalkboard-user"></i>Training Requests</a>
    <a class="nav-link" href="profile.php"><i class="fa-solid fa-user-circle me-2"></i>Profile</a>
    <a class="nav-link text-danger" href="logout.php" style="position:absolute;bottom:60px;left:20px;"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a>
    <small style="position:absolute;bottom:20px;left:20px;opacity:0.8;font-size:12px;">© MZE Cellular</small>
  </aside>

  <main class="main">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
      <div class="mb-2">
        <h2 class="page-title">Attendance Dashboard</h2>
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

    <!-- Filters under the title -->
    <div class="mb-3">
      <div class="filters">
        <select id="branchSelect" class="form-select" style="width:200px">
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
  <input id="fromDate" type="date" class="form-control" style="width:180px" />
  <span class="text-muted">to</span>
  <input id="toDate" type="date" class="form-control" style="width:180px" />
        <button id="refreshBtn" class="btn btn-primary-custom">Refresh</button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="row summary-row g-3 mb-4">
      <div class="col-sm-6 col-md-4 col-lg-2"><div class="stat-card"><div class="stat-label">Total Account Executives</div><div class="stat-value" id="statTotal">-</div></div></div>
      <div class="col-sm-6 col-md-4 col-lg-2"><div class="stat-card"><div class="stat-label">Present</div><div class="stat-value" id="statPresent">-</div></div></div>
      <div class="col-sm-6 col-md-4 col-lg-2"><div class="stat-card"><div class="stat-label">Late</div><div class="stat-value" id="statLate">-</div></div></div>
      <div class="col-sm-6 col-md-4 col-lg-2"><div class="stat-card"><div class="stat-label">Absent</div><div class="stat-value" id="statAbsent">-</div></div></div>
      <div class="col-sm-6 col-md-4 col-lg-2"><div class="stat-card"><div class="stat-label">Overtime Hours</div><div class="stat-value" id="statOT">-</div></div></div>
      <div class="col-sm-6 col-md-4 col-lg-2"><div class="stat-card"><div class="stat-label">Attendance Rate</div><div class="stat-value" id="statRate">-</div></div></div>
    </div>

    <!-- Charts and logs copied from HTML version -->
    <div class="row g-3 mb-4 align-items-stretch">
      <div class="col-lg-8 d-flex">
        <div class="card h-100 w-100">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div style="font-weight:700">Attendance Trend</div>
            <div style="font-size:13px;color:var(--text-light)">Shows present/late/absent by date</div>
          </div>
          <div class="chart-container">
            <canvas id="trendChart"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-4 d-flex">
        <div class="card h-100 w-100 breakdown-card">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div style="font-weight:700">Status Breakdown</div>
            <div style="font-size:13px;color:var(--text-light)">Percentages</div>
          </div>
          <div class="breakdown-chart-wrapper"><canvas id="breakdownChart"></canvas></div>
          <div class="breakdown-legend" id="breakdownLegend">
            <div><span class="legend-dot" style="background:#4a00e0"></span> Present</div>
            <div><span class="legend-dot" style="background:#ffa500"></span> Late</div>
            <div><span class="legend-dot" style="background:#0dcaf0"></span> Under Hours</div>
            <div><span class="legend-dot" style="background:#6c757d"></span> On Leave</div>
            <div><span class="legend-dot" style="background:#dc3545"></span> Absent</div>
            <div><span class="legend-dot" style="background:#343a40"></span> Terminated</div>
            <div><span class="legend-dot" style="background:#6610f2"></span> Suspended</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div style="font-weight:700">Recent Logs</div>
        <div style="font-size:13px;color:var(--text-light)">All logs for the selected range</div>
      </div>
      <div style="overflow:auto">
        <table class="table table-borderless" id="logsTable">
          <thead><tr><th>Account Executives</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Total Hours</th><th>Status</th><th>Remarks</th></tr></thead>
          <tbody id="logsBody"><tr><td colspan="7" class="text-center text-muted">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>

  </main>

<script>
function toISO(d){ return new Date(d).toISOString().slice(0,10); }
function formatPHTime(dt){ if(!dt) return '—'; try { return new Date(dt.replace(' ','T')).toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});}catch{return dt;} }
function formatPHDate(d){ try { return new Date(d).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'2-digit'});}catch{return d;} }
// Date utilities
const today = new Date().toISOString().slice(0,10);
function firstOfMonth(d){ const dt=new Date(d||today); return `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}-01`; }
function rangeFromInputs(){
  const f = document.getElementById('fromDate').value || firstOfMonth();
  const t = document.getElementById('toDate').value || today;
  return (new Date(f) <= new Date(t)) ? { start:f, end:t } : { start:t, end:f };
}
// Set default range (this month to today)
document.getElementById('fromDate').value = firstOfMonth();
document.getElementById('toDate').value = today;

async function fetchDashboard(){
  const branch = document.getElementById('branchSelect').value || '';
  const rng = rangeFromInputs();
  const params = new URLSearchParams({ from_date: rng.start, to_date: rng.end });
  if (branch) {
    if (branch === 'Cainta/Antipolo') {
      params.set('branch_locations', ['Cainta','Antipolo'].join(','));
    } else if (branch === 'Paranaque') {
      params.set('branch_locations', ['Paranaque','Parañaque'].join(','));
    } else {
      params.set('branch', branch);
    }
  }
  const res = await fetch(`../backend/get_attendance_dashboard.php?${params.toString()}`);
  return res.json();
}
function renderSummary(s){ document.getElementById('statTotal').textContent=s.total_employees??0; document.getElementById('statPresent').textContent=s.present??0; document.getElementById('statLate').textContent=s.late??0; document.getElementById('statAbsent').textContent=s.absent??0; document.getElementById('statOT').textContent=(s.overtime_hours??0)+' hrs'; document.getElementById('statRate').textContent=(s.attendance_rate??0)+'%'; }

// Build trend strictly from attendance_logs rows (no synthetic absents)
function buildTrendFromLogs(allLogs, meta){
  const out = [];
  if(!meta?.from || !meta?.to){ return out; }
  const cutoff = meta.cutoff || '08:15:00';
  // Build date index
  const start = new Date(meta.from);
  const end   = new Date(meta.to);
  for(let d=new Date(start); d<=end; d.setDate(d.getDate()+1)){
    out.push({ date:d.toISOString().slice(0,10), present:0, late:0, absent:0, underhours:0, onleave:0 });
  }
  const idx = Object.fromEntries(out.map((r,i)=>[r.date,i]));
  (allLogs||[]).forEach(r=>{
    const i = idx[r.date]; if(i===undefined) return;
    // If employee is marked On Leave, count as onleave and skip other buckets
    if (r.employee_status && String(r.employee_status).toLowerCase() === 'on leave') { out[i].onleave += 1; return; }
    const ci = r.check_in || r.first_in || null;
    // Compute hours if possible (either provided or derived from first_in/last_out)
    const hours = (r.total_hours!=null)? Number(r.total_hours) : (r.last_out && ci ? (new Date(r.last_out.replace(' ','T')) - new Date(ci.replace(' ','T')))/3600000 : null);
    if(!ci){
      // No check-in at all -> skip
      return;
    }
    const empCutoff = (r.shift_start) ? r.shift_start : cutoff;
    const t = (typeof ci === 'string') ? ci.substring(11,19) : '';
    if (hours !== null && !isNaN(hours)) {
      if(hours < 8){
        // Track Under Hours separately without affecting present/late counts
        out[i].underhours += 1;
        return;
      }
      // Have hours and >= 8 => decide Late vs Present
      if(t && empCutoff && t > empCutoff){ out[i].late += 1; } else { out[i].present += 1; }
    } else {
      // No checkout yet: count the time-in as present/late according to shift start so trend reflects remarks
      if(t && empCutoff && t > empCutoff){ out[i].late += 1; } else { out[i].present += 1; }
    }
  });
  return out;
}
let trendChartRef=null; function renderTrend(trend, meta){
  // If no trend rows, render an empty chart for the selected range
  let rows = Array.isArray(trend) ? trend.slice() : [];
  if(!rows.length && meta && meta.from && meta.to){
    const start = new Date(meta.from); const end = new Date(meta.to);
    for(let d=new Date(start); d<=end; d.setDate(d.getDate()+1)){
      rows.push({ date:d.toISOString().slice(0,10), present:0, late:0, absent:0 });
    }
  }
  const labels=(rows||[]).map(t=>t.date);
  const present=(rows||[]).map(t=>t.present||0);
  const late=(rows||[]).map(t=>t.late||0);
  const absent=(rows||[]).map(t=>t.absent||0);
  const under=(rows||[]).map(t=>t.underhours||0);
  const dayoff=(rows||[]).map(t=>t.dayoff||0);
  const canvas = document.getElementById('trendChart');
  const ctx=canvas.getContext('2d');
  if(trendChartRef) trendChartRef.destroy();
  trendChartRef=new Chart(ctx,{ type:'bar', data:{ labels, datasets:[ {label:'Present',data:present,backgroundColor:'rgba(74,0,224,0.9)',stack:'stack'},{label:'Late',data:late,backgroundColor:'rgba(255,165,0,0.95)',stack:'stack'},{label:'Under Hours',data:under,backgroundColor:'rgba(13,202,240,0.95)',stack:'stack'},{label:'On Leave',data:(rows||[]).map(t=>t.onleave||0),backgroundColor:'rgba(108,117,125,0.85)',stack:'stack'},{label:'Absent',data:absent,backgroundColor:'rgba(220,53,69,0.95)',stack:'stack'},{label:'Day Off',data:dayoff,backgroundColor:'rgba(169,169,169,0.35)',stack:'stack'} ] }, options:{ responsive:true, plugins:{ legend:{ display:true, position:'top' } }, scales:{ x:{ stacked:true }, y:{ stacked:true, beginAtZero:true } } } }); }
let breakdownRef=null; function renderBreakdown(b){ const ctx=document.getElementById('breakdownChart').getContext('2d'); if(breakdownRef) breakdownRef.destroy(); const labels=['Present','Late','Under Hours','On Leave','Absent','Terminated','Suspended']; const data=[b.present_pct||0,b.late_pct||0,b.under_pct||0,b.onleave_pct||0,b.absent_pct||0,b.terminated_pct||0,b.suspended_pct||0]; const colors=['#4a00e0','#ffa500','#0dcaf0','#6c757d','#dc3545','#343a40','#6610f2']; breakdownRef=new Chart(ctx,{ type:'doughnut', data:{ labels, datasets:[{ data, backgroundColor:colors, borderWidth:0, hoverOffset:10, cutout:'65%' }] }, options:{ plugins:{ legend:{ display:false } }, maintainAspectRatio:false } }); }
function statusBadge(status){ const s=(status||'').toLowerCase(); if(s==='late') return '<span class="badge-late">Late</span>'; if(s==='absent') return '<span class="badge-absent">Absent</span>'; if(s==='under hours') return '<span class="badge-under">Under Hours</span>'; if(s==='present') return '<span class="badge-present">Present</span>'; if(s==='on leave') return '<span class="badge-leave">On Leave</span>'; return '<span class="badge-present">Present</span>'; }
function renderLogs(rows){ const tbody=document.getElementById('logsBody'); if(!rows||rows.length===0){ tbody.innerHTML = "<tr><td colspan='7' class='text-center text-muted'>No recent logs</td></tr>"; return; } tbody.innerHTML = rows.map(r=>{ const emp = `<strong>${r.employee_name||'-'}</strong><div style='font-size:12px;color:var(--text-light)'>${r.employee_id||''}</div>`; const st=statusBadge(r.status); const th=(r.total_hours==null||r.total_hours===undefined)?'—':Number(r.total_hours).toFixed(2); const dayoffNoWork = (parseInt(r.is_dayoff||0)===1) && (!r.check_in); const remarks = dayoffNoWork ? 'Day Off' : (r.remarks||'-'); return `<tr><td>${emp}</td><td>${formatPHDate(r.date||'')}</td><td>${formatPHTime(r.check_in||'')}</td><td>${formatPHTime(r.check_out||'')}</td><td>${th}</td><td>${st}</td><td>${remarks}</td></tr>`; }).join(''); }
async function refresh(){
  try{
    const data=await fetchDashboard();
    if(!data.success){ console.warn(data); return; }
    renderSummary(data.summary||{});
    const allLogs = (data.all_logs&&data.all_logs.length? data.all_logs : []);
    const logsCount = allLogs.length || (data.recent_logs?data.recent_logs.length:0);
  const trendFromDb = buildTrendFromLogs(allLogs, data.meta || {});
  // Combine DB-only present/late/underhours with backend dayoff, absent and onleave counts for display
  const byDate = Object.fromEntries((data.trend||[]).map(t=>[t.date, {dayoff:(t.dayoff||0), absent:(t.absent||0), onleave:(t.onleave||0)}]));
  const mergedTrend = (trendFromDb||[]).map(r=>({
    ...r,
    dayoff: (byDate[r.date]?.dayoff)||0,
    absent: (byDate[r.date]?.absent)||0,
    onleave: (byDate[r.date]?.onleave)||0
  }));
    renderTrend(mergedTrend, data.meta || {});

    // Use backend-provided breakdown which now includes onleave, terminated, suspended
    renderBreakdown(data.breakdown || {});
    renderLogs((data.all_logs&&data.all_logs.length?data.all_logs:null) || data.recent_logs || []);
  }catch(e){ console.error(e); }
}

document.getElementById('refreshBtn').addEventListener('click', refresh);
document.getElementById('branchSelect').addEventListener('change', refresh);
document.getElementById('fromDate').addEventListener('change', refresh);
document.getElementById('toDate').addEventListener('change', refresh);
refresh();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
