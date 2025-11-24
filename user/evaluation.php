<?php
require_once __DIR__ . '/../backend/session_user.php';
require_auth();
$me = session_employee($conn);
if (($_SESSION['role'] ?? 'account_executive') !== 'account_executive') { header('Location: dashboard.php'); exit; }
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
  <link href="../assets/css/evaluation.css" rel="stylesheet" />
</head>
<body>

  <!-- Sidebar (copied from main_dashboard) -->
  <div class="sidebar">
    <h2>MZE Cellular</h2>
    <a class="nav-link" href="main_dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a>
    <a class="nav-link active" href="evaluation.php"><i class="fa-solid fa-clipboard-list me-2"></i>Monthly Performance</a>
    <a class="nav-link" href="attendance_record.php"><i class="fa-solid fa-user-check me-2"></i>Attendance</a>
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
        <h4><i class="bi bi-clipboard-data"></i> Evaluation Results</h4>
        <p class="info-text">View your monthly performance evaluations and ratings, including quotas, ROR, and quality scores.</p>
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

    <!-- Evaluation List -->
    <div class="card p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Monthly Evaluation History</h5>
        <div class="small text-muted" id="resultMeta"></div>
      </div>
      <div id="evalList">
        <div class="text-muted">Loading evaluations…</div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3" id="pager" style="display:none;">
        <button class="btn btn-outline-secondary btn-sm" id="prevBtn">Prev</button>
        <button class="btn btn-outline-secondary btn-sm" id="nextBtn">Next</button>
      </div>
    </div>

    <!-- Evaluation Details Modal -->
    <div class="modal fade" id="evaluationModal" tabindex="-1" aria-labelledby="evaluationModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="evaluationModalLabel">Evaluation Details</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="evaluationDetails">
              <!-- Dynamic content will be loaded here -->
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const state = { page: 1, perPage: 10, pages: 1 };

    function fmtDate(d) {
      if (!d) return '-';
      try { return new Date(d).toLocaleDateString(); } catch { return d; }
    }

    function renderList(payload) {
      const list = document.getElementById('evalList');
      const meta = document.getElementById('resultMeta');
      if (!payload.success) {
        list.innerHTML = `<div class="text-danger">${payload.message || 'Failed to load evaluations'}</div>`;
        meta.textContent = '';
        return;
      }
  // Header user (optional)
  const nmEl = document.getElementById('userName');
  if(nmEl) nmEl.textContent = payload.employee?.full_name || '-';
  const br = [payload.employee?.branch_name, payload.employee?.branch_location].filter(Boolean).join(' - ');
  const brEl = document.getElementById('userBranch');
  if(brEl) brEl.textContent = br || '-';

      const rows = payload.data || [];
      state.pages = payload.summary?.pages || 1;
      meta.textContent = `${rows.length} of ${payload.summary?.count || 0}`;

      if (!rows.length) {
        list.innerHTML = '<div class="text-muted">No evaluations found.</div>';
        document.getElementById('pager').style.display = 'none';
        return;
      }

      list.innerHTML = rows.map(r => {
        const title = `Monthly Performance Evaluation - ${new Date(r.evaluation_date || r.created_at).toLocaleString('default',{ month:'long', year:'numeric' })}`;
        const dateDisp = fmtDate(r.evaluation_date || r.created_at);
        return `
          <div class="evaluation-card" data-bs-toggle="modal" data-bs-target="#evaluationModal" data-id="${r.id}">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="evaluation-date">${dateDisp}</div>
                <div class="evaluation-title">${title}</div>
              </div>
              <span class="evaluation-status status-completed">Completed</span>
            </div>
          </div>`;
      }).join('');

      // Pager
      const pager = document.getElementById('pager');
      pager.style.display = (state.pages > 1) ? '' : 'none';
      document.getElementById('prevBtn').disabled = (state.page <= 1);
      document.getElementById('nextBtn').disabled = (state.page >= state.pages);
    }

    async function loadPage() {
      const url = `../backend/get_my_evaluations.php?page=${state.page}&per_page=${state.perPage}`;
      let j = null;
      try {
        const r = await fetch(url, {cache:'no-store'});
        if (!r.ok) throw new Error('HTTP '+r.status);
        j = await r.json();
      } catch (e) {
        // Fallback: attempt absolute path (in case relative mismatch after nesting)
        try {
          const r2 = await fetch(`/backend/get_my_evaluations.php?page=${state.page}&per_page=${state.perPage}`, {cache:'no-store'});
          if (r2.ok) j = await r2.json();
        } catch(_) {}
      }
      if (!j) {
        renderList({success:false,message:'Network error'});
        return;
      }
      renderList(j);
      window.__evalPayload = j;
    }

    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('prevBtn').addEventListener('click', ()=>{ if(state.page>1){ state.page--; loadPage(); }});
      document.getElementById('nextBtn').addEventListener('click', ()=>{ if(state.page<state.pages){ state.page++; loadPage(); }});
      loadPage();

      // Modal population using cached payload
      const evaluationModal = document.getElementById('evaluationModal');
      evaluationModal.addEventListener('show.bs.modal', function(event){
        const btn = event.relatedTarget;
        const id = btn?.getAttribute('data-id');
        const all = (window.__evalPayload?.data)||[];
        const item = all.find(x=>String(x.id)===String(id));
        const detailsContainer = document.getElementById('evaluationDetails');
        if (!item) {
          detailsContainer.innerHTML = '<div class="text-danger">Details unavailable.</div>';
          document.getElementById('evaluationModalLabel').textContent = 'Evaluation Details';
          return;
        }
        const title = `Monthly Performance Evaluation - ${new Date(item.evaluation_date || item.created_at).toLocaleString('default',{ month:'long', year:'numeric' })}`;
        document.getElementById('evaluationModalLabel').textContent = title;
        const branchFull = [item.branch_name, item.branch_location].filter(Boolean).join(' - ');
        const pct = (v)=> (v!=null? (Math.round((parseFloat(v)||0)*100)/100)+'%' : '-')
        detailsContainer.innerHTML = `
          <div class="detail-item"><div class="detail-label">Evaluation Date</div><div class="detail-value">${fmtDate(item.evaluation_date || item.created_at)}</div></div>
          <div class="detail-item"><div class="detail-label">Evaluation Period</div><div class="detail-value">${title}</div></div>
          <div class="detail-item"><div class="detail-label">Evaluator</div><div class="detail-value">${item.evaluator_name || '-'}</div></div>
          <div class="detail-item"><div class="detail-label">Branch</div><div class="detail-value">${branchFull || '-'}</div></div>
          <div class="row mt-4">
            <div class="col-md-6"><div class="detail-item"><div class="detail-label">Sales Percent</div><div class="detail-value">${pct(item.sales_percent)}</div></div></div>
            <div class="col-md-6"><div class="detail-item"><div class="detail-label">Vault Percent</div><div class="detail-value">${pct(item.vault_percent)}</div></div></div>
            <div class="col-md-6"><div class="detail-item"><div class="detail-label">Attendance Percent</div><div class="detail-value">${pct(item.attendance_percent)}</div></div></div>
            <div class="col-md-6"><div class="detail-item"><div class="detail-label">Return Rate</div><div class="detail-value">${pct(item.return_rate_percent)}</div></div></div>
            <div class="col-md-6"><div class="detail-item"><div class="detail-label">Quality</div><div class="detail-value">${pct(item.quality_percent)}</div></div></div>
            <div class="col-md-6"><div class="detail-item"><div class="detail-label">Total Points</div><div class="detail-value"><span class="badge-rate">${pct(item.total_points_percent)}</span></div></div></div>
          </div>
          <div class="detail-item mt-4"><div class="detail-label">Evaluator Comments</div><div class="detail-value">${(item.comments||'').toString().trim() || '-'}</div></div>
        `;
      });
    });
  </script>
</body>
</html>