<?php
header('Content-Type: application/json');

require_once __DIR__ . '/employee_db.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not initialized']);
    exit;
}

// Helpers
function get_qs($key, $default = null) {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

$employee_id = get_qs('employee_id'); // optional
$branch_name = get_qs('branch_name'); // optional (exact match)
$branch_location = get_qs('branch_location'); // optional (exact match)
$start_date  = get_qs('start_date');  // optional YYYY-MM-DD
$end_date    = get_qs('end_date');    // optional YYYY-MM-DD
$page        = max(1, (int) get_qs('page', 1));
$per_page    = max(1, min(100, (int) get_qs('per_page', 20)));
$sort        = strtolower(get_qs('sort', 'date_desc'));

$order_by = 'ev.evaluation_date DESC, ev.id DESC';
if ($sort === 'date_asc') {
    $order_by = 'ev.evaluation_date ASC, ev.id ASC';
}

$where_count = [];
$where_data = [];
$types = '';
$params = [];

if ($employee_id !== null && $employee_id !== '') {
    $where_count[] = 'employee_id = ?';
    $where_data[]  = 'ev.employee_id = ?';
    $types  .= 's';
    $params[] = $employee_id;
}
if ($branch_name !== null && $branch_name !== '') {
    $where_count[] = 'branch_name = ?';
    $where_data[]  = 'ev.branch_name = ?';
    $types  .= 's';
    $params[] = $branch_name;
}
if ($branch_location !== null && $branch_location !== '') {
    $where_count[] = 'branch_location = ?';
    $where_data[]  = 'ev.branch_location = ?';
    $types  .= 's';
    $params[] = $branch_location;
}

// Basic date validation: YYYY-MM-DD
$re_date = '/^\d{4}-\d{2}-\d{2}$/';
if ($start_date && preg_match($re_date, $start_date)) {
    $where_count[] = 'evaluation_date >= ?';
    $where_data[]  = 'ev.evaluation_date >= ?';
    $types  .= 's';
    $params[] = $start_date;
}
if ($end_date && preg_match($re_date, $end_date)) {
    $where_count[] = 'evaluation_date <= ?';
    $where_data[]  = 'ev.evaluation_date <= ?';
    $types  .= 's';
    $params[] = $end_date;
}

$where_sql_count = count($where_count) ? ('WHERE ' . implode(' AND ', $where_count)) : '';
$where_sql_data  = count($where_data)  ? ('WHERE ' . implode(' AND ', $where_data))  : '';

// Count query for pagination
$count_sql = "SELECT COUNT(*) AS cnt FROM evaluations $where_sql_count";
$count_stmt = $conn->prepare($count_sql);
if ($count_stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}
if ($types) { $count_stmt->bind_param($types, ...$params); }
if (!$count_stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $count_stmt->error]);
    $count_stmt->close();
    exit;
}
$count_res = $count_stmt->get_result();
$total_rows = 0;
if ($count_res) {
    $row = $count_res->fetch_assoc();
    $total_rows = (int)($row['cnt'] ?? 0);
}
$count_stmt->close();

$offset = ($page - 1) * $per_page;

// Data query
$sql = "SELECT ev.id,
           ev.employee_id,
           emp.full_name AS employee_name,
           ev.branch_name,
           ev.branch_location,
           ev.total_sales,
           ev.company_quota,
           ev.sales_gap,
           ev.daily_sales,
           ev.vault_quota,
           ev.vault_collected,
           ev.quality_percentage,
           ev.total_units_sent,
           ev.return_rate_auto,
           ev.absentees,
           ev.lates,
           ev.vault_percent,
           ev.sales_percent,
           ev.attendance_percent,
           ev.return_rate_percent,
           ev.quality_percent,
           ev.total_points_percent,
           ev.evaluator_name,
           ev.evaluation_date,
           ev.comments,
           ev.created_at
    FROM evaluations ev
    LEFT JOIN employees emp ON TRIM(UPPER(emp.emp_id)) = TRIM(UPPER(ev.employee_id))
    $where_sql_data
    ORDER BY $order_by
    LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$types2 = $types . 'ii';
$params2 = $params;
$params2[] = $per_page;
$params2[] = $offset;
$stmt->bind_param($types2, ...$params2);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$res = $stmt->get_result();
$rows = [];
$sum_total_sales = 0.0;
$sum_sales_gap = 0.0;
$sum_sales_percent = 0.0;

while ($r = $res->fetch_assoc()) {
    // Ensure numeric types are numbers in JSON
    $r['total_sales'] = (float)$r['total_sales'];
    $r['company_quota'] = (float)$r['company_quota'];
    $r['sales_gap'] = (float)$r['sales_gap'];
    $r['daily_sales'] = (float)$r['daily_sales'];
    $r['vault_quota'] = (float)$r['vault_quota'];
    $r['vault_collected'] = (float)$r['vault_collected'];
    $r['quality_percentage'] = (float)$r['quality_percentage'];
    $r['total_units_sent'] = (int)$r['total_units_sent'];
    $r['return_rate_auto'] = (float)$r['return_rate_auto'];
    $r['absentees'] = (int)$r['absentees'];
    $r['lates'] = (int)$r['lates'];
    $r['vault_percent'] = (float)$r['vault_percent'];
    $r['sales_percent'] = (float)$r['sales_percent'];
    $r['attendance_percent'] = (float)$r['attendance_percent'];
    $r['return_rate_percent'] = (float)$r['return_rate_percent'];
    $r['quality_percent'] = (float)$r['quality_percent'];
    $r['total_points_percent'] = (float)$r['total_points_percent'];

    // Derived/calculated fallbacks (authoritative if stored values are missing)
    $sales_gap_calc = (float)$r['total_sales'] - (float)$r['company_quota'];
    $sales_percent_calc = ($r['company_quota'] > 0)
        ? (float)$r['total_sales'] / (float)$r['company_quota'] * 20.0
        : 0.0;
    $sales_percent_calc = max(0.0, min(20.0, $sales_percent_calc));

    // If stored values are zero/null, expose calculated versions
    $r['sales_gap_calc'] = round($sales_gap_calc, 2);
    $r['sales_percent_calc'] = round($sales_percent_calc, 2);

    $sum_total_sales += $r['total_sales'];
    $sum_sales_gap   += $r['sales_gap'];
    $sum_sales_percent += $r['sales_percent'];

    $rows[] = $r;
}
$stmt->close();

$out = [
    'success' => true,
    'data' => $rows,
    'summary' => [
        'count' => $total_rows,
        'page' => $page,
        'per_page' => $per_page,
        'pages' => ($per_page > 0) ? (int)ceil($total_rows / $per_page) : 1,
        'total_sales' => round($sum_total_sales, 2),
        'total_gap' => round($sum_sales_gap, 2),
        'avg_sales_percent' => count($rows) ? round($sum_sales_percent / count($rows), 2) : 0.0
    ]
];

echo json_encode($out);
exit;
