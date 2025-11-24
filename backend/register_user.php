<?php
// Handles user registration + employee record creation in one go (with photo upload)
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/employee_db.php';

date_default_timezone_set('Asia/Manila');

function resp($ok, $extra=[]) {
  echo json_encode(array_merge(['success'=>$ok], $extra));
  exit;
}
function val($k,$d=null){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }

if (!isset($conn) || !$conn) resp(false, ['message'=>'DB not connected']);

// Ensure users table
// Ensure unified employees table has auth columns (if migrating from older version)
function ensureColumn($conn,$table,$col,$ddl){
  $res=$conn->query("SHOW COLUMNS FROM `$table` LIKE '".$conn->real_escape_string($col)."'");
  if(!$res || $res->num_rows===0){ $conn->query("ALTER TABLE `$table` ADD COLUMN $ddl"); }
  if($res) $res->close();
}
// Add new auth columns when missing
foreach([
  ['username','username VARCHAR(100) NOT NULL UNIQUE AFTER id'],
  ['password_hash','password_hash VARCHAR(255) NOT NULL AFTER username'],
  ['role',"role ENUM('auditor','account_executive') NOT NULL DEFAULT 'account_executive' AFTER password_hash"]
] as $def){ ensureColumn($conn,'employees',$def[0],$def[1]); }
// Legacy migration: move existing users rows into employees if users table still exists
$hasUsers = $conn->query("SHOW TABLES LIKE 'users'");
if($hasUsers && $hasUsers->num_rows>0){
  $migr = $conn->query("SELECT username,password_hash,role,employee_id,fullname,position,branch FROM users");
  if($migr){
    while($row=$migr->fetch_assoc()){
      // If employee already exists with same emp_id or username skip
      $u = $conn->prepare("SELECT id FROM employees WHERE username=? OR emp_id=? LIMIT 1");
      if($u){
        $u->bind_param('ss',$row['username'],$row['employee_id']);
        $u->execute(); $r=$u->get_result(); $exists=$r&&$r->num_rows>0; $u->close();
        if(!$exists){
          $empId = $row['employee_id'] ?: ('MIG'.time().rand(100,999));
          $ins = $conn->prepare("INSERT INTO employees (username,password_hash,role,emp_id,full_name,position,branch_name) VALUES (?,?,?,?,?,?,?)");
          if($ins){
            $full = $row['fullname'] ?: $row['username'];
            $bn = $row['branch'];
            $ins->bind_param('sssssss',$row['username'],$row['password_hash'],$row['role'],$empId,$full,$row['position'],$bn);
            $ins->execute();
            $ins->close();
          }
        }
      }
    }
    $migr->close();
  }
  // Optionally drop users table after migration (commented out for safety)
  // $conn->query('DROP TABLE users');
}

// Collect fields
$username = val('username');
$password = val('password');
$role     = val('role','account_executive');
$emp_id   = val('emp_id');
$full_name= val('full_name');
$position = val('position');
$contact_number = val('contact_number');
$email    = val('email');
$date_hired = val('date_hired');
$branch_city = val('branch_city');
$branch_name = val('branch_name');
$branch_location = val('branch_location');
$gender = val('gender');
$birthday = val('birthday');
$contract_type = val('contract_type');
$shift_schedule = val('shift_schedule');
$address = val('address');
$auditor_name = ($role === 'account_executive') ? val('auditor_name') : null; // only capture if account executive
$status   = val('status','Active');

if ($username===''||$password==='') resp(false,['message'=>'Username and password are required']);
if (!in_array($role,['auditor','account_executive'],true)) resp(false,['message'=>'Invalid role']);
if ($emp_id==='') resp(false,['message'=>'Employee ID is required']);
if ($full_name==='') resp(false,['message'=>'Full name is required']);
if ($position==='') resp(false,['message'=>'Position is required']);
if ($contact_number==='') resp(false,['message'=>'Contact number is required']);

// Validate uniqueness (username in employees)
$st = $conn->prepare('SELECT id FROM employees WHERE username=? LIMIT 1');
if(!$st) resp(false,['message'=>'Prepare failed: '.$conn->error]);
$st->bind_param('s',$username);
$st->execute();
$r = $st->get_result();
if ($r && $r->num_rows>0) { $st->close(); resp(false,['message'=>'Username already exists']); }
$st->close();

