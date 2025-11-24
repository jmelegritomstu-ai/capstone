<?php
// Schema repair utility for MZE Cellular
// Visit in browser: /evalsystem/backend/repair_schema.php
header('Content-Type: application/json');
require_once __DIR__ . '/employee_db.php';
$out = [ 'success'=>false, 'actions'=>[] ];
if (!isset($conn) || !$conn) { echo json_encode(['success'=>false,'message'=>'DB not initialized']); exit; }

function action(&$out,$msg){ $out['actions'][] = $msg; }

// Helper: ensure column exists with given definition
function ensure_column(mysqli $conn, &$out, $table, $column, $definition){
  $exists = false;
  $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
  if ($res && $res->num_rows>0) { $exists=true; }
  if (!$exists) {
    $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
    if ($conn->query($sql)) { action($out, "Added $table.$column"); }
    else { action($out, "Failed adding $table.$column: ".$conn->error); }
  }
}

// Helper: ensure table exists
function ensure_table(mysqli $conn, &$out, $sqlCreate, $name){
  if ($conn->query($sqlCreate)) { action($out, "Ensured table $name"); }
  else { action($out, "Failed ensuring table $name: ".$conn->error); }
}

// employees table (base)
ensure_table($conn,$out,
  "CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('auditor','account_executive') NOT NULL DEFAULT 'account_executive',
    emp_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    position VARCHAR(100) NULL,
    contact_number VARCHAR(40) NULL,
    email VARCHAR(150) NULL,
    date_hired DATE NULL,
    branch_city VARCHAR(80) NULL,
    branch_name VARCHAR(100) NULL,
    branch_location VARCHAR(150) NULL,
    status VARCHAR(40) NULL,
    profile_img VARCHAR(200) NULL,
    gender VARCHAR(20) NULL,
    birthday DATE NULL,
    contract_type VARCHAR(50) NULL,
    shift_schedule VARCHAR(100) NULL,
    address VARCHAR(200) NULL,
    auditor_name VARCHAR(150) NULL,
    rfid_code VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'employees');

// Columns on employees (idempotent)
ensure_column($conn,$out,'employees','username',"VARCHAR(100) NOT NULL UNIQUE");
ensure_column($conn,$out,'employees','password_hash',"VARCHAR(255) NOT NULL");
ensure_column($conn,$out,'employees','role',"ENUM('auditor','account_executive') NOT NULL DEFAULT 'account_executive'");
ensure_column($conn,$out,'employees','emp_id',"VARCHAR(50) NOT NULL");
ensure_column($conn,$out,'employees','full_name',"VARCHAR(150) NOT NULL");
ensure_column($conn,$out,'employees','position',"VARCHAR(100) NULL");
ensure_column($conn,$out,'employees','contact_number',"VARCHAR(40) NULL");
ensure_column($conn,$out,'employees','email',"VARCHAR(150) NULL");
ensure_column($conn,$out,'employees','date_hired',"DATE NULL");
ensure_column($conn,$out,'employees','branch_city',"VARCHAR(80) NULL");
ensure_column($conn,$out,'employees','branch_name',"VARCHAR(100) NULL");
ensure_column($conn,$out,'employees','branch_location',"VARCHAR(150) NULL");
ensure_column($conn,$out,'employees','status',"VARCHAR(40) NULL");
ensure_column($conn,$out,'employees','profile_img',"VARCHAR(200) NULL");
ensure_column($conn,$out,'employees','gender',"VARCHAR(20) NULL");
ensure_column($conn,$out,'employees','birthday',"DATE NULL");
ensure_column($conn,$out,'employees','contract_type',"VARCHAR(50) NULL");
ensure_column($conn,$out,'employees','shift_schedule',"VARCHAR(100) NULL");
ensure_column($conn,$out,'employees','address',"VARCHAR(200) NULL");
ensure_column($conn,$out,'employees','auditor_name',"VARCHAR(150) NULL");
ensure_column($conn,$out,'employees','rfid_code',"VARCHAR(64) NULL");
// Preferred day off weekday (1=Mon .. 6=Sat); used to derive monthly dayoff_schedule
ensure_column($conn,$out,'employees','dayoff_weekday',"TINYINT NULL");

