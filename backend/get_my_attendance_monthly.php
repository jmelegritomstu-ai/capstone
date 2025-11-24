<?php
header('Content-Type: application/json');
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/employee_db.php';
require_once __DIR__ . '/holidays.php';
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }

function qp($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : (isset($_POST[$k]) ? trim($_POST[$k]) : $d); }

// Resolve logged-in employee (username first, fallback to employee_id in session)
function get_self_employee_id($conn){
  if (isset($_SESSION['username'])){
    $u = $_SESSION['username'];
    $stmt = $conn->prepare("SELECT emp_id FROM employees WHERE username=? LIMIT 1");
    if ($stmt){ $stmt->bind_param('s',$u); if($stmt->execute()){ $res=$stmt->get_result(); $row=$res?$res->fetch_assoc():null; $stmt->close(); if($row && !empty($row['emp_id'])) return $row['emp_id']; } else { $stmt->close(); } }
  }
  if (isset($_SESSION['employee_id']) && $_SESSION['employee_id']) return $_SESSION['employee_id'];
  return null;
}

$year = (int)qp('year', date('Y'));
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

$empId = get_self_employee_id($conn);
if (!$empId){ echo json_encode(['success'=>false,'message'=>'Not logged in']); exit; }

$rows = array_fill(1, 12, 0);
$late = array_fill(1, 12, 0);
$onleave = array_fill(1, 12, 0);

$cutoff = '08:15:00';
$sql = "SELECT MONTH(log_date) m, COUNT(DISTINCT log_date) days,
               SUM(CASE WHEN TIME(check_in) > ? THEN 1 ELSE 0 END) late_count
        FROM attendance_logs
        WHERE emp_id = ? AND YEAR(log_date) = ? AND check_in IS NOT NULL
        GROUP BY MONTH(log_date)";
$stmt = $conn->prepare($sql);
if ($stmt){
  $stmt->bind_param('ssi', $cutoff, $empId, $year);
  if ($stmt->execute()){
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()){
      $m = (int)$r['m'];
      $rows[$m] = (int)$r['days'];
      $late[$m] = (int)$r['late_count'];
    }
  }
  $stmt->close();
}

// Per-month On Leave day counts (distinct dates flagged is_onleave=1)
$leaveSql = "SELECT MONTH(log_date) m, COUNT(DISTINCT log_date) leave_days
             FROM attendance_logs
             WHERE emp_id = ? AND YEAR(log_date) = ? AND is_onleave = 1
             GROUP BY MONTH(log_date)";
$stmt2 = $conn->prepare($leaveSql);
if ($stmt2){
  $stmt2->bind_param('si', $empId, $year);
  if ($stmt2->execute()){
    $res2 = $stmt2->get_result();
    while($r2 = $res2->fetch_assoc()){
      $m2 = (int)$r2['m'];
      $onleave[$m2] = (int)$r2['leave_days'];
    }
  }
  $stmt2->close();
}

  // Add holidays as present: for each holiday in the year, if employee has no check_in for that date,
  // count it as a present day for the month (so holidays are treated as present days).
  $hols = get_holidays_for_year($year);
  $chkStmt = $conn->prepare("SELECT COUNT(*) AS c FROM attendance_logs WHERE emp_id=? AND log_date=? AND check_in IS NOT NULL");
  foreach($hols as $h){
    $m = (int)date('n', strtotime($h));
    // if already has attendance record for that date (with check_in), don't double-count
    if ($chkStmt){
      $chkStmt->bind_param('ss', $empId, $h);
      if ($chkStmt->execute()){
        $r = $chkStmt->get_result()->fetch_assoc();
        $c = (int)($r['c'] ?? 0);
        if ($c === 0) {
          $rows[$m] = ($rows[$m] ?? 0) + 1;
        }
      }
    }
  }
  if ($chkStmt) $chkStmt->close();

echo json_encode([
  'success'=>true,
  'year'=>$year,
  'data'=> array_values($rows),
  'late'=> array_values($late),
  'onleave'=> array_values($onleave),
  'emp_id'=> $empId
]);
exit;
?>
