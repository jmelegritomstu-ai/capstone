<?php
header('Content-Type: application/json');
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/employee_db.php';
// Holiday helper (returns dates; prefers DB-backed table if present)
require_once __DIR__ . '/holidays.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not initialized']);
    exit;
}

function post($key, $default = null) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// Ensure attendance_logs table exists
$createSql = "CREATE TABLE IF NOT EXISTS attendance_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (!$conn->query($createSql)) {
    echo json_encode(['success' => false, 'message' => 'Failed to ensure attendance_logs table: ' . $conn->error]);
    exit;
}

// Utility: check if employees.rfid_code column exists (once per request)
function employees_has_rfid($conn) {
    static $has = null;
    if ($has !== null) return $has;
    $res = $conn->query("SHOW COLUMNS FROM employees LIKE 'rfid_code'");
    $has = $res && $res->num_rows > 0;
    if ($res) { $res->free(); }
    return $has;
}

// Detect if attendance_logs has is_onleave column (for backward compatibility before schema upgrade)
function attendance_has_onleave($conn){
    static $has = null; if ($has !== null) return $has;
    $res = $conn->query("SHOW COLUMNS FROM attendance_logs LIKE 'is_onleave'");
    $has = $res && $res->num_rows > 0; if ($res) $res->free(); return $has;
}

// Resolve employee by rfid or emp_id
function find_employee($conn, $code) {
    $code = trim($code);
    if ($code === '') return null;
    $hasRfid = employees_has_rfid($conn);
    if ($hasRfid) {
    $sql = "SELECT id, emp_id, full_name, branch_name, branch_location, position, dayoff_weekday
                FROM employees WHERE emp_id = ? OR rfid_code = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('ss', $code, $code);
    } else {
    $sql = "SELECT id, emp_id, full_name, branch_name, branch_location, position, dayoff_weekday
                FROM employees WHERE emp_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('s', $code);
    }
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

// Determine suggested action based on today's logs
function suggest_action($conn, $emp_id) {
    $today = date('Y-m-d');
    $sql = "SELECT id, check_in, check_out FROM attendance_logs
            WHERE emp_id = ? AND log_date = ?
            ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 'in';
    $stmt->bind_param('ss', $emp_id, $today);
    if (!$stmt->execute()) { $stmt->close(); return 'in'; }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) return 'in';
    if (!empty($row['check_in']) && empty($row['check_out'])) return 'out';
    if (!empty($row['check_in']) && !empty($row['check_out'])) return 'done';
    return 'in';
}

// Compute whether a given Y-m-d date is a scheduled day off for employee using policy:
// 1 day off on days 1-15 and 1 day off on days 16-end; prefer employees.dayoff_weekday (1=Mon..6=Sun)
function is_scheduled_dayoff($date, $prefWeekday){
    // 1 day-off per half-month (1..15, 16..end)
    // Prefer the LAST occurrence of preferred weekday (1=Mon..6=Sat) in the half;
    // if none, prefer the LAST Saturday; else the LAST working day in the half.
    if (!$prefWeekday || $prefWeekday < 1 || $prefWeekday > 6) { $prefWeekday = null; }
    $ts = strtotime($date);
    $y = (int)date('Y',$ts); $m = (int)date('m',$ts);
    $workDaysFirst = []; $workDaysSecond = [];
    for ($day=1; $day<=31; $day++) {
        $cur = strtotime(sprintf('%04d-%02d-%02d',$y,$m,$day));
        if ((int)date('m',$cur) !== $m) break; // overflow
        $w = (int)date('w',$cur); // include Sunday (w=0)
        if ($day <= 15) $workDaysFirst[] = $cur; else $workDaysSecond[] = $cur;
    }
    $pick = function($arr) use($prefWeekday){
        $preferred=null; $saturday=null; $last=null;
        foreach($arr as $t){
            $w=(int)date('w',$t); $last=$t;
            if($w===6) { $saturday = $t; }
            if($prefWeekday!==null){
                // Map 1..5 => Mon..Fri (w=1..5), 6 => Sunday (w=0)
                if (($prefWeekday>=1 && $prefWeekday<=5 && $w===$prefWeekday) || ($prefWeekday===6 && $w===0)) {
                    $preferred = $t;
                }
            }
        }
        if($preferred) return $preferred;
        if($saturday) return $saturday;
        return $last;
    };
    $dayoff1 = !empty($workDaysFirst) ? $pick($workDaysFirst) : null;
    $dayoff2 = !empty($workDaysSecond)? $pick($workDaysSecond): null;
    $iso = function($t){ return date('Y-m-d',$t); };
    return ($dayoff1 && $iso($dayoff1) === $date) || ($dayoff2 && $iso($dayoff2) === $date);
}

