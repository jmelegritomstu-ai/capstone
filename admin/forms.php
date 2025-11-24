<?php
include '../backend/session_user.php';
require_auth();
$me = session_employee($conn);
$evaluatorDefault = $me['full_name'] ?? ($_SESSION['fullname'] ?? $_SESSION['username'] ?? '');
$evaluatorMissing = empty(trim((string)$evaluatorDefault));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>MZE Cellular</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="../assets/css/forms.css" rel="stylesheet" />
</head>

<body>

  <aside class="sidebar">
    <h2>MZE Cellular</h2>

    <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a>
    <a class="nav-link" href="sales.php"><i class="fa-solid fa-chart-line me-2"></i>Sales Performance</a>
    <a class="nav-link" href="attendance.php"><i class="fa-solid fa-user-check me-2"></i>Attendance</a>
    <a class="nav-link" href="employees.php"><i class="fa-solid fa-users me-2"></i>People</a>
    <a class="nav-link active" href="forms.php"><i class="fa-solid fa-file-pen me-2"></i>Forms / Evaluations</a>
    <a class="nav-link " href="notices.php"><i class="fa-solid fa-bullhorn me-2"></i>Notices</a>
    <a class="nav-link " href="requests.php"><i class="fa-solid fa-chalkboard-user"></i>Training Requests</a>
    <a class="nav-link" href="profile.php"><i class="fa-solid fa-user-circle me-2"></i>Profile</a>

    <a class="nav-link text-danger" href="logout.php" style="position:absolute;bottom:60px;left:20px;">
      <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
    </a>

    <small style="position:absolute;bottom:20px;left:20px;opacity:0.8;font-size:12px;">
      © MZE Cellular
    </small>

  </aside>

  <!-- Main Content -->
  <div class="main-content">
    <div class="form-container">
      <h3 class="form-title">Employee Evaluation Form</h3>
      <form method="POST" action="../backend/submit_evaluation.php" id="evaluationForm">
        <!-- Employee Section -->
        <div class="section-title">Employee Information</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" name="employee_display" id="employeeSelect" list="employeeList" placeholder="Type or select employee" required>
            <!-- Datalist: populate with options like below (value should be the display name)
              <option value="Juan Dela Cruz" data-emp-id="E-001" data-branch-name="MZE BLUMENTRITT" data-branch-location="Manila, Philippines"></option>
            -->
            <datalist id="employeeList"></datalist>
          </div>
          <div class="col-md-6">
            <label class="form-label">Employee ID</label>
            <input type="text" class="form-control" name="employee_id" id="employeeIdAuto" value="" readonly required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Branch Name</label>
            <input type="text" class="form-control" name="branch_name" id="branchName" value="" readonly required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Branch Location</label>
            <input type="text" class="form-control" name="branch_location" id="branchLocation" value="" readonly required>
          </div>
        </div>

        <!-- Monthly Sales Information -->
        <div class="section-title">Monthly Sales Information</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Total Sales (PHP) <i class="fa-solid fa-circle-info text-muted" data-bs-toggle="tooltip" title="Total gross sales for the month."></i></label>
            <input type="number" step="0.01" class="form-control" name="total_sales" id="totalSales" placeholder="0.00" required>
            <div class="form-text">Enter the total sales amount for the period.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Company Quota (PHP) <i class="fa-solid fa-circle-info text-muted" data-bs-toggle="tooltip" title="Target sales set by the company for the month."></i></label>
            <input type="number" step="0.01" class="form-control" name="company_quota" id="companyQuota" placeholder="0.00" required>
            <div class="form-text">The monthly target you are measured against.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Daily Sales (PHP, auto) <i class="fa-solid fa-circle-info text-muted" data-bs-toggle="tooltip" title="Auto: Total Sales / working days (Mon-Sat) minus 1 scheduled day off in days 1–15 and 1 in days 16–end."></i></label>
            <input type="number" step="0.01" class="form-control" name="daily_sales" id="dailySales" placeholder="0.00" readonly>
            <div class="form-text">Computed after entering Total Sales & Evaluation Date.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Difference / Gap (auto) <i class="fa-solid fa-circle-info text-muted" data-bs-toggle="tooltip" title="Total Sales minus Company Quota. Positive means above target."></i></label>
            <input type="number" step="0.01" class="form-control" name="sales_gap" id="salesGap" readonly placeholder="0.00">
          </div>
        </div>

        <!-- Vault Information -->
        <div class="section-title">Vault Information</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Vault Quota (PHP)</label>
            <input type="number" step="0.01" class="form-control" name="vault_quota" id="vaultQuotaInput" value="20000" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Vault Collected (PHP)</label>
            <input type="number" step="0.01" class="form-control" name="vault_collected" id="vaultCollectedInput" placeholder="Enter collected amount" required>
          </div>
        </div>

        <!-- Quality Information -->
        <div class="section-title">Quality Information</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Quality Percentage (%)</label>
            <input type="number" step="0.01" class="form-control" name="quality_percentage" id="qualityPercentageInput" placeholder="Enter quality %" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Evaluator Notes</label>
            <input type="text" class="form-control" name="evaluator_notes" placeholder="e.g. Neat appearance, proper grooming">
          </div>
        </div>

        <!-- Rate of Return Information -->
        <div class="section-title">Rate of Return Information</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Total Units Sent</label>
            <input type="number" min="0" step="1" class="form-control" name="total_units_sent" id="totalUnitsSent" placeholder="0">
            <div class="form-text">Each unit sent deducts 1% from performance.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Return Rate (auto, %)</label>
            <input type="number" step="0.01" class="form-control" name="return_rate_auto" id="returnRateAuto" readonly placeholder="0.00">
            <div class="form-text">Calculated as 20 − Units Sent (max 20%)</div>
          </div>
        </div>

        <!-- Attendance Information -->
        <div class="section-title">Attendance Information</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Absentees (auto)<i class="fa-solid fa-circle-info text-muted ms-1" data-bs-toggle="tooltip" title="Auto: Working days (Mon-Sat) without a record, excluding 1 scheduled day off in 1–15 and 1 in 16–end."></i></label>
            <input type="number" class="form-control" name="absentees" id="absenteesAuto" value="0" min="0" step="1" readonly>
            <div class="form-text">Auto after selecting employee & evaluation date.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Lates (auto)<i class="fa-solid fa-circle-info text-muted ms-1" data-bs-toggle="tooltip" title="Auto: Check-in after 09:15 AM on working days; day offs are excluded."></i></label>
            <input type="number" class="form-control" name="lates" id="latesAuto" value="0" min="0" step="1" readonly>
            <div class="form-text">Auto after selecting employee & evaluation date.</div>
          </div>
        </div>

        <!-- Performance Evaluation -->
        <div class="section-title">Performance Evaluation</div>
        <div class="row g-3 align-items-stretch">
          <div class="col-12 col-md-6 col-lg-4">
            <div class="metric-card h-100">
              <label class="form-label d-block text-center">Vault Information (auto, 0–20) <i class="fa-solid fa-circle-info text-muted" data-bs-toggle="tooltip" title="Auto from Vault Quota vs Collected."></i></label>
              <input type="number" class="form-control text-center" name="vault_percent" id="vaultPercent" min="0" max="20" placeholder="0-20" readonly>
              <div class="form-text text-center">Auto from vault section</div>
            </div>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="metric-card h-100">
              <label class="form-label d-block text-center">Monthly Sales Information (%) <i class="fa-solid fa-circle-info text-muted" data-bs-toggle="tooltip" title="Auto: Total Sales vs Company Quota."></i></label>
              <input type="number" step="0.01" class="form-control text-center" name="sales_percent" id="salesPercent" min="0" max="20" placeholder="0-20" readonly>
              <div class="form-text text-center">Auto from sales section</div>
            </div>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="metric-card h-100">
              <label class="form-label d-block text-center">Attendance (auto, 0–20) <i class="fa-solid fa-circle-info text-muted" data-bs-toggle="tooltip" title="Auto from Absentees and Lates."></i></label>
              <input type="number" step="0.01" class="form-control text-center" name="attendance_percent" id="attendancePercent" min="0" max="20" placeholder="0-20" readonly>
              <div class="form-text text-center">Auto from attendance section</div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-lg-4">
            <div class="metric-card h-100" id="returnRateCard">
              <label class="form-label d-block text-center">Rate of Return (max 20%) <i class="fa-solid fa-circle-info text-muted" data-bs-toggle="tooltip" title="Auto from Rate of Return Information; contributes up to 20 points."></i></label>
              <input type="number" step="0.01" class="form-control text-center" name="return_rate_percent" id="returnRatePercent" min="0" max="20" placeholder="0-20" readonly>
              <div class="form-text text-center">Auto from units sent (0 to 20)</div>
            </div>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="metric-card h-100">
              <label class="form-label d-block text-center">Quality (auto, 0–20) <i class="fa-solid fa-circle-info text-muted" data-bs-toggle="tooltip" title="Auto from Quality Percentage."></i></label>
              <input type="number" step="0.01" class="form-control text-center" name="quality_percent" id="qualityPercent" min="0" max="20" placeholder="0-20" readonly>
              <div class="form-text text-center">Auto from quality section</div>
            </div>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="metric-card h-100">
              <label class="form-label d-block text-center">Total Points (%)</label>
              <input type="number" step="0.01" class="form-control text-center" name="total_points_percent" id="totalPointsPercent" readonly>
              <div class="form-text text-center">Overall: <span id="overallGrade" class="badge rounded-pill bg-secondary">—</span></div>
            </div>
          </div>
        </div>

        <!-- Suggested Training -->
        <div class="section-title">Suggested Training: (auto)</div>
        <div class="mb-3">
          <textarea class="form-control" name="suggested_training" id="suggestedTraining" rows="2" placeholder="System suggestions based on evaluation results will appear here." readonly></textarea>
        </div>

        <!-- Evaluator Information -->
        <div class="section-title">Evaluator Information</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Evaluator Name</label>
            <input type="text" class="form-control" name="evaluator_name" id="evaluatorName" value="<?= htmlspecialchars($evaluatorDefault) ?>" <?= $evaluatorMissing ? '' : 'readonly' ?> required>
            <?php if ($evaluatorMissing): ?>
              <div class="alert alert-danger mt-2">Unable to determine evaluator name from your account. Please ensure your user is linked to an employee profile or contact the administrator.</div>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Evaluation Date</label>
            <input type="date" class="form-control" name="evaluation_date" id="evaluationDate" required>
          </div>
        </div>

        <!-- Comments -->
        <div class="section-title">Comments / Notes</div>
        <div class="mb-4">
          <textarea class="form-control" name="comments" rows="3" placeholder="Optional notes for the employee"></textarea>
        </div>

        <div class="text-end">
          <button type="submit" name="submit_form" class="btn-purple" <?= $evaluatorMissing ? 'disabled' : '' ?> ><i class="fa-solid fa-paper-plane me-2"></i>Send Evaluation</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Prefill from query params if provided (employee_id, employee_name, branch_name, branch_location)
    (function prefillFromQuery(){
      const qs = new URLSearchParams(window.location.search);
      const empName = qs.get('employee_name') || qs.get('employee') || '';
      const empId = qs.get('employee_id') || '';
      const branchName = qs.get('branch_name') || '';
      const branchLocation = qs.get('branch_location') || '';
      if (empName) {
        const nameInput = document.getElementById('employeeSelect');
        if (nameInput && !nameInput.value) nameInput.value = empName;
      }
      if (empId) {
        const idInput = document.getElementById('employeeIdAuto');
        if (idInput && !idInput.value) idInput.value = empId;
      }
      if (branchName) {
        const bn = document.getElementById('branchName');
        if (bn && !bn.value) bn.value = branchName;
      }
      if (branchLocation) {
        const bl = document.getElementById('branchLocation');
        if (bl && !bl.value) bl.value = branchLocation;
      }
    })();
    // Load employees to populate the datalist for Employee selection
    async function loadEmployees() {
      try {
        const res = await fetch('../backend/get_account_executives.php');
        const data = await res.json();
        if (!data.success) return;
        const list = document.getElementById('employeeList');
        if (!list) return;
        list.innerHTML = '';
        (data.data || []).forEach(emp => {
          const opt = document.createElement('option');
          opt.value = emp.full_name || '';
          if (emp.emp_id) opt.dataset.empId = emp.emp_id;
          if (emp.branch_name) opt.dataset.branchName = emp.branch_name;
          if (emp.branch_location) opt.dataset.branchLocation = emp.branch_location;
          list.appendChild(opt);
        });
        // Also wire reverse lookup: if user types an Employee ID, fill other fields
        const idInput = document.getElementById('employeeIdAuto');
        const nameInput = document.getElementById('employeeSelect');
        const bnEl = document.getElementById('branchName');
        const blEl = document.getElementById('branchLocation');
        if (idInput) {
          idInput.addEventListener('change', () => {
            const idVal = (idInput.value || '').trim().toLowerCase();
            if (!idVal) return;
            const found = (data.data || []).find(e => String(e.emp_id || '').trim().toLowerCase() === idVal);
            if (found) {
              if (nameInput && !nameInput.value) nameInput.value = found.full_name || '';
              if (bnEl && !bnEl.value) bnEl.value = found.branch_name || '';
              if (blEl && !blEl.value) blEl.value = found.branch_location || '';
            }
          });
        }
      } catch (e) {
        console.warn('Failed to load employees', e);
      }
    }
    loadEmployees();
    // Re-run prefill after employees loaded to allow reverse lookups
    setTimeout(() => { (function(){
      const qs = new URLSearchParams(window.location.search);
      const empId = qs.get('employee_id');
      if (empId) {
        const idInput = document.getElementById('employeeIdAuto');
        const nameInput = document.getElementById('employeeSelect');
        if (idInput && nameInput && nameInput.value === '') {
          // Try to find matching employee name from datalist
          const list = document.getElementById('employeeList');
          if (list) {
            const match = Array.from(list.options).find(o => (o.dataset.empId||'').toLowerCase() === empId.toLowerCase());
            if (match) {
              nameInput.value = match.value;
              const bnEl = document.getElementById('branchName');
              const blEl = document.getElementById('branchLocation');
              if (bnEl && !bnEl.value) bnEl.value = match.dataset.branchName || '';
              if (blEl && !blEl.value) blEl.value = match.dataset.branchLocation || '';
            }
          }
        }
      }
    })(); }, 800);
    // Set evaluation date to today
    function setToday() {
      const el = document.getElementById('evaluationDate');
      if (el) {
        const t = new Date();
        const yyyy = t.getFullYear();
        const mm = String(t.getMonth() + 1).padStart(2, '0');
        const dd = String(t.getDate()).padStart(2, '0');
        el.value = `${yyyy}-${mm}-${dd}`;
      }
    }
    setToday();

    // Auto-fill employee-related fields (supports editable input with datalist)
    (function setupEmployeeInput(){
      const input = document.getElementById('employeeSelect');
      const list = document.getElementById('employeeList');
      if (!input) return;
      const idOut = document.getElementById('employeeIdAuto');
      if (idOut) {
        idOut.addEventListener('input', () => { idOut.dataset.userEdited = '1'; });
      }
      function syncFromName(){
        const val = this.value;
        // If datalist exists and value matches, pull branch info
        if (list) {
          const match = Array.from(list.options).find(o => o.value === val);
          if (match) {
            // Only set Employee ID if the matched option provides a data-emp-id
            const empId = match.dataset.empId || '';
            if (empId && idOut && idOut.dataset.userEdited !== '1') {
              idOut.value = empId;
            }
            const bn = match.dataset.branchName || '';
            const bl = match.dataset.branchLocation || '';
            const bnEl = document.getElementById('branchName');
            const blEl = document.getElementById('branchLocation');
            if (bnEl) bnEl.value = bn;
            if (blEl) blEl.value = bl;
          }
        }
      }
      input.addEventListener('input', syncFromName);
      input.addEventListener('change', syncFromName);
    })();

  // Auto-calculate functions (your existing JavaScript calculations)
    const totalSalesEl = document.getElementById('totalSales');
    const companyQuotaEl = document.getElementById('companyQuota');
    // Recompute on input and on change to be safe (pastes, autofill, mobile keyboards)
    if (totalSalesEl) {
      totalSalesEl.addEventListener('input', () => { calculateSalesGap(); calculateSalesPercent(); calculateTotalPercent(); });
      totalSalesEl.addEventListener('change', () => { calculateSalesGap(); calculateSalesPercent(); calculateTotalPercent(); });
    }
    if (companyQuotaEl) {
      companyQuotaEl.addEventListener('input', () => { calculateSalesGap(); calculateSalesPercent(); calculateTotalPercent(); });
      companyQuotaEl.addEventListener('change', () => { calculateSalesGap(); calculateSalesPercent(); calculateTotalPercent(); });
    }

    const soldEl = document.getElementById('totalUnitsSent');
    if (soldEl) soldEl.addEventListener('input', () => { calculateReturnRate(); });

  const vq = document.getElementById('vaultQuotaInput');
  const vc = document.getElementById('vaultCollectedInput');
  if (vq) vq.addEventListener('input', () => { calculateVaultPercent(); calculateTotalPercent(); });
  if (vc) vc.addEventListener('input', () => { calculateVaultPercent(); calculateTotalPercent(); });

  const qpi = document.getElementById('qualityPercentageInput');
  if (qpi) qpi.addEventListener('input', () => { calculateQualityPercent(); calculateTotalPercent(); });

  const absEl = document.getElementById('absenteesAuto');
  const lateEl = document.getElementById('latesAuto');
  if (absEl) absEl.addEventListener('input', () => { calculateAttendancePercent(); calculateTotalPercent(); });
  if (lateEl) lateEl.addEventListener('input', () => { calculateAttendancePercent(); calculateTotalPercent(); });

  // Auto-load attendance summary (absentees, lates) based on employee and evaluation date
  async function loadAttendanceSummary() {
    try {
      const empId = (document.getElementById('employeeIdAuto')?.value || '').trim();
      const evalDate = document.getElementById('evaluationDate')?.value;
      if (!empId || !evalDate) return; // wait until both available
      const month = evalDate.slice(0,7); // YYYY-MM
      const url = `../backend/get_attendance_summary.php?emp_id=${encodeURIComponent(empId)}&month=${encodeURIComponent(month)}`;
      const res = await fetch(url);
      const data = await res.json();
      if (!data.success) return;
      const a = document.getElementById('absenteesAuto');
      const l = document.getElementById('latesAuto');
      if (a) a.value = data.absentees ?? 0;
      if (l) l.value = data.lates ?? 0;
      // Sync daily sales using backend's effective working_days (keeps it consistent with attendance summary)
      const ds = document.getElementById('dailySales');
      if (ds) {
        if (data.working_days !== undefined) ds.dataset.workingDays = String(data.working_days);
        const total = parseFloat(document.getElementById('totalSales')?.value) || 0;
        const wd = parseInt(data.working_days, 10);
        if (!isNaN(wd) && wd > 0) {
          ds.value = (total / wd).toFixed(2);
        }
      }
      calculateAttendancePercent();
      calculateTotalPercent();
    } catch (e) {
      console.warn('Failed to load attendance summary', e);
    }
  }

  // Trigger auto-load when employee or date changes
  document.getElementById('employeeIdAuto')?.addEventListener('change', loadAttendanceSummary);
  document.getElementById('employeeSelect')?.addEventListener('change', () => {
    // When selecting via name, ensure employeeIdAuto is filled from datalist
    const input = document.getElementById('employeeSelect');
    const list = document.getElementById('employeeList');
    const idOut = document.getElementById('employeeIdAuto');
    if (input && list && idOut) {
      const match = Array.from(list.options).find(o => o.value === input.value);
      if (match && match.dataset.empId && !idOut.dataset.userEdited) {
        idOut.value = match.dataset.empId;
      }
    }
    loadAttendanceSummary();
  });
  document.getElementById('evaluationDate')?.addEventListener('change', loadAttendanceSummary);

    function calculateSalesGap() {
      const totalEl = document.getElementById('totalSales');
      const quotaEl = document.getElementById('companyQuota');
      const gapEl = document.getElementById('salesGap');
      const dailyEl = document.getElementById('dailySales');
      const evalDateEl = document.getElementById('evaluationDate');
      const total = parseFloat(totalEl?.value) || 0;
      const quota = parseFloat(quotaEl?.value) || 0;
      if (gapEl) gapEl.value = (total - quota).toFixed(2);
      // Auto daily sales: prefer backend-provided working_days (Mon-Sat minus 1 dayoff per 15 days); fallback to local calculation
      if (dailyEl) {
        let workingDays = null;
        const dsWd = parseInt(dailyEl.dataset?.workingDays || '', 10);
        if (!isNaN(dsWd) && dsWd > 0) workingDays = dsWd;
        if (workingDays === null) {
          // Fallback local calculation mirrors backend policy
          let refDateStr = evalDateEl?.value;
          let ref = refDateStr ? new Date(refDateStr+'T00:00:00') : new Date();
          const year = ref.getFullYear();
          const month = ref.getMonth();
          const all = [];
          for (let d = 1; d <= 31; d++) {
            const dt = new Date(year, month, d);
            if (dt.getMonth() !== month) break;
            const day = dt.getDay();
            if (day !== 0) all.push(new Date(dt)); // Mon-Sat
          }
          const firstHalf = all.filter(d => d.getDate() <= 15);
          const secondHalf = all.filter(d => d.getDate() >= 16);
          function pickDayOff(days){
            let saturday = days.find(d => d.getDay() === 6);
            return saturday || days[days.length-1];
          }
          const dayOffs = [];
          if (firstHalf.length) dayOffs.push(pickDayOff(firstHalf).toDateString());
          if (secondHalf.length) dayOffs.push(pickDayOff(secondHalf).toDateString());
          const effective = all.filter(d => !dayOffs.includes(d.toDateString()));
          workingDays = effective.length;
        }
        dailyEl.value = (workingDays && workingDays > 0) ? (total / workingDays).toFixed(2) : '0.00';
      }
    }
  // Recalculate gap & daily sales when evaluation date changes (affects working day count)
  document.getElementById('evaluationDate')?.addEventListener('change', ()=>{ calculateSalesGap(); calculateSalesPercent(); calculateTotalPercent(); });

    const percentInputs = [
      'salesPercent',
      'vaultPercent',
      'attendancePercent',
      'returnRatePercent',
      'qualityPercent'
    ];
    percentInputs.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', calculateTotalPercent);
    });

    function calculateSalesPercent() {
      const total = parseFloat(document.getElementById('totalSales').value) || 0;
      const quota = parseFloat(document.getElementById('companyQuota').value) || 0;
      const el = document.getElementById('salesPercent');
      if (!el) return;
      // Convert sales performance to a 0..20 point scale
      // 100% of quota => 20 points; proportionally scaled and capped at 20
      let percentOfQuota = 0;
      if (quota > 0) percentOfQuota = (total / quota) * 100;
      let points = (percentOfQuota * 0.2); // percent -> points out of 20
      points = Math.max(0, Math.min(20, points));
      el.value = points.toFixed(2);
      // Ensure total updates when sales changes
      calculateTotalPercent();
    }

    function calculateVaultPercent() {
      const quota = parseFloat(document.getElementById('vaultQuotaInput')?.value) || 0;
      const collected = parseFloat(document.getElementById('vaultCollectedInput')?.value) || 0;
      const el = document.getElementById('vaultPercent');
      let points = 0;
      if (quota > 0) {
        const ratio = collected / quota; // 1.0 => 100%
        points = ratio * 20; // map to 0..20
      }
      points = Math.max(0, Math.min(20, points));
      if (el) el.value = points.toFixed(2);
      // Ensure total updates when vault changes
      calculateTotalPercent();
    }

    function calculateQualityPercent() {
      const qualityPct = parseFloat(document.getElementById('qualityPercentageInput')?.value) || 0;
      const el = document.getElementById('qualityPercent');
      let points = (qualityPct * 0.2); // 100% => 20 points
      points = Math.max(0, Math.min(20, points));
      if (el) el.value = points.toFixed(2);
      // Ensure total updates when quality changes
      calculateTotalPercent();
    }

    function calculateAttendancePercent() {
      const abs = parseFloat(document.getElementById('absenteesAuto')?.value) || 0;
      const lates = parseFloat(document.getElementById('latesAuto')?.value) || 0;
      const el = document.getElementById('attendancePercent');
      // Simple penalty model: 5 pts per absence, 2 pts per late
      let points = 20 - (abs * 5 + lates * 2);
      points = Math.max(0, Math.min(20, points));
      if (el) el.value = points.toFixed(2);
      // Ensure total updates when attendance changes
      calculateTotalPercent();
    }

    function calculateReturnRate() {
      const sent = parseFloat(document.getElementById('totalUnitsSent')?.value) || 0;
      const autoOut = document.getElementById('returnRateAuto');
      const perfOut = document.getElementById('returnRatePercent');
      let deductionPct = Math.max(0, sent * 1);
      deductionPct = Math.min(100, deductionPct);
      let rorPoints = 20 - Math.min(20, sent);
      rorPoints = Math.max(0, rorPoints);
      if (autoOut) autoOut.value = rorPoints.toFixed(2);
      if (perfOut) perfOut.value = rorPoints.toFixed(2);
      calculateTotalPercent();
    }

    function calculateTotalPercent() {
      const getVal = id => parseFloat(document.getElementById(id)?.value) || 0;
      const vault = getVal('vaultPercent');      // expected 0..20
      const sales = getVal('salesPercent');      // auto 0..20
      const attendance = getVal('attendancePercent'); // 0..20
      const quality = getVal('qualityPercent');  // 0..20
      const rorPoints = getVal('returnRatePercent');  // 0..20

      const clamp = (v, min, max) => Math.max(min, Math.min(max, v));
      const v = clamp(vault, 0, 20);
      const s = clamp(sales, 0, 20);
      const a = clamp(attendance, 0, 20);
      const q = clamp(quality, 0, 20);
      const r = clamp(rorPoints, 0, 20);

      // Each section contributes 20 points; total is out of 100
      const total = v + s + a + q + r;

      const out = document.getElementById('totalPointsPercent');
      out.value = total.toFixed(2);
      updateOverallGrade(total);
      suggestTraining();
    }

    function updateOverallGrade(total) {
      const badge = document.getElementById('overallGrade');
      if (!badge) return;
      let label = '—';
      let cls = 'bg-secondary';
      if (total >= 90) { label = 'Excellent'; cls = 'bg-success'; }
      else if (total >= 75) { label = 'Good'; cls = 'bg-primary'; }
      else if (total >= 60) { label = 'Fair'; cls = 'bg-warning text-dark'; }
      else { label = 'Needs Improvement'; cls = 'bg-danger'; }
      badge.className = 'badge rounded-pill ' + cls;
      badge.textContent = label;
    }

    function suggestTraining() {
      const suggestions = [];
  const vaultPct = parseFloat(document.getElementById('vaultPercent').value) || 0; // 0..20
  const salesPct = parseFloat(document.getElementById('salesPercent').value) || 0; // 0..20
  const qualityPct = parseFloat(document.getElementById('qualityPercent').value) || 0; // 0..20
  const attendancePct = parseFloat(document.getElementById('attendancePercent').value) || 0; // 0..20
  const returnRatePct = parseFloat(document.getElementById('returnRatePercent').value) || 0; // 0..20

  // Threshold at 60% of max (12 out of 20)
  const threshold = 12;
  if (vaultPct < threshold) suggestions.push("Vault Management Training");
  if (salesPct < threshold) suggestions.push("Sales Techniques Workshop");
  if (qualityPct < threshold) suggestions.push("Quality Assurance Training");
  if (attendancePct < threshold) suggestions.push("Time Management & Work-Life Balance");
  if (returnRatePct < threshold) suggestions.push("Customer Service Excellence");

      const trainingTextarea = document.getElementById('suggestedTraining');
      trainingTextarea.value = suggestions.length > 0 ? suggestions.join(", ") : "No specific training needed - Good performance!";
    }

    // Initialize calculations on page load
  calculateSalesGap();
  calculateSalesPercent();
  calculateVaultPercent();
  calculateQualityPercent();
  calculateAttendancePercent();
  calculateReturnRate();
  calculateTotalPercent();

    // Enable Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(el => new bootstrap.Tooltip(el));

    // AJAX form submission
    document.getElementById('evaluationForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Ensure employee_id is set if user selected a name from the datalist
      const empNameInput = document.getElementById('employeeSelect');
      const empIdInput = document.getElementById('employeeIdAuto');
      const list = document.getElementById('employeeList');
      if (empNameInput && empIdInput) {
        const nameVal = (empNameInput.value || '').trim();
        let idVal = (empIdInput.value || '').trim();
        if (!idVal && list) {
          const match = Array.from(list.options).find(o => o.value === nameVal);
          if (match && match.dataset.empId) {
            idVal = match.dataset.empId;
            empIdInput.value = idVal;
          }
        }
        if (!idVal) {
          showAlert('Please select a valid employee from the list (employee ID required).', 'error');
          return;
        }
      }

      const submitBtn = this.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Submitting...';
      submitBtn.disabled = true;

      const formData = new FormData(this);
      
      fetch('../backend/submit_evaluation.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showAlert('Evaluation submitted successfully!', 'success');
          setTimeout(() => {
            this.reset();
            // Clear manual-edit guard so auto-fill can work again next time
            const idOut = document.getElementById('employeeIdAuto');
            if (idOut && idOut.dataset) { delete idOut.dataset.userEdited; }
            calculateSalesGap();
            calculateSalesPercent();
            calculateVaultPercent();
            calculateQualityPercent();
            calculateAttendancePercent();
            calculateReturnRate();
            calculateTotalPercent();
            setToday(); // Reset date to today
          }, 2000);
        } else {
          showAlert('Error: ' + data.message, 'error');
        }
      })
      .catch(error => {
        showAlert('Network error occurred while submitting the evaluation.', 'error');
        console.error('Error:', error);
      })
      .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      });
    });

    function showAlert(message, type) {
      const existingAlert = document.querySelector('.alert');
      if (existingAlert) {
        existingAlert.remove();
      }
      
      const alertDiv = document.createElement('div');
      alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
      alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;
      
      const formContainer = document.querySelector('.form-container');
      formContainer.insertBefore(alertDiv, formContainer.firstChild);
      
      setTimeout(() => {
        if (alertDiv.parentElement) {
          alertDiv.remove();
        }
      }, 5000);
    }
  </script>
</body>
</html>