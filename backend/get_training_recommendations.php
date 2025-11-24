<?php
header('Content-Type: application/json');
require_once __DIR__ . '/session_user.php';
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }

// Scope: AEs see only their trainings; auditors can filter by emp_id; deleted hidden by default
$role = $_SESSION['role'] ?? null;
$emp = session_employee($conn);
$emp_id = $emp['emp_id'] ?? null;

$where = " WHERE status <> 'deleted' ";
$params = [];
$types = '';

// Optional status filter (exclude deleted regardless)
if (isset($_GET['status']) && in_array($_GET['status'], ['active','pending_delete'], true)) {
        $where .= " AND status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
}

// Role scoping
if ($role === 'account_executive') {
        if (!$emp_id) { echo json_encode(['success'=>true,'data'=>[]]); exit; }
        $where .= ' AND emp_id = ?';
        $params[] = $emp_id; $types .= 's';
} else if ($role === 'auditor') {
        if (!empty($_GET['emp_id'])) { $where .= ' AND emp_id = ?'; $params[] = $_GET['emp_id']; $types .= 's'; }
}

$sql = "SELECT id, emp_id, title, description, scheduled_date, status, source, created_at
                                FROM training_recommendations" . $where . " ORDER BY COALESCE(scheduled_date, DATE(created_at)) ASC, id ASC LIMIT 500";
$stmt = $conn->prepare($sql);
if (!$stmt) { echo json_encode(['success'=>false,'message'=>'Prepare failed: '.$conn->error]); exit; }
if ($params) { $stmt->bind_param($types, ...$params); }
if (!$stmt->execute()) { echo json_encode(['success'=>false,'message'=>'Execute failed: '.$stmt->error]); $stmt->close(); exit; }
$res = $stmt->get_result();
$data = [];
while ($row = $res->fetch_assoc()) { $data[] = $row; }
$stmt->close();

echo json_encode(['success'=>true,'data'=>$data]);
