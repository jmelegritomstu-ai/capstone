<?php
require_once __DIR__ . '/../backend/session_user.php';
require_auth();
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/attendance_record.css" rel="stylesheet" />
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <h2>MZE Cellular</h2>
    <a class="nav-link" href="main_dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a>
    <a class="nav-link" href="evaluation.php"><i class="fa-solid fa-clipboard-list me-2"></i>Monthly Performance</a>
    <a class="nav-link active" href="attendance_record.php"><i class="fa-solid fa-user-check me-2"></i>Attendance</a>
    <a class="nav-link" href="notification.php"><i class="fa-solid fa-bullhorn me-2"></i>Notification</a>
    <a class="nav-link" href="training.php"><i class="fa-solid fa-graduation-cap me-2"></i>Training</a>
    <a class="nav-link" href="profile.php"><i class="fa-solid fa-user-circle me-2"></i>Profile</a>
    <a class="nav-link text-danger" href="logout.php" style="position:absolute;bottom:60px;left:20px;"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a>
    <small style="position:absolute;bottom:20px;left:20px;opacity:0.8;font-size:12px;">© MZE Cellular</small>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="header">
      <div>
        <h4><i class="bi bi-calendar-check"></i> Attendance Records</h4>
        <p class="text-muted mb-0">Your last 30 days of attendance with total hours and automated remarks.</p>
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

    <div class="card p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">My Attendance</h5>
        <small class="text-muted">Timezone: Asia/Manila</small>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th style="width:120px">Date</th>
              <th>Branch</th>
              <th>Location</th>
              <th style="width:120px">Time In</th>
              <th style="width:120px">Time Out</th>
              <th style="width:120px">Total Hours</th>
              <th style="width:140px">Remarks</th>
            </tr>
          </thead>
          <tbody id="attBody">
            <tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Floating Time In/Out Buttons -->
  <div id="fabToast" class="fab-toast" role="status" aria-live="polite"></div>
  <div class="fab-container" aria-label="Attendance quick actions">
    <button id="btnTimeOut" type="button" class="btn btn-outline-secondary">
      <i class="bi bi-box-arrow-right me-1"></i> Time Out
    </button>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    async function fetchHistory(){
      const fd = new FormData(); fd.append('op','history_self');
      const res = await fetch('../backend/attendance_api.php',{method:'POST', body:fd});
      return res.json();
    }
    function fmtDate(d){ try { return new Date(d).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'2-digit'}); } catch { return d; } }
    function fmtTime(dt){ if(!dt) return '-'; try { return new Date(dt.replace(' ','T')).toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}); } catch { return dt; } }
    function badge(remarks){
      const m = (remarks||'').toLowerCase();
      let cls = 'badge-soft badge-ok';
      if(m.includes('under')||m.includes('half')) cls='badge-soft badge-warn';
      if(m.includes('absent')||m.includes('no time out')) cls='badge-soft badge-err';
      if(m.includes('day off (worked)')) cls='badge-soft badge-dayoff-worked';
      return `<span class="${cls}">${remarks||'-'}</span>`;
    }
    function mostRecentOpen(rows){
      const open = (rows||[]).filter(r=>!r.check_out);
      if(open.length===0) return null;
      return open.reduce((a,b)=>{
        const da = new Date(String(a.check_in).replace(' ','T'));
        const db = new Date(String(b.check_in).replace(' ','T'));
        return db>da?b:a;
      });
    }
    function setFabState(rows){
      const open = mostRecentOpen(rows);
      const outBtn = document.getElementById('btnTimeOut');
      const inBtn = document.getElementById('btnTimeIn'); // may be null after removal
      if(!outBtn) return;
      if(inBtn){
        // Original dual-button logic retained if Time In is re-added later
        if(open){
          inBtn.classList.remove('btn-primary');
          inBtn.classList.add('btn-outline-secondary');
          inBtn.disabled = true;
          outBtn.classList.remove('btn-outline-secondary');
          outBtn.classList.add('btn-danger');
          outBtn.disabled = false;
        }else{
          inBtn.classList.add('btn-primary');
          inBtn.classList.remove('btn-outline-secondary');
          inBtn.disabled = false;
          outBtn.classList.add('btn-outline-secondary');
          outBtn.classList.remove('btn-danger');
          outBtn.disabled = true;
        }
      } else {
        // Single-button mode: Time Out only. Enable when open record exists.
        if(open){
          outBtn.classList.remove('btn-outline-secondary');
          outBtn.classList.add('btn-danger');
          outBtn.disabled = false;
        } else {
          outBtn.classList.add('btn-outline-secondary');
          outBtn.classList.remove('btn-danger');
          outBtn.disabled = true;
        }
      }
    }
    function showToast(msg){
      const el = document.getElementById('fabToast');
      el.textContent = msg;
      el.classList.add('show');
      setTimeout(()=>el.classList.remove('show'), 1800);
    }
    async function renderHistory(){
      const tbody = document.getElementById('attBody');
      try{
        const data = await fetchHistory();
        if(!data.success){ tbody.innerHTML = `<tr><td colspan='7' class='text-center text-danger'>${data.message||'Failed to load history'}</td></tr>`; return; }
        const rows = data.history||[];
        // userSummary replaced by dropdown; keep values for potential tooltip
        if(rows.length===0){ tbody.innerHTML = "<tr><td colspan='7' class='text-center text-muted'>No records</td></tr>"; setFabState(rows); return; }
        tbody.innerHTML = rows.map(r=> {
          const remarksHtml = (r.remarks||'').toLowerCase().includes('day off (worked)')
            ? badge(r.remarks).replace(/<span class=\"([^\"]*)\">(.*?)<\/span>/, '<span class="$1">$2</span>') // preserve structure
            : badge(r.remarks);
          return `<tr>
            <td>${fmtDate(r.date)}</td>
            <td>${r.branch_name||'-'}</td>
            <td>${r.branch_location||'-'}</td>
            <td>${fmtTime(r.check_in)}</td>
            <td>${fmtTime(r.check_out)}</td>
            <td>${(r.total_hours??null)===null?'-':Number(r.total_hours).toFixed(2)}</td>
            <td>${remarksHtml}</td>
          </tr>`;
        }).join('');
        setFabState(rows);
      }catch(err){
        console.error(err);
        tbody.innerHTML = "<tr><td colspan='7' class='text-center text-danger'>Error loading history</td></tr>";
      }
    }
    async function logAction(action){
      // In single-button mode we only allow 'out'
      if(action === 'in') return; // ignored when Time In removed
      const outBtn = document.getElementById('btnTimeOut');
      if(outBtn) outBtn.disabled = true;
      try{
        const fd = new FormData();
        fd.append('op','log_self');
        fd.append('action', action);
        const res = await fetch('../backend/attendance_api.php', { method:'POST', body: fd });
        const data = await res.json();
        if(data.success){
          showToast('Timed out successfully');
          await renderHistory();
        }else{
          showToast(data.message || 'Operation failed');
        }
      }catch(e){
        console.error(e);
        showToast('Network error');
      }finally{ if(outBtn) outBtn.disabled = false; }
    }
    const outBtnOnly = document.getElementById('btnTimeOut');
    if(outBtnOnly) outBtnOnly.addEventListener('click', ()=>logAction('out'));
    // Initial load
  renderHistory();

  // Client-side guard: no additional logic needed for single daily attendance
  // backend blocks second time-in; here we only time-out open records.
  </script>
</body>
</html>
