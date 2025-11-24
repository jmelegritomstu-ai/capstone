<?php
header('Content-Type: application/json');

require_once __DIR__ . '/employee_db.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not initialized']);
    exit;
}

function qs($key, $default = null) {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

$branch_location = qs('branch_location'); // optional filter
$branch_name     = qs('branch_name');     // optional filter
$branch_names_csv = qs('branch_names');   // optional MULTI filter (comma-separated branch names)
$branch_locations_csv = qs('branch_locations'); // optional MULTI filter for locations
$start_date      = qs('start_date');      // optional YYYY-MM-DD
$end_date        = qs('end_date');        // optional YYYY-MM-DD
$page            = max(1, (int) qs('page', 1));
$per_page        = max(1, min(500, (int) qs('per_page', 100)));
$debug           = qs('debug');

// If dates are absent, default to current month
if (!$start_date || !$end_date) {
    $now = new DateTime('now');
    $first = (clone $now)->modify('first day of this month')->format('Y-m-d');
    $last  = (clone $now)->modify('last day of this month')->format('Y-m-d');
    if (!$start_date) $start_date = $first;
    if (!$end_date)   $end_date   = $last;
}

// Build WHERE
$where = [ 'ev.evaluation_date >= ?', 'ev.evaluation_date <= ?' ];
$types = 'ss';
$params = [ $start_date, $end_date ];

if ($branch_location !== null && $branch_location !== '') {
    $where[] = 'COALESCE(emp.branch_location, ev.branch_location) = ?';
    $types  .= 's';
    $params[] = $branch_location;
}
if ($branch_name !== null && $branch_name !== '') {
    $where[] = 'COALESCE(emp.branch_name, ev.branch_name) = ?';
    $types  .= 's';
    $params[] = $branch_name;
}
// If a comma-separated list of branch names is provided, apply an IN (...) filter
if ($branch_names_csv !== null && trim($branch_names_csv) !== '') {
    $branch_list = array_filter(array_map('trim', explode(',', $branch_names_csv)), function($v){ return $v !== ''; });
    if (count($branch_list)) {
        // Add placeholders
        $inPlace = implode(',', array_fill(0, count($branch_list), '?'));
        $where[] = 'COALESCE(emp.branch_name, ev.branch_name) IN (' . $inPlace . ')';
        $types .= str_repeat('s', count($branch_list));
        foreach ($branch_list as $bn) { $params[] = $bn; }
    }
}
// Support multiple branch locations via IN (...)
if ($branch_locations_csv !== null && trim($branch_locations_csv) !== '') {
    $loc_list = array_filter(array_map('trim', explode(',', $branch_locations_csv)), function($v){ return $v !== ''; });
    if (count($loc_list)) {
        $inPlace = implode(',', array_fill(0, count($loc_list), '?'));
        $where[] = 'COALESCE(emp.branch_location, ev.branch_location) IN (' . $inPlace . ')';
        $types .= str_repeat('s', count($loc_list));
        foreach ($loc_list as $bl) { $params[] = $bl; }
    }
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

// Count unique employees for pagination
$count_sql = "SELECT COUNT(DISTINCT ev.employee_id) as cnt
              FROM evaluations ev
              LEFT JOIN employees emp ON TRIM(UPPER(emp.emp_id)) = TRIM(UPPER(ev.employee_id))
              $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($types) { $count_stmt->bind_param($types, ...$params); }
if (!$count_stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $count_stmt->error]);
    $count_stmt->close();
    exit;
}
$count_res = $count_stmt->get_result();
$total_rows = 0;
if ($count_res) { $row = $count_res->fetch_assoc(); $total_rows = (int)($row['cnt'] ?? 0); }
$count_stmt->close();

$offset = ($page - 1) * $per_page;