function parse_shift_start($shift_str){
    if(!$shift_str) return null;
    $parts = preg_split('/[-–—]/u', $shift_str);
    if(!$parts || !isset($parts[0])) return null;
    $start = trim($parts[0]);
    $ts = @strtotime($start);
    if($ts === false) return null;
    return date('H:i:s', $ts);
}

// Ensure a day-off-only row exists for today (no time-in), for visibility in records
function ensure_dayoff_log($conn, $emp, $date){
    $emp_id = $emp['emp_id'];
    // if a row already exists for this emp/date, do nothing
    $chk = $conn->prepare("SELECT id FROM attendance_logs WHERE emp_id=? AND log_date=? LIMIT 1");
    if(!$chk) return false; $chk->bind_param('ss',$emp_id,$date); $chk->execute(); $res=$chk->get_result(); $row=$res?$res->fetch_assoc():null; $chk->close();
    if($row) return false;
    $sql = "INSERT INTO attendance_logs (emp_id, full_name, branch_name, branch_location, position, log_date, is_dayoff) VALUES (?,?,?,?,?,?,1)";
    $stmt = $conn->prepare($sql);
    if(!$stmt) return false;
    $stmt->bind_param('ssssss', $emp_id, $emp['full_name'], $emp['branch_name'], $emp['branch_location'], $emp['position'], $date);
    $ok = $stmt->execute(); $stmt->close(); return $ok;
}

// Persist a placeholder row for On Leave days so history keeps them after status changes.
function ensure_onleave_log($conn, $emp, $date){
    $emp_id = $emp['emp_id'];
    $chk = $conn->prepare("SELECT id FROM attendance_logs WHERE emp_id=? AND log_date=? LIMIT 1");
    if(!$chk) return false; $chk->bind_param('ss',$emp_id,$date); $chk->execute(); $res=$chk->get_result(); $row=$res?$res->fetch_assoc():null; $chk->close();
    if($row) return false;
    $ins = $conn->prepare("INSERT INTO attendance_logs (emp_id, full_name, branch_name, branch_location, position, log_date, is_onleave) VALUES (?,?,?,?,?,?,1)");
    if(!$ins) return false;
    $ins->bind_param('ssssss',$emp_id,$emp['full_name'],$emp['branch_name'],$emp['branch_location'],$emp['position'],$date);
    $ok = $ins->execute(); $ins->close(); return $ok;
}

$self_cache = null;
function get_self_employee($conn) {
    global $self_cache;
    if ($self_cache !== null) return $self_cache;
    if (!isset($_SESSION['username'])) return null;
    $username = $_SESSION['username'];
    // Unified table: employees (lookup by username; fallback to emp_id if needed)
    $row = null;
    $stmt = $conn->prepare("SELECT username, emp_id, full_name, branch_name, branch_location, position, dayoff_weekday, status FROM employees WHERE username = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $username);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
        }
        $stmt->close();
    }
    if (!$row && isset($_SESSION['employee_id']) && $_SESSION['employee_id']) {
        $empId = $_SESSION['employee_id'];
        $stmt2 = $conn->prepare("SELECT username, emp_id, full_name, branch_name, branch_location, position, dayoff_weekday, status FROM employees WHERE emp_id = ? LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param('s', $empId);
            if ($stmt2->execute()) {
                $res2 = $stmt2->get_result();
                $row = $res2 ? $res2->fetch_assoc() : null;
            }
            $stmt2->close();
        }
    }
    if (!$row || empty($row['emp_id'])) return null;
    $self_cache = [
        'emp_id' => $row['emp_id'],
        'full_name' => $row['full_name'],
        'branch_name' => $row['branch_name'],
        'branch_location' => $row['branch_location'],
        'position' => $row['position'],
        'dayoff_weekday' => isset($row['dayoff_weekday']) ? (int)$row['dayoff_weekday'] : null,
        'status' => isset($row['status']) ? $row['status'] : null
    ];
    return $self_cache;
}

