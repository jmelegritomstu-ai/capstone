<?php
header('Content-Type: application/json');
require_once __DIR__ . '/session_user.php';
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'auditor') {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Forbidden']);
  exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid id']); exit; }

$stmt = $conn->prepare('DELETE FROM notices WHERE id = ?');
if (!$stmt) { echo json_encode(['success'=>false,'message'=>'Prepare failed: '.$conn->error]); exit; }
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
if (!$ok) { echo json_encode(['success'=>false,'message'=>'Delete failed: '.$stmt->error]); $stmt->close(); exit; }
$stmt->close();

echo json_encode(['success'=>true]);
