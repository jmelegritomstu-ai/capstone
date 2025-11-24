<?php
require_once __DIR__ . '/../backend/session_user.php';
require_auth();
$username = $_SESSION['username'];

// Prefer single-source of truth from employees via helper
$emp = session_employee($conn);
// Minimal user role from session
$user = [ 'role' => $_SESSION['role'] ?? '' ];

// Derived display values
$displayName = $emp['full_name'] ?? ($user['fullname'] ?? $username);
$position    = $emp['position']   ?? ($user['position'] ?? '-');
$branchName  = $emp['branch_name']?? ($user['branch']   ?? '-');
$branchCity  = $emp['branch_city'] ?? ($emp['branch_location'] ?? '');
$empId       = $emp['emp_id'] ?? ($user['employee_id'] ?? '-');
$gender      = $emp['gender'] ?? '';
$birthday    = $emp['birthday'] ?? '';
$dateHired   = $emp['date_hired'] ?? '';
$contract    = $emp['contract_type'] ?? '';
$shiftSched  = $emp['shift_schedule'] ?? '';
$status      = $emp['status'] ?? '';
$contact     = $emp['contact_number'] ?? '';
$email       = $emp['email'] ?? '';
$address     = $emp['address'] ?? '';
$auditor     = $emp['auditor_name'] ?? '';
$role        = $user['role'] ?? '';

// Age
$age = '';
if (!empty($birthday) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
  try { $age = (new DateTime($birthday))->diff(new DateTime('now'))->y; } catch(Exception $e){ $age = ''; }
}

// Profile image
$imgSrc = 'https://ui-avatars.com/api/?name='.urlencode($displayName).'&background=6f42c1&color=fff';
if (!empty($emp['profile_img'])) {
  $imgPath = '../uploads/' . basename($emp['profile_img']);
  if (is_file(__DIR__ . '/../uploads/' . basename($emp['profile_img']))) {
    $imgSrc = $imgPath;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MZE Cellular</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/profile.css" rel="stylesheet" />
  </head>
<body>
<aside class="sidebar">
  <h2>MZE Cellular</h2>
  <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a>
  <a class="nav-link" href="sales.php"><i class="fa-solid fa-chart-line me-2"></i>Sales Performance</a>
  <a class="nav-link" href="attendance.php"><i class="fa-solid fa-user-check me-2"></i>Attendance</a>
  <a class="nav-link" href="employees.php"><i class="fa-solid fa-users me-2"></i>People</a>
  <a class="nav-link" href="forms.php"><i class="fa-solid fa-file-pen me-2"></i>Forms / Evaluations</a>
  <a class="nav-link" href="notices.php"><i class="fa-solid fa-bullhorn me-2"></i>Notices</a>
  <a class="nav-link" href="requests.php"><i class="fa-solid fa-chalkboard-user"></i>Training Requests</a>
  <a class="nav-link active" href="profile.php"><i class="fa-solid fa-user-circle me-2"></i>Profile</a>
  <a class="nav-link text-danger" href="logout.php" style="position:absolute;bottom:60px;left:20px;"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a>
  <small style="position:absolute;bottom:20px;left:20px;opacity:0.8;font-size:12px;">© MZE Cellular</small>
</aside>

<div class="main-content">
  <div class="profile-header">
    <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Profile Picture">
    <div>
      <h2><?php echo htmlspecialchars($displayName); ?></h2>
      <p><?php echo htmlspecialchars($position.' | '.$branchName); ?></p>
    </div>
  </div>

  <div class="profile-content">
    <h3 class="section-title">Basic Information</h3>
    <div class="info-grid">
      <div class="info-item"><label>ID Number</label><span><?php echo htmlspecialchars($empId); ?></span></div>
      <div class="info-item"><label>Full Name</label><span><?php echo htmlspecialchars($displayName); ?></span></div>
      <div class="info-item"><label>Gender</label><span><?php echo htmlspecialchars($gender ?: '-'); ?></span></div>
      <div class="info-item"><label>Birthday</label><span><?php echo htmlspecialchars($birthday ?: '-'); ?></span></div>
      <div class="info-item"><label>Age</label><span><?php echo htmlspecialchars($age !== '' ? $age : '-'); ?></span></div>
      <div class="info-item"><label>Branch Location</label><span><?php echo htmlspecialchars($branchCity ?: '-'); ?></span></div>
      <div class="info-item"><label>Position</label><span><?php echo htmlspecialchars($position ?: '-'); ?></span></div>
    </div>

    <hr class="my-4">

    <h3 class="section-title">Work Details</h3>
    <div class="info-grid">
      <div class="info-item"><label>Date Hired</label><span><?php echo htmlspecialchars($dateHired ?: '-'); ?></span></div>
      <div class="info-item"><label>Contract Type</label><span><?php echo htmlspecialchars($contract ?: '-'); ?></span></div>
  <?php if ($role === 'account_executive'): ?>
      <div class="info-item"><label>Auditor</label><span><?php echo htmlspecialchars($auditor ?: '-'); ?></span></div>
      <?php endif; ?>
      <div class="info-item"><label>Shift Schedule</label><span><?php echo htmlspecialchars($shiftSched ?: '-'); ?></span></div>
      <div class="info-item"><label>Branch Name</label><span><?php echo htmlspecialchars($branchName ?: '-'); ?></span></div>
      <div class="info-item"><label>Employment Status</label><span><?php echo htmlspecialchars($status ?: '-'); ?></span></div>
    </div>

    <hr class="my-4">

    <h3 class="section-title">Contact Information</h3>
    <div class="info-grid">
      <div class="info-item"><label>Contact Number</label><span><?php echo htmlspecialchars($contact ?: '-'); ?></span></div>
      <div class="info-item"><label>Email</label><span><?php echo htmlspecialchars($email ?: '-'); ?></span></div>
      <div class="info-item" style="grid-column:1/-1;"><label>Address</label><span><?php echo htmlspecialchars($address ?: '-'); ?></span></div>
    </div>
  </div>
</div>

</body>
</html>