// attendance_logs
ensure_table($conn,$out,
  "CREATE TABLE IF NOT EXISTS attendance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id VARCHAR(50) NOT NULL,
    full_name VARCHAR(150) NULL,
    branch_name VARCHAR(100) NULL,
    branch_location VARCHAR(150) NULL,
    position VARCHAR(100) NULL,
    log_date DATE NOT NULL,
    check_in DATETIME NULL,
    check_out DATETIME NULL,
    is_dayoff TINYINT(1) NOT NULL DEFAULT 0,
    is_onleave TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_emp_date (emp_id, log_date)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'attendance_logs');

// Ensure attendance_logs.is_dayoff exists for existing installs
ensure_column($conn,$out,'attendance_logs','is_dayoff','TINYINT(1) NOT NULL DEFAULT 0');
// Ensure attendance_logs.is_onleave exists for existing installs
ensure_column($conn,$out,'attendance_logs','is_onleave','TINYINT(1) NOT NULL DEFAULT 0');

// evaluations
ensure_table($conn,$out,
  "CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) NOT NULL,
    branch_name VARCHAR(100) NULL,
    branch_location VARCHAR(150) NULL,
    total_sales DECIMAL(12,2) DEFAULT 0,
    company_quota DECIMAL(12,2) DEFAULT 0,
    sales_gap DECIMAL(12,2) DEFAULT 0,
    daily_sales DECIMAL(12,2) DEFAULT 0,
    vault_quota DECIMAL(12,2) DEFAULT 0,
    vault_collected DECIMAL(12,2) DEFAULT 0,
    quality_percentage DECIMAL(5,2) DEFAULT 0,
    evaluator_notes VARCHAR(255) NULL,
    total_units_sent INT DEFAULT 0,
    return_rate_auto DECIMAL(6,2) DEFAULT 0,
    absentees INT DEFAULT 0,
    lates INT DEFAULT 0,
    vault_percent DECIMAL(6,2) DEFAULT 0,
    sales_percent DECIMAL(6,2) DEFAULT 0,
    attendance_percent DECIMAL(6,2) DEFAULT 0,
    return_rate_percent DECIMAL(6,2) DEFAULT 0,
    quality_percent DECIMAL(6,2) DEFAULT 0,
    total_points_percent DECIMAL(6,2) DEFAULT 0,
    evaluator_name VARCHAR(120) NULL,
    evaluation_date DATE NULL,
    comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'evaluations');

// notices
ensure_table($conn,$out,
  "CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id VARCHAR(50) NOT NULL,
    employee_name VARCHAR(150) NULL,
    notice_type VARCHAR(100) NOT NULL,
    category ENUM('positive','warning','announcement') DEFAULT 'warning',
    description TEXT NOT NULL,
    issued_by VARCHAR(120) NULL,
    date_issued DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notice_emp_date (emp_id, date_issued)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'notices');

// trainings
ensure_table($conn,$out,
  "CREATE TABLE IF NOT EXISTS training_recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    scheduled_date DATE NULL,
    status ENUM('active','pending_delete','deleted') DEFAULT 'active',
    source VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_emp_title_date (emp_id, title, scheduled_date),
    INDEX idx_emp_status (emp_id, status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'training_recommendations');

ensure_table($conn,$out,
  "CREATE TABLE IF NOT EXISTS training_delete_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    emp_id VARCHAR(50) NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    INDEX idx_training (training_id),
    INDEX idx_emp (emp_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'training_delete_requests');

// branch_logs (optional but used by dashboard)
ensure_table($conn,$out,
  "CREATE TABLE IF NOT EXISTS branch_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    branch_name VARCHAR(100) NULL,
    log_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_branch_user_date (username, log_date)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'branch_logs');

// dayoff_schedule: store two monthly scheduled day-offs per employee
ensure_table($conn,$out,
  "CREATE TABLE IF NOT EXISTS dayoff_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id VARCHAR(50) NOT NULL,
    year SMALLINT NOT NULL,
    month TINYINT NOT NULL,
    preferred_weekday TINYINT NULL,
    dayoff_first_half DATE NULL,
    dayoff_second_half DATE NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_emp_month (emp_id, year, month),
    INDEX idx_emp (emp_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'dayoff_schedule');

$out['success'] = true;
echo json_encode($out);
