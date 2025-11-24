<?php
header('Content-Type: application/json');
require_once __DIR__ . '/employee_db.php';

if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }

// Helper fetch query param
function qs($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

// Date range: default current month
$start = qs('start_date');
$end   = qs('end_date');
$re_date = '/^\d{4}-\d{2}-\d{2}$/';
if (!$start || !preg_match($re_date,$start) || !$end || !preg_match($re_date,$end)) {
  $d = new DateTime('now', new DateTimeZone('Asia/Manila'));
  $first = new DateTime($d->format('Y-m-01'), new DateTimeZone('Asia/Manila'));
  $last  = new DateTime($d->format('Y-m-t'), new DateTimeZone('Asia/Manila'));
  $start = $first->format('Y-m-d');
  $end   = $last->format('Y-m-d');
}

$out = [ 'success'=>true, 'range'=>['start'=>$start,'end'=>$end] ];

// Total account executives (robust: role or position-based)
$sqlAE = "SELECT COUNT(*) c FROM employees
          WHERE (
            LOWER(COALESCE(role,'')) = 'account_executive'
            OR LOWER(COALESCE(role,'')) = 'ae'
            OR LOWER(COALESCE(position,'')) LIKE '%account%executive%'
          )";
$resAE = $conn->query($sqlAE);
$out['total_account_executives'] = ($resAE && ($row=$resAE->fetch_assoc())) ? (int)$row['c'] : 0;

// Branch coverage (distinct branches that have AEs) using same robust criteria
$resBC = $conn->query("SELECT COUNT(DISTINCT COALESCE(branch_name, branch_location, branch_city)) c FROM employees
  WHERE (LOWER(COALESCE(role,''))='account_executive' OR LOWER(COALESCE(role,''))='ae' OR LOWER(COALESCE(position,'')) LIKE '%account%executive%')");
$out['branch_coverage'] = $resBC && ($row=$resBC->fetch_assoc()) ? (int)$row['c'] : 0;

// Total market sales (month)
$stmtSales = $conn->prepare("SELECT COALESCE(SUM(total_sales),0) s FROM evaluations WHERE evaluation_date BETWEEN ? AND ?");
if ($stmtSales){ $stmtSales->bind_param('ss',$start,$end); $stmtSales->execute(); $r = $stmtSales->get_result(); $out['total_market_sales'] = $r && ($row=$r->fetch_assoc()) ? (float)$row['s'] : 0.0; $stmtSales->close(); } else { $out['total_market_sales']=0.0; }

// Best account executive by total points (sum of total_points_percent)
$stmtBest = $conn->prepare("SELECT e.employee_id, COALESCE(emp.full_name, e.employee_id) full_name, SUM(e.total_points_percent) pts, ROUND(AVG(e.total_points_percent),2) avg_pts, COUNT(*) evals
  FROM evaluations e
  LEFT JOIN employees emp ON TRIM(UPPER(emp.emp_id)) = TRIM(UPPER(e.employee_id))
  WHERE e.evaluation_date BETWEEN ? AND ? AND e.employee_id <> ''
  GROUP BY e.employee_id
  ORDER BY pts DESC
  LIMIT 1");
if ($stmtBest){ $stmtBest->bind_param('ss',$start,$end); $stmtBest->execute(); $rB = $stmtBest->get_result(); $out['best_account_executive'] = $rB && ($row=$rB->fetch_assoc()) ? $row : null; $stmtBest->close(); } else { $out['best_account_executive']=null; }

// Per-branch AE counts
$resPBAE = $conn->query("SELECT COALESCE(branch_name, branch_location, branch_city, 'Unknown') branch, COUNT(*) total_ae
  FROM employees WHERE role='account_executive'
  GROUP BY COALESCE(branch_name, branch_location, branch_city, 'Unknown') ORDER BY total_ae DESC");
$branchAE = [];
if ($resPBAE){ while($row=$resPBAE->fetch_assoc()){ $row['total_ae']=(int)$row['total_ae']; $branchAE[]=$row; } }
$out['per_branch_accounts'] = $branchAE;

// Per-branch sales (month)
$stmtPBS = $conn->prepare("SELECT COALESCE(e.branch_name, e.branch_location, 'Unknown') branch, SUM(e.total_sales) total_sales
  FROM evaluations e
  WHERE e.evaluation_date BETWEEN ? AND ?
  GROUP BY COALESCE(e.branch_name, e.branch_location, 'Unknown')
  ORDER BY total_sales DESC");
$branchSales = [];
if ($stmtPBS){ $stmtPBS->bind_param('ss',$start,$end); $stmtPBS->execute(); $rS=$stmtPBS->get_result(); while($row=$rS->fetch_assoc()){ $row['total_sales']=(float)$row['total_sales']; $branchSales[]=$row; } $stmtPBS->close(); }
$out['per_branch_sales'] = $branchSales;

echo json_encode($out);
?>
