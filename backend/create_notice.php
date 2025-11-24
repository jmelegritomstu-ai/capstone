<?php
header('Content-Type: application/json');
require_once __DIR__ . '/session_user.php';

if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }

// Only auditors can create notices
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'auditor') {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Forbidden']);
  exit;
}

$emp_id = isset($_POST['emp_id']) ? trim($_POST['emp_id']) : '';
$employee_name = isset($_POST['employee_name']) ? trim($_POST['employee_name']) : '';
$notice_type = isset($_POST['notice_type']) ? trim($_POST['notice_type']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$date_issued = isset($_POST['date_issued']) ? trim($_POST['date_issued']) : '';

if ($emp_id === '' || $notice_type === '' || $description === '' || $date_issued === '') {
  echo json_encode(['success'=>false,'message'=>'Missing required fields']);
  exit;
}

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_issued)) {
  echo json_encode(['success'=>false,'message'=>'Invalid date']);
  exit;
}

// Determine category based on notice_type
$positive = [
  'Outstanding Performance','Excellent Customer Service','Perfect Attendance','Team Player Award',
  'Sales Achievement','Employee of the Month','Positive Attitude Recognition','Leadership Recognition','Punctuality Award','Extra Effort Recognition'
];
$warnings = [
  'Rate of Return Issue','Warning','Late Submission','Tardiness','Unprofessional Behavior','Missed Deadline',
  'Unauthorized Absence','Negligence of Duty','Violation of Company Policy','Failure to Meet Sales Target','Misconduct','Disrespectful Behavior'
];
$category = 'warning';
if (in_array($notice_type, $positive, true)) $category = 'positive';
elseif (in_array($notice_type, $warnings, true)) $category = 'warning';
else $category = 'announcement';

$issued_by = $_SESSION['username'] ?? 'system';
$emp = session_employee($conn);
if ($emp && isset($_SESSION['role']) && $_SESSION['role']==='auditor') {
  // If the auditor has an employee record, prefer their full name
  // Note: session_employee returns current user; for auditor we want their name
  $issued_by = $emp['full_name'] ?: $issued_by;
}

// Ensure table exists (safety if schema.sql not run)
$conn->query("CREATE TABLE IF NOT EXISTS notices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  emp_id VARCHAR(50) NOT NULL,
  employee_name VARCHAR(150) NULL,
  notice_type VARCHAR(100) NOT NULL,
  category ENUM('positive','warning','announcement') DEFAULT 'warning',
  description TEXT NOT NULL,
  issued_by VARCHAR(120) NULL,
  date_issued DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notice_emp_date (emp_id, date_issued)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $conn->prepare("INSERT INTO notices (emp_id, employee_name, notice_type, category, description, issued_by, date_issued) VALUES (?,?,?,?,?,?,?)");
if (!$stmt) { echo json_encode(['success'=>false,'message'=>'Prepare failed: '.$conn->error]); exit; }
$stmt->bind_param('sssssss', $emp_id, $employee_name, $notice_type, $category, $description, $issued_by, $date_issued);
$ok = $stmt->execute();
if (!$ok) { echo json_encode(['success'=>false,'message'=>'Insert failed: '.$stmt->error]); $stmt->close(); exit; }
$id = $stmt->insert_id;
$stmt->close();

echo json_encode(['success'=>true,'id'=>$id]);
