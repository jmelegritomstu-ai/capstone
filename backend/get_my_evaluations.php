<?php
header('Content-Type: application/json');
require_once __DIR__ . '/session_user.php';

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$emp = session_employee($conn);
if (!$emp || empty($emp['emp_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Account not linked to an employee record']);
    exit;
}

$employee_id = $emp['emp_id'];

// Query params (sanitized)
function q($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
$page = max(1, (int)q('page',1));
$per_page = max(1, min(50, (int)q('per_page',15)));
$sort = strtolower(q('sort','date_desc')); // date_desc | date_asc
$order_by = ($sort === 'date_asc') ? 'evaluation_date ASC, id ASC' : 'evaluation_date DESC, id DESC';

$offset = ($page - 1) * $per_page;

// Ensure evaluations table exists minimally (non-destructive)
$conn->query("CREATE TABLE IF NOT EXISTS evaluations (id INT AUTO_INCREMENT PRIMARY KEY, employee_id VARCHAR(50), branch_name VARCHAR(100) NULL, branch_location VARCHAR(150) NULL, total_sales DECIMAL(12,2) NULL, company_quota DECIMAL(12,2) NULL, sales_gap DECIMAL(12,2) NULL, daily_sales DECIMAL(12,2) NULL, vault_quota DECIMAL(12,2) NULL, vault_collected DECIMAL(12,2) NULL, quality_percentage DECIMAL(5,2) NULL, total_units_sent INT NULL, return_rate_auto DECIMAL(6,2) NULL, absentees INT NULL, lates INT NULL, vault_percent DECIMAL(6,2) NULL, sales_percent DECIMAL(6,2) NULL, attendance_percent DECIMAL(6,2) NULL, return_rate_percent DECIMAL(6,2) NULL, quality_percent DECIMAL(6,2) NULL, total_points_percent DECIMAL(6,2) NULL, evaluator_name VARCHAR(120) NULL, evaluation_date DATE NULL, comments TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Count
$stmtCount = $conn->prepare("SELECT COUNT(*) c FROM evaluations WHERE employee_id = ?");
if (!$stmtCount){ echo json_encode(['success'=>false,'message'=>'Prepare failed']); exit; }
$stmtCount->bind_param('s',$employee_id);
if(!$stmtCount->execute()){ echo json_encode(['success'=>false,'message'=>'Count execute failed']); $stmtCount->close(); exit; }
$resC = $stmtCount->get_result();
$rowC = $resC ? $resC->fetch_assoc() : ['c'=>0];
$total = (int)$rowC['c'];
$stmtCount->close();

// Data
$stmt = $conn->prepare("SELECT id, employee_id, branch_name, branch_location, total_sales, company_quota, sales_gap, daily_sales, vault_quota, vault_collected, quality_percentage, total_units_sent, return_rate_auto, absentees, lates, vault_percent, sales_percent, attendance_percent, return_rate_percent, quality_percent, total_points_percent, evaluator_name, evaluation_date, comments, created_at FROM evaluations WHERE employee_id = ? ORDER BY $order_by LIMIT ? OFFSET ?");
if(!$stmt){ echo json_encode(['success'=>false,'message'=>'Prepare failed']); exit; }
$stmt->bind_param('sii',$employee_id,$per_page,$offset);
if(!$stmt->execute()){ echo json_encode(['success'=>false,'message'=>'Query failed']); $stmt->close(); exit; }
$res = $stmt->get_result();
$rows = [];
while($r = $res->fetch_assoc()){
    // numeric normalization
    foreach(['total_sales','company_quota','sales_gap','daily_sales','vault_quota','vault_collected','quality_percentage','return_rate_auto','vault_percent','sales_percent','attendance_percent','return_rate_percent','quality_percent','total_points_percent'] as $f){
        if(isset($r[$f])) $r[$f] = (float)$r[$f];
    }
    foreach(['total_units_sent','absentees','lates'] as $f){ if(isset($r[$f])) $r[$f] = (int)$r[$f]; }
    // Derived fallback
    $r['sales_gap_calc'] = round(((float)$r['total_sales']) - ((float)$r['company_quota']),2);
    $r['sales_percent_calc'] = ($r['company_quota']>0) ? round(((float)$r['total_sales'] / (float)$r['company_quota']) * 20.0,2) : 0.0;
    $rows[] = $r;
}
$stmt->close();

echo json_encode([
    'success'=>true,
    'employee'=>[
        'emp_id'=>$emp['emp_id'],
        'full_name'=>$emp['full_name'],
        'branch_name'=>$emp['branch_name'],
        'branch_location'=>$emp['branch_location']
    ],
    'summary'=>[
        'count'=>$total,
        'page'=>$page,
        'per_page'=>$per_page,
        'pages'=> $per_page>0 ? (int)ceil($total/$per_page) : 1
    ],
    'data'=>$rows
]);
exit;
?>