// Monthly aggregation per employee
$sql = "SELECT 
            ev.employee_id,
            COALESCE(emp.full_name, '') AS employee_name,
            COALESCE(emp.branch_name, ev.branch_name) AS branch_name,
            COALESCE(emp.branch_location, ev.branch_location) AS branch_location,
            SUM(ev.total_sales) AS total_sales,
            SUM(ev.company_quota) AS company_quota,
            SUM(ev.vault_collected) AS vault_collected,
            SUM(ev.vault_quota) AS vault_quota,
            MAX(ev.evaluation_date) AS last_eval
        FROM evaluations ev
        LEFT JOIN employees emp ON TRIM(UPPER(emp.emp_id)) = TRIM(UPPER(ev.employee_id))
        $where_sql
        GROUP BY ev.employee_id
        ORDER BY total_sales DESC, ev.employee_id ASC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$types2 = $types . 'ii';
$params2 = $params; $params2[] = $per_page; $params2[] = $offset;
$stmt->bind_param($types2, ...$params2);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$res = $stmt->get_result();
$rows = [];
$sum_sales = 0.0; $sum_quota = 0.0; $sum_vault = 0.0; $sum_vquota = 0.0;
while ($r = $res->fetch_assoc()) {
    $r['employee_name'] = $r['employee_name'];
    $r['total_sales'] = (float)$r['total_sales'];
    $r['company_quota'] = (float)$r['company_quota'];
    $r['vault_collected'] = (float)$r['vault_collected'];
    $r['vault_quota'] = (float)$r['vault_quota'];
    $r['sales_gap_calc'] = round($r['total_sales'] - $r['company_quota'], 2);
    $r['sales_percent_calc'] = ($r['company_quota'] > 0) ? round(min(20.0, max(0.0, ($r['total_sales']/$r['company_quota'])*20.0)), 2) : 0.0;
    $sum_sales += $r['total_sales'];
    $sum_quota += $r['company_quota'];
    $sum_vault += $r['vault_collected'];
    $sum_vquota += $r['vault_quota'];
    $rows[] = $r;
}
$stmt->close();

$debugInfo = [];
if ($debug === '1') {
    // Collect raw rows (non-aggregated) for first 15 matches to help diagnose empty aggregates
    $raw_sql = "SELECT ev.id, ev.employee_id, ev.evaluation_date, ev.total_sales, ev.company_quota, ev.vault_collected, ev.vault_quota, ev.branch_name, ev.branch_location
                FROM evaluations ev
                LEFT JOIN employees emp ON TRIM(UPPER(emp.emp_id)) = TRIM(UPPER(ev.employee_id))
                $where_sql
                ORDER BY ev.evaluation_date DESC, ev.id DESC
                LIMIT 15";
    $stmtRaw = $conn->prepare($raw_sql);
    if ($stmtRaw) {
        $stmtRaw->bind_param($types, ...$params);
        if ($stmtRaw->execute()) {
            $resRaw = $stmtRaw->get_result();
            $rawRows = [];
            while ($rr = $resRaw->fetch_assoc()) { $rawRows[] = $rr; }
            $debugInfo['raw_rows_preview'] = $rawRows;
        } else {
            $debugInfo['raw_error'] = $stmtRaw->error;
        }
        $stmtRaw->close();
    } else {
        $debugInfo['raw_prepare_error'] = $conn->error;
    }
    $debugInfo['where_clauses'] = $where;
    $debugInfo['bound_params'] = $params;
    $debugInfo['bound_types'] = $types;
    $debugInfo['aggregation_limit'] = $per_page;
    $debugInfo['aggregation_offset'] = $offset;
}

$out = [
    'success' => true,
    'data' => $rows,
    'summary' => [
        'count' => $total_rows,
        'page' => $page,
        'per_page' => $per_page,
        'pages' => ($per_page > 0) ? (int)ceil($total_rows / $per_page) : 1,
        'total_sales' => round($sum_sales, 2),
        'total_quota' => round($sum_quota, 2),
        'total_vault' => round($sum_vault, 2),
        'total_vault_quota' => round($sum_vquota, 2)
    ],
    'debug' => $debugInfo
];

echo json_encode($out);
exit;
