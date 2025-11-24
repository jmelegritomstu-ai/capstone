<?php
header('Content-Type: application/json');
require_once __DIR__ . '/session_user.php';
$out = [ 'success'=>true ];
$out['session'] = [
  'username' => $_SESSION['username'] ?? null,
  'role' => $_SESSION['role'] ?? null,
  'employee_id_session' => $_SESSION['employee_id'] ?? null
];
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }
 $emp = null;
try { $emp = session_employee($conn); } catch (Throwable $e) { $out['error'] = $e->getMessage(); }
// Enforce account blocking: terminated or suspended users should be logged out and denied access
if ($emp && isset($emp['status'])) {
  $st = strtolower(trim((string)$emp['status']));
  if ($st === 'terminated') {
    // Destroy session to prevent further access
    session_unset(); session_destroy();
    echo json_encode(['success'=>false,'message'=>'Account terminated','blocked'=>'terminated']); exit;
  }
  if ($st === 'suspended') {
    session_unset(); session_destroy();
    echo json_encode(['success'=>false,'message'=>'Account suspended. Contact your auditor to restore access.','blocked'=>'suspended']); exit;
  }
}

$out['employee_lookup'] = $emp ?: null;
// Column existence quick check
$cols = ['emp_id','full_name','branch_name','branch_location','branch_city','position','gender','birthday','email','auditor_name'];
$existing = [];
$res = $conn->query("SHOW COLUMNS FROM employees");
if ($res) { while($r=$res->fetch_assoc()){ $existing[] = $r['Field']; } }
$out['columns_present'] = $existing;
$out['missing_important'] = array_values(array_diff($cols,$existing));
echo json_encode($out);
