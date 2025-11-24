<?php
header('Content-Type: application/json');
require_once __DIR__ . '/employee_db.php';
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }
function qs($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
$start = qs('start_date'); $end = qs('end_date'); $limit = (int)qs('limit',100);
$re_date='/^\d{4}-\d{2}-\d{2}$/';
if (!$start || !preg_match($re_date,$start) || !$end || !preg_match($re_date,$end)) {
  $d=new DateTime('now', new DateTimeZone('Asia/Manila'));
  $first = new DateTime($d->format('Y-m-01'), new DateTimeZone('Asia/Manila'));
  $last  = new DateTime($d->format('Y-m-t'), new DateTimeZone('Asia/Manila'));
  $start=$first->format('Y-m-d'); $end=$last->format('Y-m-d');
}
if ($limit<1||$limit>500) $limit=100;

$sql = "SELECT e.employee_id, COALESCE(emp.full_name, e.employee_id) full_name,
  COALESCE(emp.branch_name, emp.branch_location, 'Unknown') branch,
  SUM(e.total_points_percent) total_points,
  ROUND(AVG(e.total_points_percent),2) avg_points,
  COUNT(*) evaluations
  FROM evaluations e
  LEFT JOIN employees emp ON TRIM(UPPER(emp.emp_id)) = TRIM(UPPER(e.employee_id))
  WHERE e.evaluation_date BETWEEN ? AND ? AND e.employee_id <> ''
  GROUP BY e.employee_id
  ORDER BY total_points DESC
  LIMIT ?";
$stmt = $conn->prepare($sql);
if (!$stmt){ echo json_encode(['success'=>false,'message'=>'Prepare failed']); exit; }
$stmt->bind_param('ssi',$start,$end,$limit);
if(!$stmt->execute()){ echo json_encode(['success'=>false,'message'=>'Execute failed']); $stmt->close(); exit; }
$res=$stmt->get_result(); $rows=[]; $rank=1; while($row=$res->fetch_assoc()){ $row['rank']=$rank++; $row['total_points']=(float)$row['total_points']; $row['avg_points']=(float)$row['avg_points']; $row['evaluations']=(int)$row['evaluations']; $rows[]=$row; }
$stmt->close();
echo json_encode(['success'=>true,'range'=>['start'=>$start,'end'=>$end],'data'=>$rows]);
?>
