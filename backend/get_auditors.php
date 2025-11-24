<?php
header('Content-Type: application/json');
require_once __DIR__ . '/employee_db.php';
if (!isset($conn) || !$conn) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }

// Optional filters: branch_name, branch_city
$branch_name = isset($_GET['branch_name']) ? trim($_GET['branch_name']) : null;
$branch_city = isset($_GET['branch_city']) ? trim($_GET['branch_city']) : null;

$rows = [];
if ($branch_name || $branch_city) {
    // Prepare flexible SQL to match by branch_name OR branch_city when provided
    $conds = [];
    $types = '';
    $params = [];
    if ($branch_name) { $conds[] = '`branch_name` = ?'; $types .= 's'; $params[] = $branch_name; }
    if ($branch_city) { $conds[] = '`branch_city` = ?'; $types .= 's'; $params[] = $branch_city; }
    $where = implode(' OR ', $conds);
    $sql = "SELECT id, emp_id, full_name, branch_name, branch_city FROM employees WHERE role = 'auditor' AND ({$where}) ORDER BY full_name ASC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $rows[] = [ 'id' => (int)$r['id'], 'emp_id' => $r['emp_id'], 'full_name' => $r['full_name'], 'branch_name' => $r['branch_name'], 'branch_city' => $r['branch_city'] ];
            }
        }
        $stmt->close();
    } else {
        echo json_encode(['success'=>false,'message'=>'Prepare failed: '.$conn->error]); exit;
    }
} else {
    $sql = "SELECT id, emp_id, full_name, branch_name, branch_city FROM employees WHERE role = 'auditor' ORDER BY full_name ASC";
    $res = $conn->query($sql);
    if (!$res) { echo json_encode(['success'=>false,'message'=>'Query failed: '.$conn->error]); exit; }
    while ($r = $res->fetch_assoc()) {
        $rows[] = [ 'id' => (int)$r['id'], 'emp_id' => $r['emp_id'], 'full_name' => $r['full_name'], 'branch_name' => $r['branch_name'], 'branch_city' => $r['branch_city'] ];
    }
}

echo json_encode(['success'=>true,'data'=>$rows]);
exit;
