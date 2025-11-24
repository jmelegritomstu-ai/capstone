<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/employee_db.php';

date_default_timezone_set('Asia/Manila');

function post($k,$d=null){ return isset($_POST[$k])?trim($_POST[$k]):$d; }
function json_fail($m,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$m]); exit; }
function json_ok($data=[]){ echo json_encode(array_merge(['success'=>true],$data)); exit; }

if (!isset($conn) || !$conn) { json_fail('DB not connected',500); }

// Ensure employees table carries login columns (for upgraded installs)
@$conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS username VARCHAR(100) NOT NULL UNIQUE");
@$conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NOT NULL");
@$conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS role ENUM('auditor','account_executive') NOT NULL DEFAULT 'account_executive'");

$op = post('op');

// Registration is handled by backend/register_user.php writing to employees

if ($op === 'login') {
  $username = post('username');
  $password = post('password');
  if ($username==='' || $password==='') json_fail('Username and password are required');

  // Authenticate against employees table by username or emp_id
  $u = null;
  $stmt2 = $conn->prepare('SELECT id, username, emp_id AS employee_id, full_name AS fullname, position, branch_name AS branch, password_hash, role, status FROM employees WHERE username=? OR emp_id=? LIMIT 1');
  if ($stmt2) {
    $stmt2->bind_param('ss',$username,$username);
    if ($stmt2->execute()) {
      $res2 = $stmt2->get_result();
      $u = $res2? $res2->fetch_assoc():null;
    }
    $stmt2->close();
  }

  if (!$u) json_fail('Invalid credentials',401);
  if (!password_verify($password, $u['password_hash'])) json_fail('Invalid credentials',401);

  // Prevent login for terminated or suspended accounts
  $ust = isset($u['status']) ? strtolower(trim((string)$u['status'])) : '';
  if ($ust === 'terminated' || $ust === 'suspended') {
    json_fail('Account status "' . ($u['status'] ?? '') . '" contact your auditor immediately', 403);
  }

  // Set session (unified)
  $_SESSION['username'] = $u['username'];
  $_SESSION['role'] = $u['role'] ?? 'account_executive';
  $_SESSION['employee_id'] = $u['employee_id'] ?? null;
  $_SESSION['fullname'] = $u['fullname'] ?? null;
  $_SESSION['position'] = $u['position'] ?? null;
  $_SESSION['branch'] = $u['branch'] ?? null;
  $_SESSION['source_table'] = 'employees';
  $redirect = (($_SESSION['role']) === 'account_executive') ? '../user/attendance.php' : '../admin/employees.php';
  json_ok(['user'=>[ 'username'=>$u['username'], 'role'=>$_SESSION['role'], 'employee_id'=>$_SESSION['employee_id'], 'source'=>$_SESSION['source_table'] ], 'redirect'=>$redirect]);
}

if ($op === 'logout') {
  session_unset();
  session_destroy();
  json_ok(['message'=>'Logged out']);
}

if ($op === 'me') {
  if (!isset($_SESSION['username'])) json_fail('Not authenticated',401);
  json_ok(['username'=>$_SESSION['username'], 'role'=>$_SESSION['role'] ?? null, 'employee_id'=>$_SESSION['employee_id'] ?? null]);
}

json_fail('Invalid request',400);
