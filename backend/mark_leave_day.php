<?php
// Lightweight helper to mark or unmark specific dates as On Leave for an Account Executive.
// Usage (auditor-only):
//   POST/GET params:
//     emp_id=AE123
//     date=2025-11-10            (single date) OR dates=2025-11-10,2025-11-11 (multi)
//     mode=set | unset           (default: set)
//     ensure=1                   (optional: attempt to add is_onleave column if missing)
//
// Response: JSON { success, message, affected_dates: [...], mode }
//
// Security: requires logged-in auditor role.

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/employee_db.php';
require_once __DIR__ . '/session_user.php';

define('MLD_VERSION', '1.0');

function p($k,$d=null){ return isset($_POST[$k])?trim($_POST[$k]):(isset($_GET[$k])?trim($_GET[$k]):$d); }

if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }

// Require auditor role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'auditor') {
  echo json_encode(['success'=>false,'message'=>'Unauthorized: auditor role required']); exit;
}

$empId = p('emp_id');
$dateSingle = p('date');
$dateListCsv = p('dates');
$mode = strtolower(p('mode','set'));
$attemptEnsure = (int)p('ensure',0) === 1;

if (!$empId || ($dateSingle === '' && $dateListCsv === '')) {
  echo json_encode(['success'=>false,'message'=>'Missing emp_id or date(s)']); exit;
}

// Build date list
$dates = [];
if ($dateSingle) $dates[] = $dateSingle;
if ($dateListCsv) {
  foreach (explode(',', $dateListCsv) as $d) { $d = trim($d); if ($d !== '') $dates[] = $d; }
}
$dates = array_values(array_unique($dates));

// Validate dates
foreach ($dates as $d) {
  $dt = DateTime::createFromFormat('Y-m-d',$d);
  if (!$dt || $dt->format('Y-m-d') !== $d) { echo json_encode(['success'=>false,'message'=>'Invalid date format: '.$d]); exit; }
}

if ($mode !== 'set' && $mode !== 'unset') {
  echo json_encode(['success'=>false,'message'=>'Invalid mode (use set|unset)']); exit;
}

// Ensure employee exists (account_executive or any role)
$stmt = $conn->prepare('SELECT emp_id, full_name, branch_name, branch_location, position FROM employees WHERE emp_id=? LIMIT 1');
if (!$stmt) { echo json_encode(['success'=>false,'message'=>'Prepare failed']); exit; }
$stmt->bind_param('s',$empId);
$stmt->execute(); $res = $stmt->get_result(); $emp = $res?$res->fetch_assoc():null; $stmt->close();
if (!$emp) { echo json_encode(['success'=>false,'message'=>'Employee not found']); exit; }

// Optionally ensure is_onleave column exists
if ($attemptEnsure) {
  $colRes = $conn->query("SHOW COLUMNS FROM attendance_logs LIKE 'is_onleave'");
  if (!$colRes || $colRes->num_rows === 0) {
    $conn->query("ALTER TABLE attendance_logs ADD COLUMN is_onleave TINYINT(1) NOT NULL DEFAULT 0");
  }
  if ($colRes) $colRes->free();
}

// Check column presence
$colRes = $conn->query("SHOW COLUMNS FROM attendance_logs LIKE 'is_onleave'");
$hasOnLeave = $colRes && $colRes->num_rows > 0; if ($colRes) $colRes->free();
if (!$hasOnLeave) { echo json_encode(['success'=>false,'message'=>'is_onleave column missing (run repair_schema or use ensure=1)']); exit; }

$affected = [];
$modeSet = ($mode==='set');

foreach ($dates as $d) {
  // Look for existing row
  $chk = $conn->prepare('SELECT id, check_in FROM attendance_logs WHERE emp_id=? AND log_date=? LIMIT 1');
  if(!$chk){ continue; }
  $chk->bind_param('ss',$empId,$d); $chk->execute(); $r = $chk->get_result(); $row = $r?$r->fetch_assoc():null; $chk->close();

  if ($modeSet) {
    if ($row) {
      // Update flag to onleave without altering existing times
      $upd = $conn->prepare('UPDATE attendance_logs SET is_onleave=1 WHERE id=?');
      if ($upd) { $upd->bind_param('i',$row['id']); $upd->execute(); $upd->close(); $affected[]=$d; }
    } else {
      // Insert placeholder
      $ins = $conn->prepare('INSERT INTO attendance_logs (emp_id, full_name, branch_name, branch_location, position, log_date, is_onleave) VALUES (?,?,?,?,?,?,1)');
      if ($ins) { $ins->bind_param('ssssss',$emp['emp_id'],$emp['full_name'],$emp['branch_name'],$emp['branch_location'],$emp['position'],$d); if($ins->execute()){ $affected[]=$d; } $ins->close(); }
    }
  } else { // unset
    if ($row) {
      $upd = $conn->prepare('UPDATE attendance_logs SET is_onleave=0 WHERE id=?');
      if ($upd) { $upd->bind_param('i',$row['id']); $upd->execute(); $upd->close(); $affected[]=$d; }
    }
  }
}

echo json_encode([
  'success'=>true,
  'message'=>($modeSet? 'Marked On Leave':'Unmarked On Leave').' for '.count($affected).' date(s)',
  'affected_dates'=>$affected,
  'mode'=>$mode,
  'emp_id'=>$empId,
  'version'=>MLD_VERSION
]);
exit;
?>