$op = post('op');
$rfid = post('rfid'); // can be RFID code or emp_id
$action = strtolower(post('action', ''));

if ($op === 'scan') {
    if (!$rfid) { echo json_encode(['success'=>false,'message'=>'Missing RFID/Employee ID']); exit; }
    $emp = find_employee($conn, $rfid);
    if (!$emp) { echo json_encode(['success'=>false,'message'=>'Employee not found']); exit; }
    $suggest = suggest_action($conn, $emp['emp_id']);
    echo json_encode([
        'success' => true,
        'employee' => $emp,
        'suggested_action' => $suggest,
        'now' => date('Y-m-d H:i:s')
    ]);
    exit;
}

if ($op === 'scan_self') {
    $emp = get_self_employee($conn);
    if (!$emp) { echo json_encode(['success'=>false,'message'=>'Not logged in or employee not linked']); exit; }
    // Block access for terminated or suspended accounts
    $empStatus = strtolower(trim((string)($emp['status'] ?? '')));
    if ($empStatus === 'terminated') { session_unset(); session_destroy(); echo json_encode(['success'=>false,'message'=>'Account terminated']); exit; }
    if ($empStatus === 'suspended')  { session_unset(); session_destroy(); echo json_encode(['success'=>false,'message'=>'Account suspended. Contact your auditor.']); exit; }
    $today = date('Y-m-d');
    $isDayoffToday = is_scheduled_dayoff($today, isset($emp['dayoff_weekday']) ? (int)$emp['dayoff_weekday'] : null);
    $statusNow = strtolower(trim((string)($emp['status'] ?? '')));
    if ($statusNow === 'on leave') {
        // Ensure placeholder exists so records show the leave day (non-destructive if already present)
        try { ensure_onleave_log($conn, $emp, $today); } catch (Throwable $e) {}
    }
    $suggest = ($statusNow === 'on leave') ? 'onleave' : ($isDayoffToday ? 'dayoff' : suggest_action($conn, $emp['emp_id']));
    // Holiday advisory (Option B): non-blocking advisory for UI. Use holidays helper which may be DB-backed.
    $isHoliday = false; $holidayDates = [];
    try {
        $isHoliday = is_holiday_date($today);
        $holidayDates = get_holidays_between($today, $today);
    } catch (Throwable $e) { /* ignore holiday helper failures */ }

    echo json_encode([
        'success' => true,
        'employee' => $emp,
        'suggested_action' => $suggest,
        'is_dayoff_today' => $isDayoffToday ? 1 : 0,
        'is_onleave_today' => ($statusNow === 'on leave') ? 1 : 0,
        'is_holiday_today' => $isHoliday ? 1 : 0,
        'holiday_dates' => $holidayDates,
        'holiday_message' => $isHoliday ? 'Today is a holiday — you may still Time In.' : '',
        'now' => date('Y-m-d H:i:s')
    ]);
    exit;
}

