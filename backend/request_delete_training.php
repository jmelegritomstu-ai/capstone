<?php
header('Content-Type: application/json');
require_once __DIR__ . '/session_user.php';
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }
$role = $_SESSION['role'] ?? null;
$emp = session_employee($conn);
if ($role !== 'account_executive') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Only AEs can request delete']); exit; }
$emp_id = $emp['emp_id'] ?? null;
if (!$emp_id) { echo json_encode(['success'=>false,'message'=>'No emp_id in session']); exit; }
$training_id = isset($_POST['training_id']) ? (int)$_POST['training_id'] : 0;
if ($training_id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid training_id']); exit; }

// Verify training ownership
$stmt = $conn->prepare('SELECT emp_id, status FROM training_recommendations WHERE id = ? LIMIT 1');
$stmt->bind_param('i',$training_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res? $res->fetch_assoc():null;
$stmt->close();
if (!$row || $row['emp_id'] !== $emp_id) { echo json_encode(['success'=>false,'message'=>'Training not found for employee']); exit; }
if ($row['status'] === 'deleted') { echo json_encode(['success'=>false,'message'=>'Training already deleted']); exit; }

// Avoid duplicate pending request for same training
$dup = $conn->prepare("SELECT id FROM training_delete_requests WHERE training_id = ? AND emp_id = ? AND status = 'pending' LIMIT 1");
$dup->bind_param('is', $training_id, $emp_id);
$dup->execute();
$dupRes = $dup->get_result();
$already = $dupRes && $dupRes->fetch_assoc();
$dup->close();

if (!$already) {
  // Mark training pending_delete
  $upd = $conn->prepare("UPDATE training_recommendations SET status = 'pending_delete' WHERE id = ?");
  $upd->bind_param('i',$training_id);
  $upd->execute();
  $upd->close();

  // Insert request
  $ins = $conn->prepare('INSERT INTO training_delete_requests (training_id, emp_id) VALUES (?,?)');
  $ins->bind_param('is',$training_id,$emp_id);
  $ins->execute();
  $ins->close();
}

echo json_encode(['success'=>true,'training_id'=>$training_id,'duplicate'=>(bool)$already]);
