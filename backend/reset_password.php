<?php
header('Content-Type: application/json');

require_once __DIR__ . '/employee_db.php'; // provides $conn

function json_out($ok, $message = '', $extra = []){
  echo json_encode(array_merge(['success'=>$ok, 'message'=>$message], $extra));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(false, 'Invalid method');
}

$op = $_POST['op'] ?? '';

if ($op === 'lookup') {
  $username = trim($_POST['username'] ?? '');
  if ($username === '') { json_out(false, 'Username required'); }

  $stmt = $conn->prepare("SELECT username, emp_id, full_name FROM employees WHERE username = ? OR emp_id = ? LIMIT 1");
  $stmt->bind_param('ss', $username, $username);
  if (!$stmt->execute()) { json_out(false, 'Database error'); }
  $res = $stmt->get_result();
  if ($res->num_rows === 0) { json_out(false, 'User not found'); }
  $row = $res->fetch_assoc();
  json_out(true, 'OK', ['user'=> $row]);
}

if ($op === 'reset') {
  $username = trim($_POST['username'] ?? '');
  $new = $_POST['new_password'] ?? '';
  if ($username === '' || $new === '') { json_out(false, 'Missing fields'); }
  if (strlen($new) < 6) { json_out(false, 'Password too short'); }

  // Resolve username strictly to username (not emp_id) to avoid ambiguity on update
  $stmt = $conn->prepare("SELECT username FROM employees WHERE username = ? LIMIT 1");
  $stmt->bind_param('s', $username);
  if (!$stmt->execute()) { json_out(false, 'Database error'); }
  $res = $stmt->get_result();
  if ($res->num_rows === 0) { json_out(false, 'User not found'); }
  $row = $res->fetch_assoc();
  $uname = $row['username'];

  $hash = password_hash($new, PASSWORD_DEFAULT);
  $upd = $conn->prepare("UPDATE employees SET password_hash = ? WHERE username = ?");
  $upd->bind_param('ss', $hash, $uname);
  if (!$upd->execute()) { json_out(false, 'Failed to update'); }

  json_out(true, 'Password updated');
}

json_out(false, 'Unknown operation');
?>
