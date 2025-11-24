<?php
require_once __DIR__ . '/../backend/session_user.php';
require_auth();
if ($_SESSION['role'] !== 'auditor') { header('Location: ../user/notification.php'); exit; }
// Provide user dropdown context similar to sales.php
$me = session_employee($conn);
$displayName = $me['full_name'] ?? ($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'User'));
$avatarPath = !empty($me['profile_img']) ? ('../uploads/' . basename($me['profile_img'])) : 'https://via.placeholder.com/64?text=U';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MZE Cellular</title>
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome (icons) -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link href="../assets/css/notices.css" rel="stylesheet">
</head>

<body>
  <!-- Sidebar (auditor) -->
  <aside class="sidebar">
    <h2>MZE Cellular</h2>

    <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a>
    <a class="nav-link" href="sales.php"><i class="fa-solid fa-chart-line me-2"></i>Sales Performance</a>
    <a class="nav-link" href="attendance.php"><i class="fa-solid fa-user-check me-2"></i>Attendance</a>
    <a class="nav-link" href="employees.php"><i class="fa-solid fa-users me-2"></i>People</a>
    <a class="nav-link" href="forms.php"><i class="fa-solid fa-file-pen me-2"></i>Forms / Evaluations</a>
    <a class="nav-link active" href="notices.php"><i class="fa-solid fa-bullhorn me-2"></i>Notices</a>
    <a class="nav-link " href="requests.php"><i class="fa-solid fa-chalkboard-user"></i>Training Requests</a>
    <a class="nav-link" href="profile.php"><i class="fa-solid fa-user-circle me-2"></i>Profile</a>

    <a class="nav-link text-danger" href="logout.php" style="position:absolute;bottom:60px;left:20px;">
      <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
    </a>

    <small style="position:absolute;bottom:20px;left:20px;opacity:0.8;font-size:12px;">
      © MZE Cellular
    </small>
  </aside>

  <!-- Content -->
  <div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
      <div class="mb-2">
        <h3>Notices</h3>
        <p class="text-muted">Manage employee warnings and penalties efficiently.</p>
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

    <div id="alertContainer" style="max-width:600px;"></div>

    <!-- Notices Table -->
    <div class="card shadow-sm border-0 rounded-3">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table align-middle" id="noticesTable">
            <thead>
              <tr>
                <th>Account Executive</th>
                <th>Employee ID</th>
                <th>Date Issued</th>
                <th>Type</th>
                <th>Description</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody id="noticesTbody">
              <tr><td colspan="6" class="text-center text-muted py-4">
                <i class="fa-solid fa-spinner fa-spin me-2"></i> Loading notices...
              </td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Floating Send Notice button -->
  <button class="fab-send-notice" data-bs-toggle="modal" data-bs-target="#sendNoticeModal" title="Send New Notice">
    <i class="fa-solid fa-plus"></i>
    <span class="d-none d-sm-inline">Send Notice</span>
  </button>

  <!-- Elegant Send Notice Modal -->
  <div class="modal fade" id="sendNoticeModal" tabindex="-1" aria-labelledby="sendNoticeLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="sendNoticeLabel">
            <i class="fa-solid fa-paper-plane me-2"></i> Send New Notice
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <form id="noticeForm">
          <div class="modal-body px-4">
            <div class="mb-3">
              <label class="form-label fw-medium">Employee Name</label>
              <select class="form-select" name="employee_name" id="employeeSelect" required>
                <option value="">Loading...</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-medium">Employee ID</label>
              <input type="text" class="form-control" id="employeeId" name="employee_id" readonly>
            </div>

            <div class="mb-3">
              <label class="form-label fw-medium">Date Issued</label>
              <input type="date" class="form-control" name="date_issued" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="mb-3">
              <label class="form-label fw-medium">Notice Type</label>
              <select class="form-select" name="notice_type" id="noticeType" required>
                <option value="">Select Type</option>
                <optgroup label="Positive Recognition">
                  <option>Outstanding Performance</option>
                  <option>Excellent Customer Service</option>
                  <option>Perfect Attendance</option>
                  <option>Team Player Award</option>
                  <option>Sales Achievement</option>
                  <option>Employee of the Month</option>
                  <option>Positive Attitude Recognition</option>
                  <option>Leadership Recognition</option>
                  <option>Punctuality Award</option>
                  <option>Extra Effort Recognition</option>
                </optgroup>
                <optgroup label="Formal Notices & Warnings">
                  <option>Rate of Return Issue</option>
                  <option>Warning</option>
                  <option>Late Submission</option>
                  <option>Tardiness</option>
                  <option>Unprofessional Behavior</option>
                  <option>Missed Deadline</option>
                  <option>Unauthorized Absence</option>
                  <option>Negligence of Duty</option>
                  <option>Violation of Company Policy</option>
                  <option>Failure to Meet Sales Target</option>
                  <option>Misconduct</option>
                  <option>Disrespectful Behavior</option>
                </optgroup>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-medium">Description</label>
              <textarea class="form-control" name="description" rows="3" placeholder="Enter description..." required></textarea>
            </div>

            <!-- Warning notice for formal notices -->
            <div class="warning-notice" id="formalNoticeWarning" style="display: none;">
              <h6><i class="fa-solid fa-triangle-exclamation warning-icon"></i> Formal Notice</h6>
              <p>This notice will be recorded in the employee's permanent file and may impact their employment status.</p>
            </div>
          </div>

          <div class="modal-footer border-0 px-4 pb-4">
            <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary px-4 rounded-pill" id="submitButton">
              <i class="fa-solid fa-paper-plane me-1"></i> Send Notice
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    const alertContainer = document.getElementById('alertContainer');
    function pushAlert(msg,type='success',timeout=3500){
      const div=document.createElement('div');
      div.className=`alert alert-${type} alert-dismissible fade show`;
      div.innerHTML=`${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
      alertContainer.appendChild(div);
      if(timeout) setTimeout(()=>{div.classList.remove('show'); setTimeout(()=>div.remove(),400);},timeout);
    }

    async function loadEmployees(){
      const sel=document.getElementById('employeeSelect');
      try { const r=await fetch('../backend/get_employees.php'); const j=await r.json();
        sel.innerHTML='<option value="">Select Employee</option>';
        if(j.success){
          j.data.forEach(e=>{
            const o=document.createElement('option');
            o.value=e.full_name; o.dataset.id=e.emp_id; o.textContent=`${e.full_name} (ID: ${e.emp_id})`;
            sel.appendChild(o);
          });
        } else { sel.innerHTML='<option value="">Failed loading</option>'; }
      } catch(err){ sel.innerHTML='<option value="">Error</option>'; }
    }

    async function loadNotices(){
      const tbody=document.getElementById('noticesTbody');
      try { const r=await fetch('../backend/get_notices.php'); const j=await r.json();
        if(!j.success) throw new Error(j.message||'Failed');
        if(j.data.length===0){
          tbody.innerHTML='<tr><td colspan="6" class="text-center text-muted py-4"><i class="fa-solid fa-inbox fa-2x mb-3 d-block"></i>No notices found</td></tr>';
          return;
        }
        tbody.innerHTML='';
        j.data.forEach(n=>{
          const tr=document.createElement('tr');
          const badgeClass = n.category==='positive' ? 'badge-good' : (n.notice_type==='Warning' ? 'bg-warning text-dark' : (n.category==='announcement' ? 'bg-info text-dark':'bg-danger'));
          tr.innerHTML=`<td><strong>${escapeHtml(n.employee_name||'')}</strong></td>
            <td><span class='badge bg-light text-dark'>${escapeHtml(n.emp_id)}</span></td>
            <td>${escapeHtml(n.date_issued)}</td>
            <td><span class='badge ${badgeClass}'>${escapeHtml(n.notice_type)}</span></td>
            <td>${escapeHtml(n.description)}</td>
            <td class='text-end'><button class='btn btn-outline-danger btn-sm' data-id='${n.id}'>Delete</button></td>`;
          tbody.appendChild(tr);
        });
      } catch(err){
        tbody.innerHTML='<tr><td colspan="6" class="text-danger">Failed to load notices</td></tr>';
      }
    }

    function escapeHtml(str){ if(str===null||str===undefined) return ''; return str.replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }

    document.addEventListener('DOMContentLoaded',()=>{
      loadEmployees();
      loadNotices();
      const employeeSelect=document.getElementById('employeeSelect');
      const employeeId=document.getElementById('employeeId');
      const noticeType=document.getElementById('noticeType');
      const formalNoticeWarning=document.getElementById('formalNoticeWarning');
      const submitButton=document.getElementById('submitButton');
      const form=document.getElementById('noticeForm');
      const modalEl=document.getElementById('sendNoticeModal');
      const modal=new bootstrap.Modal(modalEl);

      employeeSelect.addEventListener('change',()=>{
        const opt=employeeSelect.options[employeeSelect.selectedIndex];
        employeeId.value=opt? (opt.dataset.id||''):'';
      });

      noticeType.addEventListener('change',()=>{
        const formal=[ 'Warning','Late Submission','Tardiness','Unprofessional Behavior','Missed Deadline','Unauthorized Absence','Negligence of Duty','Violation of Company Policy','Failure to Meet Sales Target','Misconduct','Disrespectful Behavior' ];
        if(formal.includes(noticeType.value)){
          formalNoticeWarning.style.display='block';
          submitButton.innerHTML='<i class="fa-solid fa-triangle-exclamation me-1"></i> Issue Formal Notice';
          submitButton.classList.remove('btn-primary'); submitButton.classList.add('btn-danger');
        } else {
          formalNoticeWarning.style.display='none';
          submitButton.innerHTML='<i class="fa-solid fa-paper-plane me-1"></i> Send Notice';
          submitButton.classList.remove('btn-danger'); submitButton.classList.add('btn-primary');
        }
      });

      form.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const fd=new FormData(form);
        fd.append('emp_id', fd.get('employee_id'));
        fd.append('employee_name', fd.get('employee_name'));
        submitButton.disabled=true; submitButton.textContent='Sending...';
        try {
          const r=await fetch('../backend/create_notice.php',{method:'POST',body:fd});
          const j=await r.json();
          if(!j.success) throw new Error(j.message||'Failed');
          pushAlert('Notice created successfully');
          form.reset(); employeeId.value='';
          modal.hide();
          loadNotices();
        } catch(err){ pushAlert('Failed: '+err.message,'danger',5000); }
        submitButton.disabled=false; submitButton.innerHTML='<i class="fa-solid fa-paper-plane me-1"></i> Send Notice';
      });

      document.getElementById('noticesTbody').addEventListener('click', async (e)=>{
        if(e.target.matches('button[data-id]')){
          if(!confirm('Delete this notice?')) return;
          const id=e.target.getAttribute('data-id');
          const fd=new FormData(); fd.append('id',id);
          try{ const r=await fetch('../backend/delete_notice.php',{method:'POST',body:fd}); const j=await r.json(); if(!j.success) throw new Error(j.message||'Delete failed'); pushAlert('Notice deleted','success'); loadNotices(); }catch(err){ pushAlert('Delete failed: '+err.message,'danger'); }
        }
      });

      modalEl.addEventListener('hidden.bs.modal',()=>{
        formalNoticeWarning.style.display='none';
        submitButton.innerHTML='<i class="fa-solid fa-paper-plane me-1"></i> Send Notice';
        submitButton.classList.remove('btn-danger'); submitButton.classList.add('btn-primary');
      });
    });
  </script>
</body>

</html>
