<?php
include '../backend/session_user.php'; // session + db
require_auth();

// Prepare data
$me = session_employee($conn);
$displayName = $me['full_name'] ?? ($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'User'));
$avatarPath = !empty($me['profile_img']) ? ('../uploads/' . $me['profile_img']) : 'https://via.placeholder.com/72?text=U';

$result = $conn->query("SELECT * FROM employees ORDER BY id DESC");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
   <title>MZE Cellular</title>
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="../assets/css/employees.css" rel="stylesheet" />
  <style>
    /* Branch suggestion popup (small, self-contained) */
    .branch-suggestions{
      position:absolute;
      z-index:1200;
      top:100%;
      left:0;
      right:0;
      background:#111;
      color:#fff;
      border-radius:6px;
      box-shadow:0 6px 18px rgba(0,0,0,0.25);
      max-height:260px;
      overflow:auto;
      padding:6px 0;
      font-size:0.85rem;
    }
    .branch-suggestions .item{
      padding:8px 12px;
      cursor:pointer;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .branch-suggestions .item:hover, .branch-suggestions .item.active{
      background:#222;
    }
    .d-none{display:none !important}
  </style>
</head>
<body>
  <div class="sidebar">
    <h2>MZE Cellular</h2>
    <a class="nav-link" href="dashboard.php">
      <i class="fa-solid fa-gauge me-2"></i>Dashboard
    </a>
    <a class="nav-link" href="sales.php">
      <i class="fa-solid fa-chart-line me-2"></i>Sales Performance
    </a>
  <a class="nav-link" href="attendance.php">
      <i class="fa-solid fa-user-check me-2"></i>Attendance
    </a>
    <a class="nav-link active" href="employees.php">
      <i class="fa-solid fa-users me-2"></i>People
    </a>
    <a class="nav-link" href="forms.php">
      <i class="fa-solid fa-file-pen me-2"></i>Forms / Evaluations
    </a>
    <a class="nav-link" href="notices.php">
      <i class="fa-solid fa-bullhorn me-2"></i>Notices
    </a>
    <a class="nav-link" href="requests.php"><i class="fa-solid fa-chalkboard-user"></i>Training Requests</a>

    <a class="nav-link" href="profile.php">
      <i class="fa-solid fa-user-circle me-2"></i>Profile
    </a>
    <a class="nav-link text-danger" href="logout.php" style="position:absolute;bottom:60px;left:20px;">
      <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
    </a>
    <small style="position:absolute;bottom:20px;left:20px;opacity:0.8;font-size:12px;">
      © MZE Cellular
    </small>
  </div>

  <div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 style="color: var(--lavender-primary); font-weight: 700;">People Directory</h2>

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
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Profile</th>
              <th>Emp ID</th>
              <th>Full Name</th>
              <th>Position</th>
              <th>Branch</th>
              <th>Location</th>
              <th>Preferred Day Off</th>
              <th>Contact</th>
              <th>Email</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="employeeTable">
            <?php while($row = $result->fetch_assoc()): ?>
              
              <tr>
                <td>
                  <?php if (!empty($row['profile_img'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($row['profile_img']) ?>" width="45" height="45" alt="Profile">
                  <?php else: ?>
                    <div class="bg-lavender text-white rounded-circle d-flex align-items-center justify-content-center" style="width:45px;height:45px;background:var(--lavender-light);color:var(--lavender-primary);">
                      <i class="bi bi-person fs-5"></i>
                    </div>
                  <?php endif; ?>
                </td>
                <td><strong><?= htmlspecialchars($row['emp_id']) ?></strong></td>
                <td>
                  <strong><?= htmlspecialchars($row['full_name']) ?></strong>
                  <?php if ($row['date_hired']): ?>
                    <br><small class="text-muted">Since <?= date('M d, Y', strtotime($row['date_hired'])) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['position']) ?></td>
                <td>
                  <span class="badge rounded-pill" style="background:var(--lavender-light);color:var(--lavender-primary);">
                    <?= htmlspecialchars($row['branch_name']) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($row['branch_city']) ?></td>
                <td>
                  <?php
                    $d = $row['dayoff_weekday'];
                    $dayoffMap = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'];
                    echo isset($dayoffMap[$d]) ? $dayoffMap[$d] : '-';
                  ?>
                </td>
                <td><?= htmlspecialchars($row['contact_number']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td>
                  <?php 
                  $statusClass = 'status-active';
                  if ($row['status'] == 'Inactive') $statusClass = 'status-inactive';
                  if ($row['status'] == 'On Leave') $statusClass = 'status-leave';
                  ?>
                  <span class="<?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span>
                </td>
                <td>
                  <div class="action-buttons">
                    <button class="btn btn-sm btn-outline-primary" onclick="viewEmployee(<?= $row['id'] ?>)">
                      <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-warning" onclick="editEmployee(<?= $row['id'] ?>)">
                      <i class="bi bi-pencil"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    
  </div>


  <!-- Edit Employee Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form method="post" enctype="multipart/form-data" action="../backend/employee_backend.php">
          <input type="hidden" name="employee_id" id="edit_employee_id">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Employee</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-4">
              <div class="col-md-4 text-center">
                <div class="position-relative">
                  <img id="edit_preview" src="https://via.placeholder.com/150?text=Employee+Photo" class="img-thumbnail mb-3" style="width:160px;height:160px;object-fit:cover;">
                  <div class="mt-2">
                    <input type="file" class="form-control" name="profile_img" accept="image/*" onchange="previewEditImage(event)">
                    <input type="hidden" name="current_image" id="current_image">
                  </div>
                </div>
              </div>
              <div class="col-md-8">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" id="edit_username">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role" id="edit_role">
                      <option value="account_executive">Account Executive</option>
                      <option value="auditor">Auditor</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Employee ID *</label>
                    <input type="text" class="form-control" name="emp_id" id="edit_emp_id" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Position *</label>
                    <input type="text" class="form-control" name="position" id="edit_position" required readonly disabled>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Date Hired</label>
                    <input type="date" class="form-control" name="date_hired" id="edit_date_hired">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="gender" id="edit_gender">
                      <option value="">-- Select --</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                      <option value="Prefer not to say">Prefer not to say</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Birthday</label>
                    <input type="date" class="form-control" name="birthday" id="edit_birthday">
                  </div>
                  <div class="col-md-6">
          <label class="form-label">Branch (City)</label>
          <select class="form-select" name="branch_city" id="branchCity">
            <option value="">-- Select City --</option>
            <option>Manila</option>
            <option>Quezon</option>
            <option>Caloocan</option>
            <option>Paranaque</option>
            <option>Marikina</option>
            <option>Taguig</option>
            <option>Antipolo</option>
            <option>Imus</option>
          </select>
        </div>
                  <div class="col-md-6">
                    <label class="form-label">Branch Name</label>
                    <div class="position-relative">
                      <input autocomplete="off" class="form-control" name="branch_name" id="edit_branch_name" placeholder="Type or choose branch">
                      <div id="edit_branch_suggestions" class="branch-suggestions d-none" role="listbox" aria-label="Branch suggestions"></div>
                    </div>
                    <datalist id="dl_edit_branch_name" class="d-none"></datalist>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Branch Location</label>
                    <input type="text" class="form-control" name="branch_location" id="edit_branch_location" list="edit_branchLocationList" readonly>
                    <datalist id="edit_branchLocationList"></datalist>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Contact Number</label>
                    <input type="text" class="form-control" name="contact_number" id="edit_contact_number">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" id="edit_email">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Contract Type</label>
                    <input type="text" class="form-control" name="contract_type" id="edit_contract_type" placeholder="Regular / Probationary / Contractual">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" id="edit_status">
                      <option value="Active">Active</option>
                      <option value="On Leave">On Leave</option>
                      <option value="Terminated">Terminated</option>
                      <option value="Suspended">Suspended</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Preferred Day Off</label>
                    <select class="form-select" name="dayoff_weekday" id="edit_dayoff_weekday">
                      <option value="">-- Select --</option>
                      <option value="1">Monday</option>
                      <option value="2">Tuesday</option>
                      <option value="3">Wednesday</option>
                      <option value="4">Thursday</option>
                      <option value="5">Friday</option>
                      <option value="6">Saturday</option>
                      <option value="7">Sunday</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Shift Schedule</label>
                    <select class="form-select" name="shift_schedule" id="edit_shift_schedule">
                      <option value="">-- Select Schedule --</option>
                      <option value="8:00AM 5:00PM">8:00 AM - 5:00 PM</option>
                      <option value="8:00AM 6:00PM">8:00 AM - 6:00 PM</option>
                      <option value="8:00AM 7:00PM">8:00 AM - 7:00 PM</option>
                    </select>
                  </div>
                  <!-- Auditor selector for Account Executives (loaded on demand) -->
                  <div class="col-md-6" id="edit_auditor_group" style="display:none;">
                    <label class="form-label">Auditor</label>
                    <select class="form-select" name="auditor_name" id="edit_auditor_name">
                      <option value="">-- Select auditor --</option>
                    </select>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="update" class="btn btn-primary">
              <i class="bi bi-check-circle me-2"></i>Update Employee
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Employee Modal -->
  <div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Employee Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="profile-section">
            <img id="view_profile_img" src="https://via.placeholder.com/150?text=Employee+Photo" class="img-thumbnail mb-3" style="width:160px;height:160px;object-fit:cover;">
            <h4 id="view_full_name" class="mb-2" style="color: var(--lavender-primary);">Full Name</h4>
            <p id="view_position" class="text-muted mb-3">Position</p>
            <!-- Username and role removed from view modal to reduce clutter -->
            <span id="view_status" class="status-active">Status</span>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="employee-detail-row">
                <div class="detail-label">Employee ID</div>
                <div class="detail-value" id="view_emp_id">-</div>
              </div>
              <div class="employee-detail-row">
                <div class="detail-label">Date Hired</div>
                <div class="detail-value" id="view_date_hired">-</div>
              </div>
              <div class="employee-detail-row">
                <div class="detail-label">Gender</div>
                <div class="detail-value" id="view_gender">-</div>
              </div>
              <div class="employee-detail-row">
                <div class="detail-label">Birthday</div>
                <div class="detail-value" id="view_birthday">-</div>
              </div>
              <div class="employee-detail-row">
                <div class="detail-label">Branch Name</div>
                <div class="detail-value">
                  <span class="badge rounded-pill" style="background:var(--lavender-light);color:var(--lavender-primary);" id="view_branch_name">-</span>
                </div>
              </div>
              <div class="employee-detail-row">
                <div class="detail-label">Auditor Name</div>
                <div class="detail-value" id="view_auditor_name">-</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="employee-detail-row">
                <div class="detail-label">Branch Location</div>
                <div class="detail-value" id="view_branch_location">-</div>
              </div>
              <div class="employee-detail-row">
                <div class="detail-label">Branch City</div>
                <div class="detail-value" id="view_branch_city">-</div>
              </div>
              <div class="employee-detail-row">
                <div class="detail-label">Contact Number</div>
                <div class="detail-value" id="view_contact_number">-</div>
              </div>
              <div class="employee-detail-row">
                <div class="detail-label">Email Address</div>
                <div class="detail-value" id="view_email">-</div>
              </div>
              <div class="employee-detail-row">
                <div class="detail-label">Contract Type</div>
                <div class="detail-value" id="view_contract_type">-</div>
              </div>
              <div class="employee-detail-row">
                <div class="detail-label">Shift Schedule</div>
                <div class="detail-value" id="view_shift_schedule">-</div>
              </div>
              <div class="employee-detail-row">
                <div class="detail-label">Preferred Day Off</div>
                <div class="detail-value" id="view_dayoff_weekday">-</div>
              </div>
              
            </div>
          </div>

          
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary" onclick="editEmployeeFromView()">
            <i class="bi bi-pencil-square me-2"></i>Edit Employee
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    function previewImage(event) {
      var reader = new FileReader();
      reader.onload = function () {
        document.getElementById('preview').src = reader.result;
      }
      reader.readAsDataURL(event.target.files[0]);
    }

    function previewEditImage(event) {
      var reader = new FileReader();
      reader.onload = function () {
        document.getElementById('edit_preview').src = reader.result;
      }
      reader.readAsDataURL(event.target.files[0]);
    }

    // Search and Filter functionality
    function filterEmployees() {
      const searchText = document.getElementById('searchInput').value.toLowerCase();
      const branchFilter = document.getElementById('branchFilter').value;
      const statusFilter = document.getElementById('statusFilter').value;
      
      const rows = document.querySelectorAll('#employeeTable tr');
      
      rows.forEach(row => {
        const name = row.cells[2]?.textContent.toLowerCase() || '';
        const empId = row.cells[1]?.textContent.toLowerCase() || '';
        const branch = row.cells[4]?.textContent || '';
        const status = row.cells[9]?.textContent || '';
        
        const matchesSearch = name.includes(searchText) || empId.includes(searchText);
        const matchesBranch = !branchFilter || branch === branchFilter;
        const matchesStatus = !statusFilter || status === statusFilter;
        
        row.style.display = (matchesSearch && matchesBranch && matchesStatus) ? '' : 'none';
      });
    }

    function resetFilters() {
      document.getElementById('searchInput').value = '';
      document.getElementById('branchFilter').value = '';
      document.getElementById('statusFilter').value = '';
      filterEmployees();
    }

    // View Employee Function
    function viewEmployee(id) {
      fetch(`../backend/get_employee.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Store the employee ID for edit functionality
            window.currentViewEmployeeId = data.employee.id;
            
            // Set profile image
            if (data.employee.profile_img) {
              document.getElementById('view_profile_img').src = `../uploads/${data.employee.profile_img}`;
            } else {
              document.getElementById('view_profile_img').src = 'https://via.placeholder.com/150?text=Employee+Photo';
            }
            
            // Set status with appropriate class
            const statusElement = document.getElementById('view_status');
            statusElement.textContent = data.employee.status;
            statusElement.className = getStatusClass(data.employee.status);
            
            // Set basic information
            document.getElementById('view_full_name').textContent = data.employee.full_name || '-';
            document.getElementById('view_position').textContent = data.employee.position || '-';
            // username and role display removed from view modal to reduce visual clutter
            document.getElementById('view_emp_id').textContent = data.employee.emp_id || '-';
            document.getElementById('view_date_hired').textContent = data.employee.date_hired ? 
              formatDate(data.employee.date_hired) : '-';
            document.getElementById('view_gender').textContent = data.employee.gender || '-';
            document.getElementById('view_birthday').textContent = data.employee.birthday ? formatDate(data.employee.birthday) : '-';
            
            // Set branch information
            document.getElementById('view_branch_city').textContent = data.employee.branch_city || '-';
            document.getElementById('view_branch_name').textContent = data.employee.branch_name || '-';
            document.getElementById('view_branch_location').textContent = data.employee.branch_location || '-';
            
            // Set contact information
            document.getElementById('view_contact_number').textContent = data.employee.contact_number || '-';
            document.getElementById('view_email').textContent = data.employee.email || '-';
            document.getElementById('view_contract_type').textContent = data.employee.contract_type || '-';
            document.getElementById('view_shift_schedule').textContent = data.employee.shift_schedule || '-';
            document.getElementById('view_auditor_name').textContent = data.employee.auditor_name || '-';
            // Dayoff schedule (attendance information)
            const dayoffText = weekdayName(data.employee.dayoff_weekday);
            const dayoffEl = document.getElementById('view_dayoff_weekday');
            if (dayoffEl) dayoffEl.textContent = dayoffText;
            
            // Show modal
            const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
            viewModal.show();
          } else {
            alert('Error loading employee data');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error loading employee data');
        });
    }

    // Helper function to get status class
    function getStatusClass(status) {
      switch(status) {
        case 'Active': return 'status-active';
        case 'Inactive': return 'status-inactive';
        case 'On Leave': return 'status-leave';
        default: return 'status-active';
      }
    }

    // Helper function to format date
    function formatDate(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
      });
    }

    // Map numeric weekday (1-6) to name for display
    function weekdayName(n) {
      if (n === null || n === undefined || n === '') return '-';
      const map = {
        '1': 'Monday',
        '2': 'Tuesday',
        '3': 'Wednesday',
        '4': 'Thursday',
        '5': 'Friday',
        '6': 'Saturday'
      };
      const key = String(n);
      return map[key] || '-';
    }

    // Helper function to calculate employment duration
    function calculateEmploymentDuration(dateHired) {
      if (!dateHired) return '-';
      
      const hiredDate = new Date(dateHired);
      const today = new Date();
      
      const years = today.getFullYear() - hiredDate.getFullYear();
      const months = today.getMonth() - hiredDate.getMonth();
      
      let duration = '';
      if (years > 0) {
        duration += `${years} year${years > 1 ? 's' : ''}`;
      }
      if (months > 0) {
        if (duration) duration += ', ';
        duration += `${months} month${months > 1 ? 's' : ''}`;
      }
      
      return duration || 'Less than 1 month';
    }

    // Show/hide auditor select when role is Account Executive
    function toggleEditAuditorField(role){
      const grp = document.getElementById('edit_auditor_group');
      if(!grp) return;
      if(String(role) === 'account_executive') grp.style.display = '';
      else grp.style.display = 'none';
    }

    // Load auditors list once and cache
    let __auditors_loaded = false;
    async function ensureAuditorOptionsLoaded(){
      if(__auditors_loaded) return;
      try{
        const res = await fetch('../backend/get_auditors.php');
        const j = await res.json();
        if(!j.success) return;
        const sel = document.getElementById('edit_auditor_name');
        if(!sel) return;
        // Clear except placeholder
        sel.innerHTML = '<option value="">-- Select auditor --</option>';
        (j.data||[]).forEach(a=>{
          const opt = document.createElement('option');
          opt.value = a.full_name;
          opt.textContent = a.full_name;
          sel.appendChild(opt);
        });
        __auditors_loaded = true;
      }catch(e){ console.warn('Failed to load auditors', e); }
    }

    // Bind role select to toggle auditor group and load auditors on demand
    const roleSelect = document.getElementById('edit_role');
    if(roleSelect){
      roleSelect.addEventListener('change', (e)=>{
        toggleEditAuditorField(e.target.value);
      });
      // Initialize on page load
      toggleEditAuditorField(roleSelect.value || 'account_executive');
    }

    // Ensure options load when user clicks the auditor area
    document.addEventListener('click', (e)=>{
      if(e.target && (e.target.id === 'edit_auditor_name' || e.target.closest('#edit_auditor_group'))){
        ensureAuditorOptionsLoaded();
      }
    });

    // Function to edit employee from view modal
    function editEmployeeFromView() {
      if (window.currentViewEmployeeId) {
        // Close view modal
        const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewModal'));
        viewModal.hide();
        
        // Open edit modal
        setTimeout(() => {
          editEmployee(window.currentViewEmployeeId);
        }, 500);
      }
    }

    // Edit Employee Function
    function editEmployee(id) {
      fetch(`../backend/get_employee.php?id=${encodeURIComponent(id)}`)
        .then(async response => {
          const text = await response.text();
          let data;
          try {
            data = JSON.parse(text);
          } catch (e) {
            console.error('Non-JSON response from get_employee.php:', text);
            alert('Error loading employee data (server response not JSON). See console for details.');
            return;
          }
          if (data.success) {
            document.getElementById('edit_employee_id').value = data.employee.id;
            document.getElementById('edit_emp_id').value = data.employee.emp_id;
            document.getElementById('edit_full_name').value = data.employee.full_name;
            document.getElementById('edit_position').value = data.employee.position;
            document.getElementById('edit_date_hired').value = data.employee.date_hired;
            document.getElementById('edit_username').value = data.employee.username || '';
            document.getElementById('edit_role').value = data.employee.role || 'account_executive';
            // Toggle auditor visibility based on role
            try{ toggleEditAuditorField(document.getElementById('edit_role').value); }catch(e){}
            // Populate auditor select if present
            if (document.getElementById('edit_auditor_name')) {
              await ensureAuditorOptionsLoaded();
              const sel = document.getElementById('edit_auditor_name');
              const av = data.employee.auditor_name || '';
              if (av) {
                let found = Array.from(sel.options).some(o=>{ if(o.value===av){ sel.value=av; return true;} return false; });
                if(!found){ const opt = document.createElement('option'); opt.value = av; opt.textContent = av; sel.appendChild(opt); sel.value = av; }
              } else {
                sel.value = '';
              }
            }
            document.getElementById('edit_gender').value = data.employee.gender || '';
            document.getElementById('edit_birthday').value = data.employee.birthday || '';
            // Set branch city and branch name in selects; populate branch list for the city first
            try {
              const city = data.employee.branch_city || '';
              if (city) {
                // use same fallback (accept either id)
                const citySel = document.getElementById('edit_branch_city') || document.getElementById('branchCity');
                if (citySel) {
                  citySel.value = city;
                  // trigger population of branch datalist (use change to match handlers)
                  const ev = new Event('change', { bubbles: true });
                  citySel.dispatchEvent(ev);
                }
              }
            } catch (e) { /* ignore */ }
            // set branch name (input) and trigger input so location autofill runs; also sync optional select
            try {
              const branchEl = document.getElementById('edit_branch_name');
              if (branchEl) {
                const val = data.employee.branch_name || '';
                branchEl.value = val;
                branchEl.dispatchEvent(new Event('input', { bubbles: true }));
                try {
                  const branchSelectEl = document.getElementById('edit_branch_name_select');
                  if (branchSelectEl) {
                    let found = Array.from(branchSelectEl.options).some(o => o.value === val);
                    if (!found && val) {
                      const opt = document.createElement('option');
                      opt.value = val; opt.textContent = val; branchSelectEl.appendChild(opt);
                    }
                    branchSelectEl.value = val;
                    branchSelectEl.classList.remove('d-none');
                  }
                } catch(e) { /* ignore */ }
              }
            } catch (e) {}
            // Keep stored branch_location as-is (do not auto-overwrite)
            document.getElementById('edit_branch_location').value = data.employee.branch_location || '';
            document.getElementById('edit_contact_number').value = data.employee.contact_number;
            document.getElementById('edit_email').value = data.employee.email;
            document.getElementById('edit_status').value = data.employee.status;
            document.getElementById('edit_contract_type').value = data.employee.contract_type || '';
            document.getElementById('edit_shift_schedule').value = data.employee.shift_schedule || '';
            document.getElementById('edit_address').value = data.employee.address || '';
            // Dayoff weekday
            if (document.getElementById('edit_dayoff_weekday')) {
              document.getElementById('edit_dayoff_weekday').value = data.employee.dayoff_weekday || '';
            }
            
            // Set current image
            if (data.employee.profile_img) {
              document.getElementById('edit_preview').src = `../uploads/${data.employee.profile_img}`;
              document.getElementById('current_image').value = data.employee.profile_img;
            } else {
              document.getElementById('edit_preview').src = 'https://via.placeholder.com/150?text=Employee+Photo';
              document.getElementById('current_image').value = '';
            }
            
            // Show modal
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
          } else {
            console.error('get_employee.php returned error:', data);
            alert('Error loading employee data: ' + (data.message || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error loading employee data');
        });
    }

    function deleteEmployee(id) {
      if (confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
        fetch(`../backend/delete_employee.php?id=${id}`)
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert('Employee deleted successfully');
              location.reload();
            } else {
              alert('Error deleting employee: ' + data.message);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Error deleting employee');
          });
      }
    }

    // Event listeners for filters
    document.getElementById('searchInput').addEventListener('input', filterEmployees);
    document.getElementById('branchFilter').addEventListener('change', filterEmployees);
    document.getElementById('statusFilter').addEventListener('change', filterEmployees);

    // Branch lists and addresses (match register.php behavior)
    const branches = {
      'Manila': [
        'MZE BLUMENTRITT','MZE RECTO','MZE OUIAPO','MZE SAN JUAN','MZE GALAS','MZE DELA FUENTE','MZE PACO','MZE PRITIL'
      ],
      'Quezon': [
        'MZE E.RODRIGUEZ','MZE QUEZON AVENUE','MZE TIMOG','MZE KAMUNING'
      ],
      'Caloocan': [
        'MZE SANGANDAAN','MZE MAYPAJO'
      ],
      'Paranaque': [
        'MZE MALIBAY','MZE EVACOM','MZE VALLEY 1','MZE SOLEDAD','MZE NAIA PASAY','MZE CAA- LAS PINAS','MZE FOURTH ESTATE'
      ],
      'Marikina': ['MZE SUMULONG- MARIKINA'],
      'Taguig': ['MZE TAGUIG'],
      'RFC': ['MZE RFC MOONWALK','MZE RFC CAINTA - ANTIPOLO','MZE RFC BICUTAN'],
      'Imus': ['JC IMUS']
    };
    const allBranches = Object.values(branches).flat();

    // branchAddresses (map of branch name -> printable location)
    const branchAddresses = {
      'MZE BLUMENTRITT': 'BLUMENTRITT - 1737 Blumentritt St., Sta. Cruz, Manila / 0923-2738795',
      'MZE RECTO': 'RECTO - 899 Quezon Boulevard, Sta. Cruz, Manila / 0932-3038807 / 8251-3487',
      'MZE OUIAPO': 'QUIAPO - Unit 1B MR Mali Bldg. Quezon Blvd., Quiapo Manila (near Mercury Drug, in front of Quiapo Church) / 0923-2738803',
      'MZE SAN JUAN': 'SAN JUAN - 5854 F. Manalo St., Kabayanan, San Juan City / 0922-2111468',
      'MZE GALAS': 'GALAS - 35 Cuatro De Julio, Brgy. San Isidro, Galas, Quezon City / 0923-2738794',
      'MZE DELA FUENTE': 'DELA FUENTE - 829 BM Delafuente St., Brgy. 425, Sampaloc, Manila / 0932-1778869',
      'MZE PACO': 'PACO - 3B G/F Jaime Cardinal Sin Bldg., 1521 Paz St., Paco, Manila / 0933-3251081',
      'MZE PRITIL': 'PRITIL - 551 Herbosa St., Tondo, Manila / 0923-5208758',
      'MZE E.RODRIGUEZ': 'E.RODRIGUEZ - Unit 6 Doña Soledad, E. Rodriguez St., Araneta Ave., Quezon City / 0923-2738791',
      'MZE QUEZON AVENUE': 'QUEZON AVENUE - 100-C Quezon Avenue, Tatalon, Quezon City / 0922-2111466 / 8251-5103',
      'MZE TIMOG': 'TIMOG - Ground Floor Victoria Tower No.9, Timog Ave., cor. Panay, Brgy. Paligsahan, Quezon City / 0923-4447814',
      'MZE KAMUNING': 'KAMUNING - 15 K - 5th St., Kamuning, Quezon City / 0942-3901800',
      'MZE SANGANDAAN': 'SANGANDAAN - Sangandaan Brgy. 58, Gen. San Miguel St., Sangandaan, Caloocan City / 0932-1778864 / 405-4080',
      'MZE MAYPAJO': 'MAYPAJO - 2345 A. Mabini St., Maypajo, Caloocan City / 0923-2738781',
      'MZE MALIBAY': 'MALIBAY - 167 Geronimo St., Brgy. 172, Malibay, Pasay City / 0923-2738802',
      'MZE EVACOM': 'EVACOM - 8263 Dr. Santos Ave., San Dionisio, Paranaque City / 0923-2738797',
      'MZE VALLEY 1': 'VALLEY 1 - L2 Blk.12 San Antonio Ave., San Antonio Valley 1, Paranaque City / 0943-0099752',
      'MZE SOLEDAD': 'SOLEDAD - #18 France cor. Germany St., Brgy. Don Bosco, Better Living, Paranaque City / 0932-4069768',
      'MZE NAIA PASAY': 'NAIA PASAY - Unit E-7 Ninoy Aquino Ave., Brgy.199 Zone 20, NAIA, Pasay City / 0932-1778866',
      'MZE CAA- LAS PINAS': 'CAA - 25 Saging St., Phase 1, CAA, Las Pinas City / 0922-7695216',
      'MZE FOURTH ESTATE': 'FOURTH ESTATE - #2 Linotype St., Fourth Estate Subd., Brgy. San Antonio, Paranaque City / 0922-9157420',
      'MZE SUMULONG- MARIKINA': 'SUMULONG - No.7 M. Cruz St. cor. Sumulong Hwy, Santo Nino, Marikina City / 0922-9157414',
      'MZE TAGUIG': 'TAGUIG - 55 General Luna St., Tuktukan, Taguig City / 0922-9157415',
      'MZE RFC MOONWALK': 'RFC MOONWALK - 15414 E. Rodriguez Ave., Alano Compound, Moonwalk, Paranaque City / 0932-1986468',
      'MZE RFC CAINTA - ANTIPOLO': 'RFC CAINTA - Jose Margarita Bldg., Ortigas Ave. Ext. cor. A. Bonifacio Ave., Brgy. Sto. Domingo, Cainta / 0923-4447813',
      'MZE RFC BICUTAN': 'RFC BICUTAN - Brgy. cor. General Santos Ave., Lower Bicutan, Taguig City / 0928-2750977',
      'JC IMUS': 'JC IMUS CAVITE - 252 Palico IV, Aguinaldo Highway / 0921-3640999',
      'JOG PEDRO GIL': 'JOG PEDRO GIL - 1548 Pedro Gil St. / 0922-9157449'
    };

    // build a case-insensitive lookup for branch addresses (normalize keys)
    const branchAddressesNorm = {};
    Object.keys(branchAddresses).forEach(k=>{ branchAddressesNorm[normKey(k)] = branchAddresses[k]; });

    function normKey(v){ return (v||'').toString().trim().toUpperCase(); }

    // Robust address lookup: try exact normalized key, then partial matches
    function getBranchAddress(name){
      if(!name) return '';
      const key = normKey(name);
      if(branchAddressesNorm[key]) return branchAddressesNorm[key];
      // try contains or startsWith matches
      const keys = Object.keys(branchAddressesNorm);
      for(const k of keys){
        if(k.includes(key) || key.includes(k)) return branchAddressesNorm[k];
      }
      // fallback: try words match (loose)
      const parts = key.split(/\s+/).filter(Boolean);
      for(const k of keys){
        let score = 0;
        for(const p of parts){ if(k.includes(p)) score++; }
        if(score >= Math.max(1, parts.length - 1)) return branchAddressesNorm[k];
      }
      return '';
    }

    // Add modal: branch city select + branch name datalist + branch location datalist
    const addBranchCity = document.getElementById('add_branch_city');
    const addBranchName = document.getElementById('add_branch_name');
    const addBranchNameList = document.getElementById('dl_add_branch_name');
    // prefer add modal location input if present; fall back to common selectors (safe)
    const addBranchLocation = document.getElementById('add_branch_location') || document.querySelector('#addModal input[name="branch_location"]') || document.querySelector('#editModal input[name="branch_location"]');
    const addBranchLocationList = document.getElementById('add_branchLocationList');
    if (addBranchCity && addBranchNameList) {
      // populate city select only if it doesn't already have options (avoid duplicates)
      if (addBranchCity && addBranchCity.options.length <= 1) {
        ['Manila','Quezon','Caloocan','Paranaque','Marikina','Taguig','Antipolo','Imus'].forEach(city=>{
          const opt = document.createElement('option'); opt.value = city; opt.textContent = city; addBranchCity.appendChild(opt);
        });
      }
      function fillAddBranchNameDatalist(city){
        addBranchNameList.innerHTML = '';
        let list = [];
        if(city === 'Manila'){
          list = ['MZE BLUMENTRITT','MZE RECTO','MZE OUIAPO','MZE SAN JUAN','MZE GALAS','MZE DELA FUENTE','MZE PACO','MZE PRITIL','JOG PEDRO GIL'];
        } else if(city === 'Quezon'){
          list = ['MZE E.RODRIGUEZ','MZE QUEZON AVENUE','MZE TIMOG','MZE KAMUNING'];
        } else if(city === 'Caloocan'){
          list = ['MZE SANGANDAAN','MZE MAYPAJO'];
        } else if(city === 'Paranaque'){
          list = ['MZE MALIBAY','MZE EVACOM','MZE VALLEY 1','MZE SOLEDAD','MZE NAIA PASAY','MZE CAA- LAS PINAS','MZE FOURTH ESTATE','MZE RFC MOONWALK','MZE RFC BICUTAN'];
        } else if(city === 'Marikina'){
          list = ['MZE SUMULONG- MARIKINA'];
        } else if(city === 'Taguig'){
          list = ['MZE TAGUIG'];
        } else if(city === 'Antipolo'){
          list = ['MZE RFC CAINTA - ANTIPOLO'];
        } else if(city === 'Imus'){
          list = ['JC IMUS'];
        } else {
          list = allBranches;
        }
        list.forEach(b=>{ const o = document.createElement('option'); o.value = b; addBranchNameList.appendChild(o); });
      }
      function fillAddBranchLocationDatalist(){
        if(!addBranchLocationList) return;
        addBranchLocationList.innerHTML = '';
        const locs = Array.from(new Set([].concat(Object.keys(branches), ['Las Pinas','Pasay','Marikina','Taguig','Antipolo','Cainta','Moonwalk','Bicutan','Valley 1','Soledad','NAIA','San Juan','Quiapo','Galas','Pritil','Paco','Dela Fuente','Recto','Blumentritt','E. Rodriguez','Timog','Kamuning','Quezon Avenue','Sangandaan','Maypajo','Evacom','Malibay','Fourth Estate','Sumulong'])));
        locs.forEach(l=>{ const o = document.createElement('option'); o.value = l; addBranchLocationList.appendChild(o); });
      }
      addBranchCity.addEventListener('change', function(){
        fillAddBranchNameDatalist(this.value);
        // clear any previously selected branch and location when city changes
        if(addBranchName) addBranchName.value = '';
        if(addBranchLocation) { addBranchLocation.value = ''; try{ addBranchLocation.readOnly = false; }catch(e){} }
        hideEditBranchSuggestions();
      });
      addBranchName.addEventListener('input', function(){
        const addr = getBranchAddress(this.value);
        if(addr){ if(addBranchLocation) { addBranchLocation.value = addr; try{ addBranchLocation.readOnly = true; }catch(e){} } }
        else { if(addBranchLocation) { try{ addBranchLocation.readOnly = false; }catch(e){} addBranchLocation.value = ''; addBranchLocation.placeholder = 'Type location if branch not found'; } }
      });
      // init
      fillAddBranchNameDatalist('');
      fillAddBranchLocationDatalist();
    }

    // Edit modal: branch city select + branch name datalist + branch location datalist
    // accept either id used in markup: prefer 'edit_branch_city' but fall back to 'branchCity'
    const editBranchCity = document.getElementById('edit_branch_city') || document.getElementById('branchCity');
    const editBranchName = document.getElementById('edit_branch_name');
    const editBranchNameList = document.getElementById('dl_edit_branch_name');
    const editBranchNameSelect = document.getElementById('edit_branch_name_select');
    const editBranchLocation = document.getElementById('edit_branch_location');
    const editBranchLocationList = document.getElementById('edit_branchLocationList');
    if (editBranchCity && editBranchNameList) {
      // populate city select only when it's empty (avoid duplicating existing options in markup)
      if (editBranchCity && editBranchCity.options.length <= 1) {
        ['Manila','Quezon','Caloocan','Paranaque','Marikina','Taguig','Antipolo','Imus'].forEach(city=>{
          const opt = document.createElement('option'); opt.value = city; opt.textContent = city; editBranchCity.appendChild(opt);
        });
      }
      function fillEditBranchNameDatalist(city){
        editBranchNameList.innerHTML = '';
        let list = [];
        if(city === 'Manila'){
          list = ['MZE BLUMENTRITT','MZE RECTO','MZE OUIAPO','MZE SAN JUAN','MZE GALAS','MZE DELA FUENTE','MZE PACO','MZE PRITIL','JOG PEDRO GIL'];
        } else if(city === 'Quezon'){
          list = ['MZE E.RODRIGUEZ','MZE QUEZON AVENUE','MZE TIMOG','MZE KAMUNING'];
        } else if(city === 'Caloocan'){
          list = ['MZE SANGANDAAN','MZE MAYPAJO'];
        } else if(city === 'Paranaque'){
          list = ['MZE MALIBAY','MZE EVACOM','MZE VALLEY 1','MZE SOLEDAD','MZE NAIA PASAY','MZE CAA- LAS PINAS','MZE FOURTH ESTATE','MZE RFC MOONWALK','MZE RFC BICUTAN'];
        } else if(city === 'Marikina'){
          list = ['MZE SUMULONG- MARIKINA'];
        } else if(city === 'Taguig'){
          list = ['MZE TAGUIG'];
        } else if(city === 'Antipolo'){
          list = ['MZE RFC CAINTA - ANTIPOLO'];
        } else if(city === 'Imus'){
          list = ['JC IMUS'];
        } else {
          list = allBranches;
        }
        list.forEach(b=>{ const o = document.createElement('option'); o.value = b; editBranchNameList.appendChild(o); });
        // Also populate optional select for clearer options in the edit modal
        if (editBranchNameSelect) {
          editBranchNameSelect.innerHTML = '';
          const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = '-- Select Branch --';
          editBranchNameSelect.appendChild(placeholder);
          list.forEach(b=>{
            const opt = document.createElement('option'); opt.value = b; opt.textContent = b; editBranchNameSelect.appendChild(opt);
          });
          editBranchNameSelect.classList.remove('d-none');
        }
      }
      function fillEditBranchLocationDatalist(){
        if(!editBranchLocationList) return;
        editBranchLocationList.innerHTML = '';
        const locs = Array.from(new Set([].concat(Object.keys(branches), ['Las Pinas','Pasay','Marikina','Taguig','Antipolo','Cainta','Moonwalk','Bicutan','Valley 1','Soledad','NAIA','San Juan','Quiapo','Galas','Pritil','Paco','Dela Fuente','Recto','Blumentritt','E. Rodriguez','Timog','Kamuning','Quezon Avenue','Sangandaan','Maypajo','Evacom','Malibay','Fourth Estate','Sumulong'])));
        locs.forEach(l=>{ const o = document.createElement('option'); o.value = l; editBranchLocationList.appendChild(o); });
      }
      editBranchCity.addEventListener('change', function(){
        fillEditBranchNameDatalist(this.value);
        // match register.php behavior: set Branch Location to selected city
        if(editBranchLocation) {
          if(this.value) {
            editBranchLocation.value = this.value;
            try{ editBranchLocation.readOnly = false; }catch(e){}
          } else {
            editBranchLocation.value = '';
            try{ editBranchLocation.readOnly = false; }catch(e){}
          }
        }
        hideEditBranchSuggestions();
      });
      editBranchName.addEventListener('input', function(){
        const addr = getBranchAddress(this.value);
        if(addr){ editBranchLocation.value = addr; try{ editBranchLocation.readOnly = true; }catch(e){} }
        else { try{ editBranchLocation.readOnly = false; }catch(e){} editBranchLocation.value = ''; editBranchLocation.placeholder = 'Type location if branch not found'; }
      });
      // Custom suggestion popup for better UX (click to select)
      const editBranchSuggestions = document.getElementById('edit_branch_suggestions');
      function hideEditBranchSuggestions(){ if(editBranchSuggestions) editBranchSuggestions.classList.add('d-none'); }
      function showEditBranchSuggestions(items){
        if(!editBranchSuggestions) return;
        editBranchSuggestions.innerHTML = '';
        if(!items || !items.length){ hideEditBranchSuggestions(); return; }
        items.forEach(it=>{
          const div = document.createElement('div');
          div.className = 'item';
          div.textContent = it;
          div.tabIndex = 0;
          div.addEventListener('click', function(){
            editBranchName.value = it;
            const addr = getBranchAddress(it);
            if(addr) editBranchLocation.value = addr;
            hideEditBranchSuggestions();
          });
          editBranchSuggestions.appendChild(div);
        });
        editBranchSuggestions.classList.remove('d-none');
      }
      // Show suggestions on input (filtered by selected city)
      editBranchName.addEventListener('input', function(e){
        const q = (this.value||'').toString().trim().toUpperCase();
        const city = (editBranchCity && editBranchCity.value) ? editBranchCity.value : '';
        const list = city && branches[city] ? branches[city] : allBranches;
        if(!q){ hideEditBranchSuggestions(); return; }
        const matches = list.filter(b=> b.toUpperCase().includes(q)).slice(0,12);
        if(matches.length) showEditBranchSuggestions(matches);
        else hideEditBranchSuggestions();
      });
      // Hide suggestions when clicking outside
      document.addEventListener('click', function(e){
        if(!editBranchSuggestions) return;
        if(e.target === editBranchName || e.target.closest('#edit_branch_suggestions')) return;
        hideEditBranchSuggestions();
      });
      if (editBranchNameSelect) {
        editBranchNameSelect.addEventListener('change', function(){ if(this.value){ editBranchName.value = this.value; editBranchName.dispatchEvent(new Event('input',{bubbles:true})); } });
      }
      // init (populate lists according to current city value, matching register.php)
      try{
        const initialCity = (editBranchCity && editBranchCity.value) ? editBranchCity.value : '';
        fillEditBranchNameDatalist(initialCity);
      }catch(e){ fillEditBranchNameDatalist(''); }
      fillEditBranchLocationDatalist();
    }

    // suggestion popup removed per request — use native select + datalist behavior
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
