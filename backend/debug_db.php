<?php
header('Content-Type: application/json');
require_once __DIR__ . '/employee_db.php';
$out = [ 'success'=>false ];
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'No $conn available']); exit; }
try {
  // Check mysqli extension
  if (!class_exists('mysqli')) { echo json_encode(['success'=>false,'message'=>'mysqli extension not loaded in this SAPI']); exit; }
  $out['php_version'] = PHP_VERSION;
  $out['mysqli_client'] = mysqli_get_client_info();
  // Users table exists?
  $res = $conn->query("SHOW TABLES LIKE 'users'");
  $out['users_table_exists'] = ($res && $res->num_rows>0);
  if ($out['users_table_exists']) {
    $cntRes = $conn->query("SELECT COUNT(*) AS c FROM users");
    $row = $cntRes? $cntRes->fetch_assoc():null;
    $out['users_count'] = $row? (int)$row['c'] : null;
  }
  // Attendance logs table exists?
  $res2 = $conn->query("SHOW TABLES LIKE 'attendance_logs'");
  $out['attendance_logs_exists'] = ($res2 && $res2->num_rows>0);
  $out['success'] = true;
} catch (Exception $e) {
  $out['success'] = false; $out['message'] = $e->getMessage();
}
echo json_encode($out);
