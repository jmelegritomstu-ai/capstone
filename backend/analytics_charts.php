<?php
header('Content-Type: application/json');
require_once __DIR__ . '/employee_db.php';
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }
function qs($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
$start = qs('start_date'); $end = qs('end_date');
$re_date='/^\d{4}-\d{2}-\d{2}$/';
if (!$start || !preg_match($re_date,$start) || !$end || !preg_match($re_date,$end)) {
  $d = new DateTime('now', new DateTimeZone('Asia/Manila'));
  $first = new DateTime($d->format('Y-m-01'), new DateTimeZone('Asia/Manila'));
  $last = new DateTime($d->format('Y-m-t'), new DateTimeZone('Asia/Manila'));
  $start = $first->format('Y-m-d'); $end = $last->format('Y-m-d');
}
$out=['success'=>true,'range'=>['start'=>$start,'end'=>$end]];

// Branch sales + quota dataset
$stmtSales = $conn->prepare("SELECT COALESCE(branch_name, branch_location, 'Unknown') branch,
  SUM(total_sales) total_sales,
  SUM(company_quota) total_quota
  FROM evaluations WHERE evaluation_date BETWEEN ? AND ?
  GROUP BY COALESCE(branch_name, branch_location, 'Unknown') ORDER BY total_sales DESC");
$branchSales=[]; if ($stmtSales){ $stmtSales->bind_param('ss',$start,$end); $stmtSales->execute(); $r=$stmtSales->get_result(); while($row=$r->fetch_assoc()){ $row['total_sales']=(float)$row['total_sales']; $row['total_quota']=(float)$row['total_quota']; $branchSales[]=$row; } $stmtSales->close(); }
$out['branch_sales']=$branchSales;

// Branch attendance (count of check_ins)
$stmtAtt = $conn->prepare("SELECT COALESCE(branch_name, branch_location, 'Unknown') branch, COUNT(*) attendance_count
  FROM attendance_logs WHERE log_date BETWEEN ? AND ? AND check_in IS NOT NULL
  GROUP BY COALESCE(branch_name, branch_location, 'Unknown') ORDER BY attendance_count DESC");
$branchAttendance=[]; if ($stmtAtt){ $stmtAtt->bind_param('ss',$start,$end); $stmtAtt->execute(); $ra=$stmtAtt->get_result(); while($row=$ra->fetch_assoc()){ $row['attendance_count']=(int)$row['attendance_count']; $branchAttendance[]=$row; } $stmtAtt->close(); }
$out['branch_attendance']=$branchAttendance;

// Combined comparison (align by branch name)
$map=[];
foreach($branchSales as $row){ $b=$row['branch']; $map[$b] = ['branch'=>$b,'sales'=>$row['total_sales'],'quota'=>$row['total_quota'],'attendance'=>0]; }
foreach($branchAttendance as $row){ $b=$row['branch']; if(!isset($map[$b])) $map[$b]=['branch'=>$b,'sales'=>0,'quota'=>0,'attendance'=>0]; $map[$b]['attendance']=$row['attendance_count']; }
$out['branch_comparison']=array_values($map);

echo json_encode($out);
?>
