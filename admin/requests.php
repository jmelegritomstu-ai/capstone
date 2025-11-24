<?php
require_once __DIR__ . '/../backend/session_user.php';
require_auth();
// Provide dropdown user info (same pattern as sales.php)
$me = session_employee($conn);
$displayName = $me['full_name'] ?? ($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'User'));
$avatarPath = !empty($me['profile_img']) ? ('../uploads/' . $me['profile_img']) : 'https://via.placeholder.com/64?text=U';
// Keep auditor gate if needed for access control
if ($_SESSION['role'] !== 'auditor') { header('Location: ../user/training.php'); exit; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MZE Cellular</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/requests.css" rel="stylesheet" />
</head>
<body>
  <!-- Sidebar (same design as employees.php) -->
  <div class="sidebar">
    <h2>MZE Cellular</h2>
    <a class="nav-link" href="dashboard.php">
      <i class="fa-solid fa-gauge me-2"></i>Dashboard
    </a>
    <a class="nav-link" href="sales.php">
      <i class="fas fa-chart-line me-2"></i>Sales Performance
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
    <a class="nav-link active" href="requests.php"><i class="fa-solid fa-chalkboard-user"></i>Training Requests</a>
    <a class="nav-link" href="profile.php">
      <i class="fa-solid fa-user-circle me-2"></i>Profile
    </a>
    <a class="nav-link text-danger" href="logout.php" style="position:absolute;bottom:60px;left:20px;">
      <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
    </a>
    <small style="position:absolute;bottom:20px;left:20px;opacity:0.8;font-size:12px;">© MZE Cellular</small>
  </div>

  <div class="main">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
      <div class="mb-2">
        <h2 class="page-title">Training Requests</h2>
        <p class="text-muted mb-0">Approve or reject training deletion requests from Account Executives</p>
      </div>
      <div class="dropdown user-menu mb-2">
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

    <div id="alertBox" style="max-width:640px;"></div>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0 align-middle" id="requestsTable">
            <thead>
              <tr>
                <th>Account Executive</th>
                <th>Training Title</th>
                <th>Scheduled Date</th>
                <th>Requested At</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody id="requestsTbody">
              <tr><td colspan="6" class="text-center text-muted py-4"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function escapeHtml(str){return (str||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));}
    function fmtDate(d){if(!d) return '—';try{const dt=new Date(d+'T00:00:00');return dt.toLocaleDateString(undefined,{year:'numeric',month:'short',day:'numeric'});}catch(e){return d;}}
    function pushAlert(msg,type='success',timeout=3500){const box=document.getElementById('alertBox');const div=document.createElement('div');div.className='alert alert-'+type+' alert-dismissible fade show';div.innerHTML=escapeHtml(msg)+'<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';box.appendChild(div);if(timeout) setTimeout(()=>{div.classList.remove('show');setTimeout(()=>div.remove(),400);},timeout);}

    async function loadRequests(){
      const tbody=document.getElementById('requestsTbody');
      try{
        const r=await fetch('../backend/get_training_delete_requests.php');
        const j=await r.json();
        if(!j.success) throw new Error(j.message||'Failed to load requests');
        
        if(j.data.length===0){
          tbody.innerHTML='<tr><td colspan="6" class="text-center text-muted py-4">No pending training deletion requests</td></tr>';
          return;
        }
        
        tbody.innerHTML='';
        j.data.forEach(req=>{
          const tr=document.createElement('tr');
          tr.className='request-row';
          const badgeClass = req.request_status==='pending'?'badge-pending':(req.request_status==='approved'?'badge-approved':'badge-rejected');
          tr.innerHTML=`
            <td>
              <strong>${escapeHtml(req.full_name||req.emp_id)}</strong><br>
              <small class='text-muted'>${escapeHtml(req.branch_name||'')}</small>
            </td>
            <td>${escapeHtml(req.title)}</td>
            <td>${fmtDate(req.scheduled_date)}</td>
            <td>${fmtDate(req.requested_at.substring(0,10))}</td>
            <td><span class='badge-status ${badgeClass}'>${escapeHtml(req.request_status)}</span></td>
            <td class='text-end'>
              ${req.request_status==='pending' ? `
                <div class='btn-group btn-group-sm'>
                  <button class='btn btn-outline-success' data-action='approve' data-id='${req.request_id}'>Approve</button>
                  <button class='btn btn-outline-danger' data-action='reject' data-id='${req.request_id}'>Reject</button>
                </div>
              `: '<span class="text-muted">—</span>'}
            </td>`;
          tbody.appendChild(tr);
        });
      }catch(err){
        console.error('Error loading requests:', err);
        tbody.innerHTML='<tr><td colspan="6" class="text-danger py-4">Failed to load requests: ' + escapeHtml(err.message) + '</td></tr>';
      }
    }

    async function setRequest(id,action){
      const fd=new FormData();
      fd.append('request_id',id);
      fd.append('action',action);
      
      try{
        const r=await fetch('../backend/set_training_delete_request_status.php',{
          method:'POST',
          body:fd
        });
        const j=await r.json();
        
        if(!j.success) throw new Error(j.message||'Failed to process request');
        
        pushAlert(`Training deletion request ${action}d successfully`);
        loadRequests();
      }catch(err){
        console.error('Error setting request status:', err);
        pushAlert('Error: '+err.message,'danger',5000);
      }
    }

    // Event delegation for dynamically loaded buttons
    document.addEventListener('click', function(e) {
      // Check if the clicked element is an approve/reject button
      if (e.target.matches('button[data-action]')) {
        const id = e.target.getAttribute('data-id');
        const action = e.target.getAttribute('data-action');
        
        // Confirm action
        const actionText = action === 'approve' ? 'approve' : 'reject';
        if (!confirm(`Are you sure you want to ${actionText} this training deletion request?`)) {
          return;
        }
        
        setRequest(id, action);
      }
    });

    // Load requests when page loads
    document.addEventListener('DOMContentLoaded', function() {
      loadRequests();
    });
  </script>
</body>
</html>