if ($op === 'log') {
    if (!$rfid || ($action !== 'in' && $action !== 'out')) {
        echo json_encode(['success'=>false,'message'=>'Missing required fields']); exit;
    }
    $emp = find_employee($conn, $rfid);
    if (!$emp) { echo json_encode(['success'=>false,'message'=>'Employee not found']); exit; }

    $emp_id = $emp['emp_id'];
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');

        if ($action === 'in') {
            $statusNow = strtolower(trim((string)($emp['status'] ?? '')));
            if ($statusNow === 'on leave') {
                echo json_encode(['success'=>false,'message'=>'Cannot time in: you are marked On Leave today','on_leave'=>1]); exit;
            }
        // Allow time-in on scheduled day off; record entries with is_dayoff=1 when applicable.
        // (Previously this returned an error and prevented storing any row.)
        // Enforce only one attendance per day (block if any record exists today)
        $chk = $conn->prepare("SELECT id, check_in, check_out FROM attendance_logs WHERE emp_id=? AND log_date=? ORDER BY id DESC LIMIT 1");
        $chk->bind_param('ss', $emp_id, $today);
        $chk->execute();
        $cres = $chk->get_result();
        $ce = $cres ? $cres->fetch_assoc() : null;
        $chk->close();
        if ($ce) {
            if (!empty($ce['check_in']) && empty($ce['check_out'])) {
                echo json_encode(['success'=>true,'message'=>'Already timed in today','data'=>['emp_id'=>$emp_id,'check_in'=>$ce['check_in']]]); exit;
            }
            // Already completed attendance (check_out present)
            echo json_encode(['success'=>false,'message'=>'Attendance already completed for today']); exit;
        }
        // Check if already timed in today without checkout
        $sql = "SELECT id, check_in, check_out FROM attendance_logs WHERE emp_id=? AND log_date=? ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $emp_id, $today);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row && !empty($row['check_in']) && empty($row['check_out'])) {
            echo json_encode(['success'=>true,'message'=>'Already timed in today','data'=>['emp_id'=>$emp_id,'check_in'=>$row['check_in']]]);
            exit;
        }
        // Enforce only one attendance per day (block if any record exists today)
        $chk = $conn->prepare("SELECT id, check_in, check_out FROM attendance_logs WHERE emp_id=? AND log_date=? ORDER BY id DESC LIMIT 1");
        $chk->bind_param('ss', $emp_id, $today);
        $chk->execute();
        $cres = $chk->get_result();
        $ce = $cres ? $cres->fetch_assoc() : null;
        $chk->close();
        if ($ce) {
            if (!empty($ce['check_in']) && empty($ce['check_out'])) {
                echo json_encode(['success'=>true,'message'=>'Already timed in today','data'=>['emp_id'=>$emp_id,'check_in'=>$ce['check_in']]]); exit;
            }
            // Already completed attendance (check_out present)
            echo json_encode(['success'=>false,'message'=>'Attendance already completed for today']); exit;
        }

    // Compute and persist is_dayoff flag
    $isDayOff = is_scheduled_dayoff($today, isset($emp['dayoff_weekday']) ? (int)$emp['dayoff_weekday'] : null) ? 1 : 0;
    $sql = "INSERT INTO attendance_logs (emp_id, full_name, branch_name, branch_location, position, log_date, check_in, is_dayoff)
        VALUES (?,?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssssi', $emp_id, $emp['full_name'], $emp['branch_name'], $emp['branch_location'], $emp['position'], $today, $now, $isDayOff);
        if (!$stmt->execute()) {
            echo json_encode(['success'=>false,'message'=>'Failed to log time in: '.$stmt->error]);
            $stmt->close(); exit;
        }
        $stmt->close();
    echo json_encode(['success'=>true,'message'=>'Time in recorded','data'=>['emp_id'=>$emp_id,'check_in'=>$now,'is_dayoff'=>$isDayOff]]);
        exit;
    }

    if ($action === 'out') {
        // Find open record
        $sql = "SELECT id FROM attendance_logs WHERE emp_id=? AND log_date=? AND check_out IS NULL ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $emp_id, $today);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            echo json_encode(['success'=>false,'message'=>'No open time-in found for today']);
            exit;
        }

        $sql = "UPDATE attendance_logs SET check_out=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $now, $row['id']);
        if (!$stmt->execute()) {
            echo json_encode(['success'=>false,'message'=>'Failed to log time out: '.$stmt->error]);
            $stmt->close(); exit;
        }
        $stmt->close();
        echo json_encode(['success'=>true,'message'=>'Time out recorded','data'=>['emp_id'=>$emp_id,'check_out'=>$now]]);
        exit;
    }
}

