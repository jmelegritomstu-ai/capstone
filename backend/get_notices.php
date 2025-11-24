<?php
header('Content-Type: application/json');
require_once __DIR__ . '/session_user.php';
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }


$where = '';
$params = [];
$types = '';

// If AE, default to their own emp_id unless explicitly overridden (not allowed)
$role = $_SESSION['role'] ?? null;
if ($role === 'account_executive') {
  $emp = session_employee($conn);
  if ($emp && !empty($emp['emp_id'])) {
    $where .= ($where? ' AND ':' WHERE ') . 'emp_id = ?';
    $params[] = $emp['emp_id'];
    $types .= 's';
  } else {
    // If no emp_id found for AE, return empty for safety
    echo json_encode(['success'=>true,'data'=>[]]);
    exit;
  }
}

// Optional filters (auditor can filter by emp_id/date)
if (isset($_GET['emp_id']) && $_GET['emp_id'] !== '') {
  if ($role === 'auditor') {
    $where .= ($where? ' AND ':' WHERE ') . 'emp_id = ?';
    $params[] = $_GET['emp_id'];
    $types .= 's';
  }
}
if (isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])) {
  $where .= ($where? ' AND ':' WHERE ') . "DATE(created_at) >= ?";
  $params[] = $_GET['from'];
  $types .= 's';
}
if (isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])) {
  $where .= ($where? ' AND ':' WHERE ') . "DATE(created_at) <= ?";
  $params[] = $_GET['to'];
  $types .= 's';
}

$sql = "SELECT id, emp_id, employee_name, notice_type, category, description, issued_by, created_at AS date_issued FROM notices" . $where . " ORDER BY created_at DESC, id DESC LIMIT 500";
$stmt = $conn->prepare($sql);
if (!$stmt) { echo json_encode(['success'=>false,'message'=>'Prepare failed: '.$conn->error]); exit; }
if ($params) { $stmt->bind_param($types, ...$params); }
if (!$stmt->execute()) { echo json_encode(['success'=>false,'message'=>'Execute failed: '.$stmt->error]); $stmt->close(); exit; }
$res = $stmt->get_result();
$data = [];
while ($row = $res->fetch_assoc()) { $data[] = $row; }
$stmt->close();

echo json_encode(['success'=>true,'data'=>$data]);
