<?php
header('Content-Type: application/json');
require_once __DIR__ . '/session_user.php';
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }

// Only auditors can trigger generation for arbitrary employees; AEs can generate for themselves
$role = $_SESSION['role'] ?? null;
$empSession = session_employee($conn);
$target_emp_id = isset($_POST['emp_id']) ? trim($_POST['emp_id']) : '';
if ($role === 'account_executive') { $target_emp_id = $empSession['emp_id'] ?? ''; }
if ($target_emp_id === '') { echo json_encode(['success'=>false,'message'=>'emp_id required']); exit; }

$days = isset($_POST['days']) ? max(7, (int)$_POST['days']) : 30;
$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$fromDate = (clone $now)->modify('-'.$days.' days')->format('Y-m-d');



// Helper: next N Mondays
function nextMondays($n = 3) {
  $tz = new DateTimeZone('Asia/Manila');
  $d = new DateTime('now', $tz);
  // Move to next Monday
  while ((int)$d->format('N') !== 1) { $d->modify('+1 day'); }
  $dates = [];
  for ($i=0; $i<$n; $i++) { $dates[] = $d->format('Y-m-d'); $d->modify('+7 days'); }
  return $dates;
}

$scheduled = nextMondays(4);
$inserts = [];

// Compute attendance lates in last $days
$lateCutoff = '08:15:00';
$stmt = $conn->prepare("SELECT COUNT(*) AS lates FROM attendance_logs WHERE emp_id = ? AND check_in IS NOT NULL AND TIME(check_in) > ? AND log_date >= ?");
$stmt->bind_param('sss', $target_emp_id, $lateCutoff, $fromDate);
$stmt->execute();
$res = $stmt->get_result();
$lateCount = 0; if ($res) { $row = $res->fetch_assoc(); $lateCount = (int)($row['lates'] ?? 0); }
$stmt->close();

if ($lateCount >= 3) {
  $inserts[] = [
    'title' => 'Time Management and Productivity',
    'description' => 'Reduce tardiness through prioritization, planning, and morning routines. Includes daily checklists and time blocking.',
    'source' => 'attendance',
    'scheduled_date' => $scheduled[0] ?? null
  ];
}

// Latest evaluation
$evalSql = "SELECT COALESCE(return_rate_percent, return_rate_auto, 0) AS rr,
                   COALESCE(quality_percent, quality_percentage, 0) AS qp,
                   COALESCE(sales_percent, 0) AS sp
            FROM evaluations WHERE employee_id = ?
            ORDER BY evaluation_date DESC, id DESC LIMIT 1";
$stmt = $conn->prepare($evalSql);
$stmt->bind_param('s', $target_emp_id);
$stmt->execute();
$rr = 0; $qp = 100; $sp = 100;
if ($r = $stmt->get_result()) {
  if ($row = $r->fetch_assoc()) { $rr = (float)$row['rr']; $qp = (float)$row['qp']; $sp = (float)$row['sp']; }
}
$stmt->close();

if ($rr > 5.0) {
  $inserts[] = [
    'title' => 'Customer Engagement and Retention',
    'description' => 'Techniques to reduce returns: proper product demo, setting expectations, and post-sale follow-ups to improve retention.',
    'source' => 'evaluation',
    'scheduled_date' => $scheduled[1] ?? null
  ];
}
if ($sp < 80.0) {
  $inserts[] = [
    'title' => 'Advanced Sales Techniques',
    'description' => 'Upselling, cross-selling, and objection handling tailored to MZE product lines to lift conversion rates.',
    'source' => 'evaluation',
    'scheduled_date' => $scheduled[2] ?? null
  ];
}
if ($qp < 85.0) {
  $inserts[] = [
    'title' => 'Quality Assurance Basics',
    'description' => 'Device inspection checklist, documentation, and QA handover to minimize defects and improve quality percentage.',
    'source' => 'evaluation',
    'scheduled_date' => $scheduled[3] ?? null
  ];
}

// Insert deduped recommendations
$created = 0;
if ($inserts) {
  $stmt = $conn->prepare("INSERT IGNORE INTO training_recommendations (emp_id, title, description, scheduled_date, status, source) VALUES (?,?,?,?, 'active', ?)");
  foreach ($inserts as $it) {
    $title = $it['title']; $desc = $it['description']; $sd = $it['scheduled_date']; $src = $it['source'];
    $stmt->bind_param('sssss', $target_emp_id, $title, $desc, $sd, $src);
    if ($stmt->execute()) { $created += $stmt->affected_rows > 0 ? 1 : 0; }
  }
  $stmt->close();
}

echo json_encode(['success'=>true,'created'=>$created,'emp_id'=>$target_emp_id,'late_count'=>$lateCount,'metrics'=>['return_rate'=>$rr,'quality'=>$qp,'sales'=>$sp]]);