if ($op === 'log_self') {
    if ($action !== 'in' && $action !== 'out') {
        echo json_encode(['success'=>false,'message'=>'Missing or invalid action']); exit;
    }
    $emp = get_self_employee($conn);
    if (!$emp) { echo json_encode(['success'=>false,'message'=>'Not logged in or employee not linked']); exit; }
    // Block access for terminated or suspended accounts (prevent API use)
    $empStatus = strtolower(trim((string)($emp['status'] ?? '')));
    if ($empStatus === 'terminated') { session_unset(); session_destroy(); echo json_encode(['success'=>false,'message'=>'Account terminated']); exit; }
    if ($empStatus === 'suspended')  { session_unset(); session_destroy(); echo json_encode(['success'=>false,'message'=>'Account suspended. Contact your auditor.']); exit; }

    $emp_id = $emp['emp_id'];
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');

        if ($action === 'in') {
            $statusNow = strtolower(trim((string)($emp['status'] ?? '')));
            if ($statusNow === 'on leave') {
                echo json_encode(['success'=>false,'message'=>'Cannot time in: you are marked On Leave today','on_leave'=>1]); exit;
            }
        // Allow self time-in on scheduled day off; will record with is_dayoff=1 when applicable.
        // Optional overrides for branch info (from UI selection)
        $bn_override = post('branch_name_override');
        $bl_override = post('branch_location_override');
        $branch_name = ($bn_override !== null && $bn_override !== '') ? $bn_override : ($emp['branch_name'] ?? null);
        $branch_location = ($bl_override !== null && $bl_override !== '') ? $bl_override : ($emp['branch_location'] ?? null);

        // Basic normalization & validation
        if ($branch_name) { $branch_name = trim($branch_name); }
        if ($branch_location) { $branch_location = trim($branch_location); }
        if ($branch_name === '') { $branch_name = null; }
        if ($branch_location === '') { $branch_location = null; }

        // If override provided but location missing, set placeholder
        if ($bn_override && !$branch_location) {
            $branch_location = 'OVERRIDE-NO-LOCATION';
        }

        // Length guard to prevent silent truncate issues
        if ($branch_name && strlen($branch_name) > 100) {
            echo json_encode(['success'=>false,'message'=>'Branch name too long (max 100 chars)']); exit;
        }
        if ($branch_location && strlen($branch_location) > 150) {
            echo json_encode(['success'=>false,'message'=>'Branch location too long (max 150 chars)']); exit;
        }

        $sql = "SELECT id, check_in, check_out FROM attendance_logs WHERE emp_id=? AND log_date=? ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $emp_id, $today);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row && !empty($row['check_in']) && empty($row['check_out'])) {
            echo json_encode(['success'=>true,'message'=>'Already timed in today','data'=>['emp_id'=>$emp_id,'check_in'=>$row['check_in']]]);
            exit;
        }

        $isDayOff = is_scheduled_dayoff($today, isset($emp['dayoff_weekday']) ? (int)$emp['dayoff_weekday'] : null) ? 1 : 0;
        $sql = "INSERT INTO attendance_logs (emp_id, full_name, branch_name, branch_location, position, log_date, check_in, is_dayoff)
                VALUES (?,?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        $bn = $branch_name; $bl = $branch_location;
        $stmt->bind_param('sssssssi', $emp_id, $emp['full_name'], $bn, $bl, $emp['position'], $today, $now, $isDayOff);
        if (!$stmt->execute()) {
            echo json_encode([
                'success'=>false,
                'message'=>'Failed to log time in: '.$stmt->error,
                'debug'=>[
                    'emp_id'=>$emp_id,
                    'branch_name'=>$bn,
                    'branch_location'=>$bl,
                    'override_sent'=>[$bn_override,$bl_override]
                ]
            ]);
            $stmt->close(); exit;
        }
        $stmt->close();
        echo json_encode([
            'success'=>true,
            'message'=>'Time in recorded',
            'data'=>[
                'emp_id'=>$emp_id,
                'check_in'=>$now,
                'branch_name'=>$bn,
                'branch_location'=>$bl,
                'override_used'=> ($bn_override || $bl_override) ? true : false,
                'is_dayoff'=>$isDayOff
            ]
        ]);
        exit;
    }

    if ($action === 'out') {
        $sql = "SELECT id FROM attendance_logs WHERE emp_id=? AND log_date=? AND check_out IS NULL ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $emp_id, $today);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            echo json_encode(['success'=>false,'message'=>'No open time-in found for today']);
            exit;
        }

        $sql = "UPDATE attendance_logs SET check_out=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $now, $row['id']);
        if (!$stmt->execute()) {
            echo json_encode(['success'=>false,'message'=>'Failed to log time out: '.$stmt->error]);
            $stmt->close(); exit;
        }
        $stmt->close();
        echo json_encode(['success'=>true,'message'=>'Time out recorded','data'=>['emp_id'=>$emp_id,'check_out'=>$now]]);
        exit;
    }
}

