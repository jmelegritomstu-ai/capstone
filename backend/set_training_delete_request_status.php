<?php
header('Content-Type: application/json');
require_once __DIR__ . '/session_user.php';
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'auditor') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
if ($request_id <= 0 || !in_array($action, ['approve','reject'], true)) { echo json_encode(['success'=>false,'message'=>'Invalid parameters']); exit; }


// Load request
$stmt = $conn->prepare('SELECT id, training_id, emp_id, status FROM training_delete_requests WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $request_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res? $res->fetch_assoc():null;
$stmt->close();
if (!$row || $row['status'] !== 'pending') { echo json_encode(['success'=>false,'message'=>'Request not found or already processed']); exit; }

$training_id = (int)$row['training_id'];

if ($action === 'approve') {
  // Mark training deleted and request approved
  $u1 = $conn->prepare("UPDATE training_recommendations SET status = 'deleted' WHERE id = ?");
  $u1->bind_param('i', $training_id);
  $u1->execute();
  $u1->close();

  $u2 = $conn->prepare("UPDATE training_delete_requests SET status = 'approved' WHERE id = ?");
  $u2->bind_param('i', $request_id);
  $u2->execute();
  $u2->close();
} else { // reject
  $u1 = $conn->prepare("UPDATE training_recommendations SET status = 'active' WHERE id = ?");
  $u1->bind_param('i', $training_id);
  $u1->execute();
  $u1->close();

  $u2 = $conn->prepare("UPDATE training_delete_requests SET status = 'rejected' WHERE id = ?");
  $u2->bind_param('i', $request_id);
  $u2->execute();
  $u2->close();
}

echo json_encode(['success'=>true]);
