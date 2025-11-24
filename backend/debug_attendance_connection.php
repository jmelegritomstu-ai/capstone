<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/session_user.php';
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }
$out = ['success'=>true];
$out['session'] = [
  'username' => $_SESSION['username'] ?? null,
  'role' => $_SESSION['role'] ?? null,
  'employee_id_session' => $_SESSION['employee_id'] ?? null,
  'fullname_session' => $_SESSION['fullname'] ?? null
];
try {
  $emp = session_employee($conn);
  $out['employee_lookup'] = $emp ?: null;
  $empId = $emp['emp_id'] ?? ($_SESSION['employee_id'] ?? null);
  if ($empId) {
    $stmt = $conn->prepare("SELECT id, emp_id, full_name, branch_name, branch_location, log_date, check_in, check_out, is_dayoff, created_at FROM attendance_logs WHERE emp_id = ? ORDER BY log_date DESC, id DESC LIMIT 50");
    if ($stmt) {
      $stmt->bind_param('s', $empId);
      $stmt->execute();
      $res = $stmt->get_result();
      $rows = [];
      while ($r = $res->fetch_assoc()) { $rows[] = $r; }
      $stmt->close();
      $out['attendance_rows'] = $rows;
      $out['attendance_count'] = count($rows);
    } else {
      $out['attendance_error'] = 'Failed to prepare attendance query: ' . $conn->error;
    }
  } else {
    $out['attendance_rows'] = [];
    $out['attendance_count'] = 0;
    $out['note'] = 'No emp_id found in session or employee lookup';
  }
} catch (Throwable $e) {
  $out['error'] = $e->getMessage();
}
echo json_encode($out);
