<?php
header('Content-Type: application/json');
require_once __DIR__ . '/employee_db.php';
require_once __DIR__ . '/holidays.php';

// Params: emp_id, month (YYYY-MM)
$emp_id = isset($_GET['emp_id']) ? trim($_GET['emp_id']) : '';
$month = isset($_GET['month']) ? trim($_GET['month']) : '';
if ($emp_id === '' || $month === '' || !preg_match('/^\d{4}-\d{2}$/', $month)) {
  echo json_encode(['success'=>false,'message'=>'Missing or invalid emp_id/month']);
  exit;
}

list($year,$mon) = explode('-', $month);
$year = (int)$year; $mon = (int)$mon;
if ($mon < 1 || $mon > 12) { echo json_encode(['success'=>false,'message'=>'Invalid month']); exit; }

$start = sprintf('%04d-%02d-01', $year, $mon);
$end = date('Y-m-d', strtotime($start.' +1 month -1 day'));

// Build set of provisional working days (Mon-Sat excluding Sundays)
$workingDays = [];
for ($d=1; $d<=31; $d++) {
  $dt = strtotime(sprintf('%04d-%02d-%02d', $year,$mon,$d));
  if ((int)date('m',$dt) !== $mon) break;
  $w = (int)date('w',$dt); // 0 Sun .. 6 Sat
  if ($w === 0) continue; // skip Sunday
  $workingDays[] = date('Y-m-d',$dt);
}

// Fetch employee preferred dayoff if set (1=Mon..6=Sun)
$pref = null;
$stmtPref = $conn->prepare("SELECT dayoff_weekday FROM employees WHERE emp_id=? LIMIT 1");
if ($stmtPref) {
  $stmtPref->bind_param('s',$emp_id);
  if ($stmtPref->execute()) {
    $rp=$stmtPref->get_result();
    if($rp && $rowp=$rp->fetch_assoc()){
      $pref = isset($rowp['dayoff_weekday']) ? (int)$rowp['dayoff_weekday'] : null;
      if ($pref < 1 || $pref > 6) $pref = null;
    }
  }
  $stmtPref->close();
}

// Automatic day off policy: one scheduled day off in 1-15 and one in 16-end.
// Prefer the LAST occurrence of preferred weekday within the half (1=Mon..5=Fri, 6=Sun); if not present,
// prefer the LAST Saturday; else the LAST available working day of that half.
function pickDayOffLast($days, $preferredWeekday=null) {
  $preferred = null; $saturday = null; $last = null;
  foreach ($days as $d) {
    $ts = strtotime($d);
    $w = (int)date('w', $ts); // 0 Sun .. 6 Sat
    $last = $d;
    if ($w === 6) { $saturday = $d; }
    if ($preferredWeekday !== null) {
      // Map 1..5 => Mon..Fri (w=1..5), 6 => Sunday (w=0) which won't be in $days since Sundays excluded
      if (($preferredWeekday >= 1 && $preferredWeekday <= 5 && $w === $preferredWeekday) || ($preferredWeekday === 6 && $w === 0)) {
        $preferred = $d; // keep assigning to capture the LAST occurrence
      }
    }
  }
  if ($preferred) return $preferred;
  if ($saturday) return $saturday;
  return $last;
}

// Split working days into halves
$firstHalf = array_values(array_filter($workingDays, function($d){ return (int)substr($d,8,2) <= 15; }));
$secondHalf = array_values(array_filter($workingDays, function($d){ return (int)substr($d,8,2) >= 16; }));
$dayOffs = [];
if (!empty($firstHalf)) { $dayOffs[] = pickDayOffLast($firstHalf, $pref); }
if (!empty($secondHalf)) { $dayOffs[] = pickDayOffLast($secondHalf, $pref); }
$dayOffs = array_values(array_unique($dayOffs));

// Filter workingDays to exclude dayOffs
$effectiveWorkingDays = array_values(array_diff($workingDays, $dayOffs));

// Fetch FIRST check-in per day for employee in month
$sql = "SELECT log_date, MIN(check_in) AS first_in FROM attendance_logs WHERE emp_id=? AND log_date BETWEEN ? AND ? GROUP BY log_date";
$stmt = $conn->prepare($sql);
if (!$stmt) { echo json_encode(['success'=>false,'message'=>'DB error']); exit; }
$stmt->bind_param('sss', $emp_id, $start, $end);
$stmt->execute();
$res = $stmt->get_result();
$firstIns = [];
while ($row = $res->fetch_assoc()) {
  if (!empty($row['log_date'])) {
    $firstIns[$row['log_date']] = $row['first_in'];
  }
}
$stmt->close();

$absentees = 0; $lates = 0;
$lateThreshold = '09:15:00';
foreach ($effectiveWorkingDays as $wd) {
  $ci = $firstIns[$wd] ?? null;
  // If this day is a recognized holiday, do not count as absentee (treated as present)
  if (in_array($wd, get_holidays_for_year($year))) { continue; }
  if (!$ci) { $absentees++; continue; }
  $t = substr($ci, 11, 8);
  if ($t > $lateThreshold) $lates++;
}

echo json_encode([
  'success'=>true,
  'emp_id'=>$emp_id,
  'month'=>$month,
  'from'=>$start,
  'to'=>$end,
  'cutoff'=>$lateThreshold,
  'working_days'=>count($effectiveWorkingDays),
  'dayoffs'=>$dayOffs,
  'absentees'=>$absentees,
  'lates'=>$lates
]);