// History self (last 30 days)
if ($op === 'history_self') {
    $emp = get_self_employee($conn);
    if (!$emp) { echo json_encode(['success'=>false,'message'=>'Not logged in or employee not linked']); exit; }
    $emp_id = $emp['emp_id'];
    $hasLeaveCol = attendance_has_onleave($conn);
    if ($hasLeaveCol) {
        $sql = "SELECT log_date, branch_name, branch_location, check_in, check_out, is_dayoff, is_onleave FROM attendance_logs
                WHERE emp_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY log_date DESC";
    } else {
        $sql = "SELECT log_date, branch_name, branch_location, check_in, check_out, is_dayoff FROM attendance_logs
                WHERE emp_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY log_date DESC";
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) { echo json_encode(['success'=>false,'message'=>'Failed to prepare history query']); exit; }
    $stmt->bind_param('s', $emp_id);
    if (!$stmt->execute()) { echo json_encode(['success'=>false,'message'=>'Failed to execute history']); $stmt->close(); exit; }
    $res = $stmt->get_result();
    $rows = [];
    $existingDates = [];
    // Fetch employee shift start (if any) to evaluate late vs present
    $shiftStart = null;
    $sstmt = $conn->prepare("SELECT shift_schedule FROM employees WHERE emp_id=? LIMIT 1");
    if ($sstmt) {
        $sstmt->bind_param('s', $emp_id);
        if ($sstmt->execute()) {
            $sres = $sstmt->get_result(); $sr = $sres ? $sres->fetch_assoc() : null;
            if ($sr) $shiftStart = parse_shift_start($sr['shift_schedule'] ?? null);
        }
        $sstmt->close();
    }

    $empStatusLocal = isset($emp['status']) ? $emp['status'] : null;
    while ($r = $res->fetch_assoc()) {
        $checkIn = $r['check_in'];
        $checkOut = $r['check_out'];
        $hours = null;
        if ($checkIn && $checkOut) {
            $hours = round((strtotime($checkOut) - strtotime($checkIn)) / 3600, 2);
        }
        $isDayoffFlag = (int)($r['is_dayoff'] ?? 0) === 1;
        $isOnLeaveFlag = $hasLeaveCol ? ((int)($r['is_onleave'] ?? 0) === 1) : false;
        if ($isOnLeaveFlag) {
            $checkIn = null; $checkOut = null; $hours = null;
            $remarks = 'On Leave';
        } else if ($isDayoffFlag) {
            // For scheduled day off: if no logs, show as Day Off (no times). If logs exist, mark worked.
            if ($checkIn || $checkOut) {
                $remarks = 'Day Off (worked)';
            } else {
                $checkIn = null; $checkOut = null; $hours = null;
                $remarks = 'Day Off';
            }
        } else {
            // Remarks logic for regular days, using employee shift start when available
            $empCutoff = $shiftStart ?? '08:15:00';
            // Absent
            if (!$checkIn && !$checkOut) {
                $remarks = 'Absent';
            } else if ($hours !== null && $hours < 4) {
                $remarks = 'Under Hours';
            } else {
                $late = false;
                if ($checkIn) {
                    $t = substr($checkIn, 11, 8);
                    if ($t > $empCutoff) $late = true;
                }
                if ($late && !$checkOut) {
                    $remarks = 'Late • No Time Out';
                } else if ($late) {
                    $remarks = 'Late';
                } else if ($checkIn && !$checkOut) {
                    $remarks = 'No Time Out';
                } else {
                    $remarks = 'OK';
                }
            }
        }
        $existingDates[$r['log_date']] = true;
        $rows[] = [
            'date' => $r['log_date'],
            'branch_name' => $r['branch_name'],
            'branch_location' => $r['branch_location'],
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total_hours' => $hours,
            'remarks' => $remarks,
            'is_dayoff' => $isDayoffFlag ? 1 : 0,
            'is_onleave' => $isOnLeaveFlag ? 1 : 0
        ];
    }
    $stmt->close();

    // Inject holiday rows for the last 30 days window so holidays appear in user's history as "Holiday"
    require_once __DIR__ . '/holidays.php';
    $startWindow = date('Y-m-d', strtotime('-30 days'));
    $hols = get_holidays_between($startWindow, date('Y-m-d'));
    foreach($hols as $h){
        if (isset($existingDates[$h])) continue;
        // Add a synthetic holiday row
        $rows[] = [
            'date' => $h,
            'branch_name' => $emp['branch_name'] ?? null,
            'branch_location' => $emp['branch_location'] ?? null,
            'check_in' => null,
            'check_out' => null,
            'total_hours' => null,
            'remarks' => 'Holiday',
            'is_dayoff' => 0,
            'is_onleave' => 0
        ];
        $existingDates[$h] = true;
    }

    // Synthesize day-off entry ONLY for today (no retroactive change when day-off preference is updated)
    $prefW = isset($emp['dayoff_weekday']) ? (int)$emp['dayoff_weekday'] : null;
    $today = date('Y-m-d');
    if (!isset($existingDates[$today])) {
        $statusNow = isset($emp['status']) ? strtolower(trim((string)$emp['status'])) : '';
        if ($statusNow === 'on leave') {
            if ($hasLeaveCol) { try { ensure_onleave_log($conn, $emp, $today); } catch (Throwable $e) { /* ignore */ } }
            $rows[] = [
                'date' => $today,
                'branch_name' => $emp['branch_name'] ?? null,
                'branch_location' => $emp['branch_location'] ?? null,
                'check_in' => null,
                'check_out' => null,
                'total_hours' => null,
                'remarks' => 'On Leave',
                'is_dayoff' => 0,
                'is_onleave' => $hasLeaveCol ? 1 : 0
            ];
        } else if (is_scheduled_dayoff($today, $prefW)) {
        // Persist a day-off only row for visibility in DB so UI and DB remain consistent.
        // ensure_dayoff_log will noop if a row already exists.
        try {
            ensure_dayoff_log($conn, $emp, $today);
        } catch (Throwable $e) { /* continue — best-effort */ }
        $rows[] = [
            'date' => $today,
            'branch_name' => $emp['branch_name'] ?? null,
            'branch_location' => $emp['branch_location'] ?? null,
            'check_in' => null,
            'check_out' => null,
            'total_hours' => null,
            'remarks' => 'Day Off',
            'is_dayoff' => 1,
            'is_onleave' => $hasLeaveCol ? 0 : 0
        ];
        }
    }

    // Sort by date desc to keep UI stable
    usort($rows, function($a,$b){ return strcmp($b['date'], $a['date']); });

    echo json_encode(['success'=>true,'employee'=>['emp_id'=>$emp_id,'full_name'=>$emp['full_name'],'status'=>$emp['status'] ?? null], 'history'=>$rows, 'has_onleave_column'=>$hasLeaveCol ? 1 : 0]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;

