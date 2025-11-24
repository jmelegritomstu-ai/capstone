<?php
header('Content-Type: application/json');
require_once __DIR__ . '/session_user.php';
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'auditor') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }



$status = isset($_GET['status']) && in_array($_GET['status'], ['pending','approved','rejected']) ? $_GET['status'] : 'pending';

$sql = "SELECT r.id AS request_id, r.training_id, r.emp_id, r.requested_at, r.status AS request_status,
               t.title, t.description, t.scheduled_date, t.status AS training_status,
               e.full_name, e.branch_name
        FROM training_delete_requests r
        JOIN training_recommendations t ON t.id = r.training_id
        LEFT JOIN employees e ON e.emp_id = r.emp_id
        WHERE r.status = ?
        ORDER BY r.requested_at ASC, r.id ASC";
$stmt = $conn->prepare($sql);
if (!$stmt) { echo json_encode(['success'=>false,'message'=>'Prepare failed: '.$conn->error]); exit; }
$stmt->bind_param('s', $status);
if (!$stmt->execute()) { echo json_encode(['success'=>false,'message'=>'Execute failed: '.$stmt->error]); $stmt->close(); exit; }
$res = $stmt->get_result();
$data = [];
while ($row = $res->fetch_assoc()) { $data[] = $row; }
$stmt->close();

echo json_encode(['success'=>true,'data'=>$data]);
