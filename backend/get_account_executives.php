<?php
header('Content-Type: application/json');
require_once __DIR__ . '/employee_db.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not initialized']);
    exit;
}

$sql = "SELECT id, emp_id, full_name, branch_name, branch_location FROM employees WHERE role = 'account_executive' ORDER BY full_name ASC";
$res = $conn->query($sql);
if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
    exit;
}

$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'id' => (int)$r['id'],
        'emp_id' => $r['emp_id'],
        'full_name' => $r['full_name'],
        'branch_name' => $r['branch_name'],
        'branch_location' => $r['branch_location'],
    ];
}

echo json_encode(['success' => true, 'data' => $rows]);
exit;
?>