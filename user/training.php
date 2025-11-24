<?php
require_once __DIR__ . '/../backend/session_user.php';
require_auth();
if ($_SESSION['role'] !== 'account_executive') { header('Location: ../admin/notices.php'); exit; }
$me = session_employee($conn);
$displayName = $me['full_name'] ?? ($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'User'));
$avatarPath = !empty($me['profile_img']) ? ('../uploads/' . $me['profile_img']) : 'https://via.placeholder.com/64?text=U';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MZE Cellular</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/training.css" rel="stylesheet" />
</head>
<body>
  <div class="sidebar">
    <h2>MZE Cellular</h2>
    <a class="nav-link" href="main_dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a>
    <a class="nav-link" href="evaluation.php"><i class="fa-solid fa-clipboard-list me-2"></i>Monthly Performance</a>
    <a class="nav-link" href="attendance_record.php"><i class="fa-solid fa-user-check me-2"></i>Attendance</a>
    <a class="nav-link" href="notification.php"><i class="fa-solid fa-bullhorn me-2"></i>Notification</a>
    <a class="nav-link active" href="training.php"><i class="fa-solid fa-graduation-cap me-2"></i>Training</a>
    <a class="nav-link" href="profile.php"><i class="fa-solid fa-user-circle me-2"></i>Profile</a>
    <a class="nav-link text-danger" href="logout.php" style="position:absolute;bottom:60px;left:20px;"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a>
    <small style="position:absolute;bottom:20px;left:20px;opacity:0.8;font-size:12px;">© MZE Cellular</small>
  </div>

  <div class="content">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div>
        <h4 class="fw-bold mb-1">Training Recommendations</h4>
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
    <div class="main-card">
      <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="fw-semibold mb-0">Suggested Trainings</h5>
          <small class="text-muted">System-generated suggestions</small>
        </div>
        <div id="trainingList" class="mt-2">
          <div class="text-muted"><i class="bi bi-arrow-repeat me-2"></i>Loading recommendations...</div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function escapeHtml(str){ if(str===null||str===undefined) return ''; return str.replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }
    function formatDate(d){ if(!d) return 'TBA'; try{ const dt=new Date(d+'T00:00:00'); return dt.toLocaleDateString(undefined,{year:'numeric',month:'long',day:'numeric'});}catch(e){return d;} }
    async function fetchTrainings(){
      const list=document.getElementById('trainingList');
      try{ const r=await fetch('../backend/get_training_recommendations.php'); const j=await r.json();
        if(!j.success) throw new Error(j.message||'Failed');
        if(j.data.length===0){ list.innerHTML='<div class="text-muted">No active training recommendations.</div>'; return; }
        list.innerHTML='';
        j.data.forEach(t=>{
          const card=document.createElement('div'); card.className='training-card '+(t.status==='pending_delete'?'requested':''); card.setAttribute('data-id', t.id);
          card.innerHTML=`
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="training-title">${escapeHtml(t.title)}</span>
              <span class="training-date"><i class='bi bi-calendar2 me-1'></i>${formatDate(t.scheduled_date)}</span>
            </div>
            <div class="training-desc">${escapeHtml(t.description||'')}</div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <small class="training-meta"><i class='bi bi-info-circle me-1'></i>Source: ${escapeHtml(t.source||'evaluation-auto')}</small>
              ${t.status==='pending_delete' ? '<button class="btn-pending" disabled>Pending Delete</button>' : '<button class="btn-request" data-action="delete">Request Delete</button>'}
            </div>`;
          list.appendChild(card);
        });
      }catch(err){ list.innerHTML='<div class="text-danger">Failed to load trainings</div>'; }
    }
    async function requestDelete(id,btn){
      if(!confirm('Send delete request for this training?')) return;
      const fd=new FormData(); fd.append('training_id',id);
      btn.disabled=true; btn.textContent='Sending...';
      try{ const r=await fetch('../backend/request_delete_training.php',{method:'POST',body:fd}); const j=await r.json(); if(!j.success) throw new Error(j.message||'Failed'); fetchTrainings(); }catch(err){ alert('Delete request failed: '+err.message); btn.disabled=false; btn.textContent='Request Delete'; }
    }
    document.addEventListener('DOMContentLoaded',()=>{
      fetchTrainings();
      document.getElementById('trainingList').addEventListener('click',e=>{
        if(e.target.matches('button[data-action="delete"]')){
          const card=e.target.closest('.training-card'); requestDelete(card.getAttribute('data-id'), e.target);
        }
      });
    });
  </script>
</body>
</html>