$st2 = $conn->prepare('SELECT id FROM employees WHERE TRIM(UPPER(emp_id)) = TRIM(UPPER(?)) LIMIT 1');
if(!$st2) resp(false,['message'=>'Prepare failed: '.$conn->error]);
$st2->bind_param('s',$emp_id);
$st2->execute();
$r2 = $st2->get_result();
if ($r2 && $r2->num_rows>0) { $st2->close(); resp(false,['message'=>'Employee ID already exists']); }
$st2->close();

// Handle photo upload file (store filename in uploads/)
$profile_img = null;
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) { @mkdir($uploadDir,0777,true); }
if (!empty($_FILES['profile_img']['name'])) {
  $base = basename($_FILES['profile_img']['name']);
  $safe = preg_replace('/[^A-Za-z0-9._-]/','_', $base);
  $name = time().'_'.$safe;
  $target = $uploadDir.$name;
  if (!move_uploaded_file($_FILES['profile_img']['tmp_name'], $target)) {
    resp(false,['message'=>'Image upload failed']);
  }
  $profile_img = $name;
}

// Ensure extended columns exist on employees table (light migration)
function ensureEmpColumn($conn,$col){
  $chk = $conn->query("SHOW COLUMNS FROM employees LIKE '".$conn->real_escape_string($col)."'");
  if($chk && $chk->num_rows>0){ return; }
  $defs = [
    'gender' => 'ALTER TABLE employees ADD COLUMN gender VARCHAR(20) NULL',
    'birthday' => 'ALTER TABLE employees ADD COLUMN birthday DATE NULL',
    'contract_type' => 'ALTER TABLE employees ADD COLUMN contract_type VARCHAR(50) NULL',
    'shift_schedule' => 'ALTER TABLE employees ADD COLUMN shift_schedule VARCHAR(100) NULL',
    'address' => 'ALTER TABLE employees ADD COLUMN address VARCHAR(200) NULL',
    'auditor_name' => 'ALTER TABLE employees ADD COLUMN auditor_name VARCHAR(150) NULL',
    'branch_city' => 'ALTER TABLE employees ADD COLUMN branch_city VARCHAR(80) NULL'
  ];
  if(isset($defs[$col])){ $conn->query($defs[$col]); }
}
foreach(['gender','birthday','contract_type','shift_schedule','address','auditor_name','branch_city'] as $c){ ensureEmpColumn($conn,$c); }
// Ensure dayoff_weekday column (1=Mon..6=Sun)
ensureColumn($conn,'employees','dayoff_weekday','dayoff_weekday TINYINT NULL COMMENT "1=Mon .. 6=Sun preferred rest day" AFTER shift_schedule');

// Insert employee row with auth fields
$insEmp = $conn->prepare('INSERT INTO employees (username, password_hash, role, emp_id, full_name, position, contact_number, email, date_hired, branch_city, branch_name, branch_location, status, profile_img, gender, birthday, contract_type, shift_schedule, address, auditor_name, dayoff_weekday) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
if(!$insEmp) resp(false,['message'=>'Prepare failed: '.$conn->error]);
$hash = password_hash($password, PASSWORD_DEFAULT);
$dayoff_weekday_raw = val('dayoff_weekday');
// Validate: allow only 1..6 (Mon..Sun)
$dayoff_weekday = ($dayoff_weekday_raw !== null && $dayoff_weekday_raw !== '' && preg_match('/^[1-6]$/',$dayoff_weekday_raw)) ? (int)$dayoff_weekday_raw : null;
$insEmp->bind_param('sssssssssssssssssssss',$username,$hash,$role,$emp_id,$full_name,$position,$contact_number,$email,$date_hired,$branch_city,$branch_name,$branch_location,$status,$profile_img,$gender,$birthday,$contract_type,$shift_schedule,$address,$auditor_name,$dayoff_weekday);
if(!$insEmp->execute()) { $msg=$insEmp->error?:'Insert employee failed'; $insEmp->close(); resp(false,['message'=>$msg]); }
$insEmp->close();

// Removed auto day-off attendance insertion: attendance_logs should only record actual Time In/Out actions.

resp(true,['message'=>'Registration successful','redirect'=>'../user/login.php']);
