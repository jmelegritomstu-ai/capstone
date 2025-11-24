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
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>MZE Cellular</title>
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Icons -->
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- Custom CSS -->
  <link href="../assets/css/notification.css" rel="stylesheet" />
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <h2>MZE Cellular</h2>
    <a class="nav-link" href="main_dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a>
    <a class="nav-link" href="evaluation.php"><i class="fa-solid fa-clipboard-list me-2"></i>Monthly Performance</a>
    <a class="nav-link" href="attendance_record.php"><i class="fa-solid fa-user-check me-2"></i>Attendance</a>
    <a class="nav-link active" href="notification.php"><i class="fa-solid fa-bullhorn me-2"></i>Notification</a>
    <a class="nav-link" href="training.php"><i class="fa-solid fa-graduation-cap me-2"></i>Training</a>
    <a class="nav-link" href="profile.php"><i class="fa-solid fa-user-circle me-2"></i>Profile</a>
    <a class="nav-link text-danger" href="logout.php" style="position:absolute;bottom:60px;left:20px;"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a>
    <small style="position:absolute;bottom:20px;left:20px;opacity:0.8;font-size:12px;">© MZE Cellular</small>
  </div>

  <!-- Main Content -->
  <div class="main-content">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h4 class="fw-bold mb-1">Notifications</h4>
          <p class="text-muted mb-0">Here are the latest notices and announcements for your branch.</p>
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
        

        <!-- Notifications List -->
        <div class="mt-4" id="notifList" aria-live="polite">
          <div class="text-muted"><i class="bi bi-arrow-repeat me-2"></i>Loading notices...</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Notice View Modal -->
  <div class="modal fade" id="noticeViewModal" tabindex="-1" aria-labelledby="noticeViewLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header" style="background:linear-gradient(135deg,#5f30e2,#7c4dff);color:#fff;">
          <h5 class="modal-title" id="noticeViewLabel"><i class="bi bi-bell me-2"></i>Notice Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="noticeMeta" class="mb-3 small text-muted"></div>
          <h4 id="noticeTitle" class="fw-semibold mb-2"></h4>
          <div id="noticeCategory" class="mb-3"></div>
          <p id="noticeDescription" class="lead" style="font-size:1rem;line-height:1.5;"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function escapeHtml(str){ if(str===null||str===undefined) return ''; return str.replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }
    function formatDate(d){ try { const dt=new Date(d+'T00:00:00'); return dt.toLocaleDateString(undefined,{year:'numeric',month:'long',day:'numeric'});} catch(e){return d;} }
    async function loadNotices(){
      const wrap=document.getElementById('notifList');
      try{ const r=await fetch('../backend/get_notices.php'); const j=await r.json();
        if(!j.success) throw new Error(j.message||'Failed');
        if(j.data.length===0){ wrap.innerHTML='<div class="text-muted">No notices yet.</div>'; return; }
        wrap.innerHTML='';
        j.data.forEach(n=>{
          const card=document.createElement('div'); card.className='notification-card'; card.tabIndex=0; card.setAttribute('role','button'); card.dataset.notice=JSON.stringify(n);
          const categoryMap={positive:'Good Performance', warning:'Warning', announcement:'Announcement'};
          const type=categoryMap[n.category]||n.notice_type;
          card.innerHTML=`<div class="d-flex justify-content-between align-items-center mb-2">
              <span class="notif-type">${escapeHtml(type)}</span>
              <span class="notif-date">${formatDate(n.date_issued)}</span>
            </div>
            <div class="notif-title">${escapeHtml(n.notice_type)}</div>
            <div class="notif-message text-truncate" style="max-width:100%;">${escapeHtml(n.description)}</div>
            <div class='mt-2'><a href="#" class="small text-decoration-none" data-action="view">View full details <i class="bi bi-arrow-right-short"></i></a></div>`;
          wrap.appendChild(card);
        });
      }catch(err){ wrap.innerHTML='<div class="text-danger">Failed to load notices</div>'; }
    }
    function openNoticeModal(notice){
      const modalEl=document.getElementById('noticeViewModal');
      document.getElementById('noticeTitle').textContent=notice.notice_type||'';
      const categoryBadge = notice.category ? `<span class="badge rounded-pill ${notice.category==='positive'?'bg-success':(notice.category==='warning'?'bg-danger':'bg-primary')}">${escapeHtml(notice.category)}</span>` : '';
      document.getElementById('noticeCategory').innerHTML=categoryBadge;
      document.getElementById('noticeDescription').textContent=notice.description||'';
      document.getElementById('noticeMeta').textContent=`Issued: ${formatDate(notice.date_issued)} • From: ${notice.issued_by? escapeHtml(notice.issued_by):'Admin'}${notice.employee_name? '' : ''}`;
      const m=new bootstrap.Modal(modalEl); m.show();
    }
    document.addEventListener('DOMContentLoaded', ()=>{
      loadNotices();
      document.getElementById('notifList').addEventListener('click', e=>{
        const card = e.target.closest('.notification-card');
        if (!card) return;
        e.preventDefault();
        try { const data = JSON.parse(card.dataset.notice); openNoticeModal(data); } catch(_) {}
      });
      document.getElementById('notifList').addEventListener('keydown', e=>{
        if(e.key==='Enter'){ const card=e.target.closest('.notification-card'); if(card){ try{ const data=JSON.parse(card.dataset.notice); openNoticeModal(data);}catch(_){} } }
      });
    });
  </script>
</body>
</html>