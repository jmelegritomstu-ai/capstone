<?php
header('Content-Type: application/json');
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/employee_db.php';
// Holiday helper (may be DB-backed)
// no holidays helper required in this version

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not initialized']);
    exit;
}

function qp($key, $default = null){
    if (isset($_GET[$key])) return trim($_GET[$key]);
    if (isset($_POST[$key])) return trim($_POST[$key]);
    return $default;
}

// Inputs
$branch = qp('branch', ''); // treat as branch_location (single)
$branch_locations_csv = qp('branch_locations', ''); // optional CSV for multi-location
$branch_locations = [];
if ($branch_locations_csv !== '') {
    foreach (explode(',', $branch_locations_csv) as $b) {
        $b = trim($b);
        if ($b !== '') $branch_locations[] = $b;
    }
}
$from   = qp('from_date', '');
$to     = qp('to_date', '');

$today = date('Y-m-d');
if (!$to)   $to = $today;
if (!$from) $from = date('Y-m-d', strtotime($to . ' -6 days'));

// Validate dates
$from_dt = DateTime::createFromFormat('Y-m-d', $from);
$to_dt   = DateTime::createFromFormat('Y-m-d', $to);
if (!$from_dt || !$to_dt) {
    echo json_encode(['success'=>false,'message'=>'Invalid date format']);
    exit;
}
if ($from_dt > $to_dt) {
    $tmp = $from; $from = $to; $to = $tmp;
}

$cutoff = '08:15:00';

function parse_shift_start($shift_str){
    if(!$shift_str) return null;
    // Expect formats like '8:00am-5:00pm' or '08:00-17:00' or '8:00-17:00'
    $parts = preg_split('/[-–—]/u', $shift_str);
    if(!$parts || !isset($parts[0])) return null;
    $start = trim($parts[0]);
    // Try strtotime to parse time; prepend a date to ensure consistent formatting
    $ts = @strtotime($start);
    if($ts === false) return null;
    return date('H:i:s', $ts);
}

// Build filters
$empWhere = "WHERE role='account_executive'";
$empParams = [];
$empTypes  = '';
if (!empty($branch_locations)) {
    $place = implode(',', array_fill(0, count($branch_locations), '?'));
    $empWhere .= " AND branch_location IN ($place)";
    $empParams = array_merge($empParams, $branch_locations);
    $empTypes  .= str_repeat('s', count($branch_locations));
} elseif ($branch !== '') {
    $empWhere .= " AND (branch_location = ?)";
    $empParams[] = $branch; $empTypes .= 's';
}

$logWhere = "WHERE log_date BETWEEN ? AND ?";
$logParams = [$from, $to];
$logTypes  = 'ss';
if (!empty($branch_locations)) {
    $place = implode(',', array_fill(0, count($branch_locations), '?'));
    $logWhere .= " AND branch_location IN ($place)";
    foreach ($branch_locations as $b) { $logParams[] = $b; }
    $logTypes  .= str_repeat('s', count($branch_locations));
} elseif ($branch !== '') {
    $logWhere .= " AND (branch_location = ?)";
    $logParams[] = $branch; $logTypes .= 's';
}

// 1) Total employees in scope (Account Executives)
$total_employees = 0;
$roster_source = 'employees';
$sql = "SELECT COUNT(*) AS c FROM employees $empWhere";
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($empTypes) { $stmt->bind_param($empTypes, ...$empParams); }
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($res) { $row = $res->fetch_assoc(); $total_employees = (int)($row['c'] ?? 0); }
    }
    $stmt->close();
}

// Fallback: if roster is zero but there are logs in range, derive roster from logs joined with employees (account_executive only)
if ($total_employees === 0) {
    $logWhereA = "WHERE a.log_date BETWEEN ? AND ?";
    $logParamsA = [$from, $to];
    $logTypesA = 'ss';
    if (!empty($branch_locations)) {
        $place = implode(',', array_fill(0, count($branch_locations), '?'));
        $logWhereA .= " AND (a.branch_location IN ($place))";
        foreach ($branch_locations as $b) { $logParamsA[] = $b; }
        $logTypesA .= str_repeat('s', count($branch_locations));
    } elseif ($branch !== '') {
        $logWhereA .= " AND (a.branch_location = ?)";
        $logParamsA[] = $branch; $logTypesA .= 's';
    }

    $joinSql = "SELECT COUNT(DISTINCT e.emp_id) AS c
                FROM attendance_logs a
                INNER JOIN employees e ON e.emp_id = a.emp_id AND e.role='account_executive'
                $logWhereA";
    $stmt = $conn->prepare($joinSql);
    if ($stmt) {
        $stmt->bind_param($logTypesA, ...$logParamsA);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res) { $row = $res->fetch_assoc(); $total_employees = (int)($row['c'] ?? 0); }
        }
        $stmt->close();
    }
    if ($total_employees > 0) { $roster_source = 'logs_join_employees'; }
}

// Last resort: count distinct emp_id from logs (no role filter) to avoid zero when there are recent logs
if ($total_employees === 0) {
    $logWhereA = "WHERE a.log_date BETWEEN ? AND ?";
    $logParamsA = [$from, $to];
    $logTypesA = 'ss';
    if (!empty($branch_locations)) {
        $place = implode(',', array_fill(0, count($branch_locations), '?'));
        $logWhereA .= " AND (a.branch_location IN ($place))";
        foreach ($branch_locations as $b) { $logParamsA[] = $b; }
        $logTypesA .= str_repeat('s', count($branch_locations));
    } elseif ($branch !== '') {
        $logWhereA .= " AND (a.branch_location = ?)";
        $logParamsA[] = $branch; $logTypesA .= 's';
    }
    $fbSql = "SELECT COUNT(DISTINCT a.emp_id) AS c FROM attendance_logs a $logWhereA";
    $stmt = $conn->prepare($fbSql);
    if ($stmt) {
        $stmt->bind_param($logTypesA, ...$logParamsA);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res) { $row = $res->fetch_assoc(); $total_employees = (int)($row['c'] ?? 0); }
        }
        $stmt->close();
    }
    if ($total_employees > 0) { $roster_source = 'logs_distinct'; }
}

// Generate date list for range
$dates = [];
$iter = new DatePeriod(new DateTime($from), new DateInterval('P1D'), (new DateTime($to))->modify('+1 day'));
foreach ($iter as $d) { $dates[$d->format('Y-m-d')] = ['present'=>0,'late'=>0,'absent'=>0,'underhours'=>0,'onleave'=>0]; }
$days_count = count($dates);


// Fetch roster with preferred dayoff (emp_id, dayoff_weekday) and shift schedule
$empDayoff = []; $empShiftStart = []; $empStatus = [];
$empSql = "SELECT emp_id, dayoff_weekday, shift_schedule, status FROM employees $empWhere";
$stmt = $conn->prepare($empSql);
if ($stmt) {
    if ($empTypes) { $stmt->bind_param($empTypes, ...$empParams); }
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $pref = isset($r['dayoff_weekday']) ? (int)$r['dayoff_weekday'] : null;
            if ($pref !== null && ($pref < 1 || $pref > 7)) $pref = null;
            $empDayoff[$r['emp_id']] = $pref;
            $empShiftStart[$r['emp_id']] = parse_shift_start($r['shift_schedule'] ?? null);
            $empStatus[$r['emp_id']] = isset($r['status']) ? $r['status'] : null;
        }
    }
    $stmt->close();
}
// Discover per-day On Leave flags from attendance_logs.is_onleave (persistent); avoid blanket range application.
$onLeaveDates = [];
$hasOnLeaveCol = false;
$colRes = $conn->query("SHOW COLUMNS FROM attendance_logs LIKE 'is_onleave'");
if ($colRes && $colRes->num_rows > 0) { $hasOnLeaveCol = true; }
if ($colRes) { $colRes->free(); }
if ($hasOnLeaveCol) {
    $onLeaveSql = "SELECT log_date, emp_id FROM attendance_logs $logWhere AND is_onleave=1 GROUP BY log_date, emp_id";
    $stmt = $conn->prepare($onLeaveSql);
    if ($stmt) {
        $stmt->bind_param($logTypes, ...$logParams);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $ld = $r['log_date']; $eid = $r['emp_id'];
                if (!isset($onLeaveDates[$ld])) $onLeaveDates[$ld] = [];
                $onLeaveDates[$ld][$eid] = true;
            }
        }
        $stmt->close();
    }
}

// Auto-create today's placeholder row for employees currently marked On Leave (non-destructive, only if missing)
if ($hasOnLeaveCol) {
    foreach ($empStatus as $eid => $st) {
        if (!$st) continue; 
        if (strtolower(trim((string)$st)) !== 'on leave') continue;
        // Skip if today's leave already recorded
        if (isset($onLeaveDates[$today]) && isset($onLeaveDates[$today][$eid])) continue;
        // Check if any row exists for today
        $chk = $conn->prepare("SELECT id, is_onleave FROM attendance_logs WHERE emp_id=? AND log_date=? LIMIT 1");
        if ($chk) {
            $chk->bind_param('ss',$eid,$today);
            if ($chk->execute()) {
                $cr = $chk->get_result(); $row = $cr ? $cr->fetch_assoc() : null;
                if ($row) {
                    if ((int)($row['is_onleave'] ?? 0) === 0) {
                        $upd = $conn->prepare("UPDATE attendance_logs SET is_onleave=1 WHERE id=?");
                        if ($upd) { $upd->bind_param('i',$row['id']); $upd->execute(); $upd->close(); }
                    }
                } else {
                    // Insert placeholder (no times)
                    $ins = $conn->prepare("INSERT INTO attendance_logs (emp_id, full_name, branch_name, branch_location, position, log_date, is_onleave) SELECT emp_id, full_name, branch_name, branch_location, position, ?, 1 FROM employees WHERE emp_id=? LIMIT 1");
                    if ($ins) { $ins->bind_param('ss',$today,$eid); $ins->execute(); $ins->close(); }
                }
            }
            $chk->close();
        }
        // Update in-memory map for current response
        if (!isset($onLeaveDates[$today])) $onLeaveDates[$today] = [];
        $onLeaveDates[$today][$eid] = true;
    }
}

// Additionally, for employees currently marked 'On Leave', mark recent days in-range as onleave
// so the Attendance Trend shows them as on-leave until their status changes. This is an
// in-memory augmentation (does not persist historical placeholders) and is limited to the
// last N days to avoid over-marking. Adjust N as needed (default 10 days).
// Instead of a fixed N-day backward span, mark On Leave for all dates in the requested range
// (still non-destructive: no DB writes). This is minimal and ensures the UI shows On Leave
// for the selected interval without touching other code paths.
if ($hasOnLeaveCol) {
    // Only mark today's date for employees currently set to 'On Leave'. This avoids
    // retroactively marking past dates in the selected range and ensures On Leave
    // appears day-by-day as the system date advances (unless auditor changes status).
    foreach ($empStatus as $eid => $st) {
        if (!$st) continue;
        if (strtolower(trim((string)$st)) !== 'on leave') continue;
        $d = $today;
        if (isset($onLeaveDates[$d]) && isset($onLeaveDates[$d][$eid])) continue;
        // If an attendance row exists for today, skip marking (preserve real data)
        $chk = $conn->prepare("SELECT id FROM attendance_logs WHERE emp_id=? AND log_date=? LIMIT 1");
        if ($chk) {
            $chk->bind_param('ss', $eid, $d);
            if ($chk->execute()) {
                $cr = $chk->get_result(); $row = $cr ? $cr->fetch_assoc() : null;
                $chk->close();
                if ($row) continue;
            } else { $chk->close(); }
        }
        if (!isset($onLeaveDates[$d])) $onLeaveDates[$d] = [];
        $onLeaveDates[$d][$eid] = true;
    }
}


// Precompute dayoff dates per employee for months in range
function pick_dayoff_in_half($days, $preferredWeekday) {
    $match = null; $sat = null; $last = null;
    foreach ($days as $d) {
        $ts = strtotime($d);
        $w = (int)date('w', $ts); // 0 Sun .. 6 Sat
        $last = $d;
        if ($preferredWeekday !== null && $w === $preferredWeekday) { $match = $d; break; }
        if ($sat === null && $w === 6) { $sat = $d; }
    }
    if ($match) return $match;
    if ($sat) return $sat;
    return $last;
}

$dayoffsByDate = [];
if (!empty($empDayoff)) {
    // Collect months in range
    $months = [];
    $it = new DatePeriod(new DateTime($from), new DateInterval('P1D'), (new DateTime($to))->modify('+1 day'));
    foreach ($it as $d) { $months[$d->format('Y-m')] = true; }
    foreach (array_keys($months) as $ym) {
        list($yy,$mm) = explode('-', $ym);
        $yy = (int)$yy; $mm = (int)$mm;
        // Build working days Mon-Sat for this month
        $workDays = [];
        for ($dd=1; $dd<=31; $dd++) {
            $ts = strtotime(sprintf('%04d-%02d-%02d', $yy,$mm,$dd));
            if ((int)date('m',$ts) !== $mm) break;
            $w = (int)date('w',$ts);
            if ($w === 0) continue; // skip Sunday
            $workDays[] = date('Y-m-d',$ts);
        }
        $firstHalf = array_filter($workDays, function($d){ return (int)substr($d,8,2) <= 15; });
        $secondHalf = array_filter($workDays, function($d){ return (int)substr($d,8,2) >= 16; });
        foreach ($empDayoff as $eid => $prefW) {
            if (empty($workDays)) continue;
            if (!empty($firstHalf)) {
                $d1 = pick_dayoff_in_half($firstHalf, $prefW);
                $dayoffsByDate[$d1][$eid] = true;
            }
            if (!empty($secondHalf)) {
                $d2 = pick_dayoff_in_half($secondHalf, $prefW);
                $dayoffsByDate[$d2][$eid] = true;
            }
        }
    }
}

// (holidays integration removed)

// 2) Daily distinct attendance window per emp (first_in/last_out) to evaluate 8-hour duty
$subSql = "SELECT log_date, emp_id, MIN(check_in) AS first_in, MAX(check_out) AS last_out
           FROM attendance_logs
           $logWhere
           GROUP BY log_date, emp_id";
$stmt = $conn->prepare($subSql);
$present_total = 0; $late_total = 0; $absent_total = 0; $under_total = 0; $onleave_total = 0;

if ($stmt) {
    $stmt->bind_param($logTypes, ...$logParams);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $date = $r['log_date'];
            $first_in = $r['first_in'];
            if (!isset($dates[$date])) continue;
            if ($first_in) {
                if (!isset($dates[$date])) continue;
                if ($first_in) {
                    $last_out = $r['last_out'] ?? null;
                    $hours = null;
                    if ($last_out) {
                        $mins = (strtotime($last_out) - strtotime($first_in)) / 60.0;
                        $hours = $mins / 60.0;
                    }
                    $t = substr($first_in, 11, 8); // HH:MM:SS
                    $empId = $r['emp_id'];
                    $empCutoff = ($empShiftStart[$empId] ?? $cutoff);

                    if ($last_out) {
                        // If we have a checkout, keep previous behavior: require 8 hours for present/late or mark underhours
                        if ($hours !== null && $hours >= 8.0) {
                            if ($t > $empCutoff) {
                                $dates[$date]['late'] += 1; $late_total += 1; $present_total += 1;
                            } else {
                                $dates[$date]['present'] += 1; $present_total += 1;
                            }
                        } else {
                            // Worked but less than 8 hours => Under Hours
                            $dates[$date]['underhours'] += 1; $under_total += 1;
                        }
                    } else {
                        // No checkout yet: count the time-in as present/late according to shift start so trend reflects remarks
                        if ($t && $empCutoff && $t > $empCutoff) {
                            $dates[$date]['late'] += 1; $late_total += 1; $present_total += 1;
                        } else {
                            $dates[$date]['present'] += 1; $present_total += 1;
                        }
                    }
                } // end inner if ($first_in)
            } // end outer if ($first_in)
        } // end while fetch_assoc
    }
    $stmt->close();
}

// Fill absents using per-day roster size (include Sundays and subtract scheduled dayoffs)
// Prepare terminated employee set to exclude them from absence roster calculations
$terminated_ids = [];
foreach ($empStatus as $eid => $st) {
    if ($st && strtolower(trim((string)$st)) === 'terminated') {
        $terminated_ids[$eid] = true;
    }
}
foreach ($dates as $d => $vals) {
    // Base roster: all employees in scope are expected to work, Sundays included
    $roster = (int)$total_employees;
    if ($roster > 0 && isset($dayoffsByDate[$d])) {
        $roster -= count($dayoffsByDate[$d]);
        if ($roster < 0) $roster = 0;
    }
    // Subtract employees marked On Leave for this specific date (persistent rows)
    $onleave_count = (isset($onLeaveDates[$d]) ? count($onLeaveDates[$d]) : 0);
    if ($roster > 0 && $onleave_count > 0) {
        $roster -= $onleave_count;
        if ($roster < 0) $roster = 0;
    }
    // Exclude terminated employees entirely from absence computation
    if ($roster > 0 && !empty($terminated_ids)) {
        $roster -= count($terminated_ids);
        if ($roster < 0) $roster = 0;
    }
    $present_day = $vals['present'] + $vals['late'];
    if ($present_day > $roster) $present_day = $roster; // clamp

    $abs = $roster - $present_day;
    if ($abs < 0) $abs = 0;
    $dates[$d]['absent'] = $abs;
    $dates[$d]['onleave'] = $onleave_count;
    $onleave_total += $onleave_count;
    $absent_total += $abs;
}

// 3) Overtime hours across range (basic): sum hours>8 per emp/day
$overtime_hours = 0.0;
$otSql = "SELECT log_date, emp_id, MIN(check_in) AS ci, MAX(check_out) AS co
          FROM attendance_logs
          $logWhere
          GROUP BY log_date, emp_id";
$stmt = $conn->prepare($otSql);
if ($stmt) {
    $stmt->bind_param($logTypes, ...$logParams);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $ci = $r['ci']; $co = $r['co'];
            if ($ci && $co) {
                $mins = (strtotime($co) - strtotime($ci)) / 60.0;
                $hours = $mins / 60.0;
                if ($hours > 8) $overtime_hours += ($hours - 8);
            }
        }
    }
    $stmt->close();
}
$overtime_hours = round($overtime_hours, 2);

// 4) Recent logs (limit 50)
$recent = [];
$recentSql = "SELECT emp_id, full_name, branch_name, branch_location, log_date, check_in, check_out, is_dayoff" . ($hasOnLeaveCol ? ", is_onleave" : "") . "
              FROM attendance_logs
              $logWhere
              ORDER BY COALESCE(updated_at, check_out, check_in, created_at) DESC
              LIMIT 50";
$stmt = $conn->prepare($recentSql);
if ($stmt) {
    $stmt->bind_param($logTypes, ...$logParams);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $ci = $r['check_in']; $co = $r['check_out'];
            $hours = null;
            if ($ci && $co) {
                $mins = (strtotime($co) - strtotime($ci)) / 60.0;
                $hours = round($mins / 60.0, 2);
            }
            $status = '-';
            if ($ci) {
                $t = substr($ci, 11, 8);
                $empId = $r['emp_id'];
                $empCutoff = ($empShiftStart[$empId] ?? $cutoff);
                $status = ($t > $empCutoff) ? 'Late' : 'Present';
            }
            $remarks = '-';
            if ($ci && !$co) $remarks = 'No Time Out';
            if ($hours !== null && $hours < 8) {
                $remarks = 'Under Hours';
                $status = 'Under Hours';
            }
            // Day Off (worked) indicator: use stored flag from attendance_logs
            if ($ci && (int)($r['is_dayoff'] ?? 0) === 1) {
                $remarks = ($remarks === '-' ? 'Day Off (worked)' : ($remarks . ' • Day Off (worked)'));
            }
            $empStat = ($empStatus[$r['emp_id']] ?? null);
            $isOnLeaveRow = $hasOnLeaveCol ? ((int)($r['is_onleave'] ?? 0) === 1) : false;
            if ($isOnLeaveRow || ($empStat && strtolower(trim((string)$empStat)) === 'on leave' && $r['log_date'] === $today && !$isOnLeaveRow)) {
                $status = 'On Leave';
                $remarks = 'On Leave';
            }

            $recent[] = [
                'employee_name' => $r['full_name'],
                'employee_id'   => $r['emp_id'],
                'branch_name'   => $r['branch_name'],
                'branch_location'=> $r['branch_location'],
                'date'          => $r['log_date'],
                'check_in'      => $ci,
                'check_out'     => $co,
                'total_hours'   => $hours,
                'status'        => $status,
                'remarks'       => $remarks,
                'shift_start'   => ($empShiftStart[$r['emp_id']] ?? null),
                'is_dayoff'     => (int)($r['is_dayoff'] ?? 0),
                'employee_status'=> $empStat,
                'is_onleave'     => $hasOnLeaveCol ? (int)($r['is_onleave'] ?? 0) : 0
            ];
        }
    }
    $stmt->close();
}

// 5) All logs in range (grouped per emp/day)
$all = [];
$allSql = "SELECT emp_id, full_name, branch_name, branch_location, log_date,
             MIN(check_in) AS first_in,
             MAX(check_out) AS last_out,
             MAX(is_dayoff) AS is_dayoff" . ($hasOnLeaveCol ? ", MAX(is_onleave) AS is_onleave" : "") . "
         FROM attendance_logs
         $logWhere
         GROUP BY log_date, emp_id, full_name, branch_name, branch_location
         ORDER BY log_date DESC, emp_id ASC";
$stmt = $conn->prepare($allSql);
if ($stmt) {
    $stmt->bind_param($logTypes, ...$logParams);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $ci = $r['first_in'];
            $co = $r['last_out'];
            $hours = null;
            if ($ci && $co) {
                $mins = (strtotime($co) - strtotime($ci)) / 60.0;
                $hours = round($mins / 60.0, 2);
            }
            $status = '-';
            if ($ci) {
                $t = substr($ci, 11, 8);
                $empId = $r['emp_id'];
                $empCutoff = ($empShiftStart[$empId] ?? $cutoff);
                $status = ($t > $empCutoff) ? 'Late' : 'Present';
            }
            $remarks = '-';
            if ($ci && !$co) $remarks = 'No Time Out';
            if ($hours !== null && $hours < 8) {
                $remarks = 'Under Hours';
                $status = 'Under Hours';
            }
            if ($ci && (int)($r['is_dayoff'] ?? 0) === 1) {
                $remarks = ($remarks === '-' ? 'Day Off (worked)' : ($remarks . ' • Day Off (worked)'));
            }
            $empStat = ($empStatus[$r['emp_id']] ?? null);
            $isOnLeaveRow = $hasOnLeaveCol ? ((int)($r['is_onleave'] ?? 0) === 1) : false;
            if ($isOnLeaveRow || ($empStat && strtolower(trim((string)$empStat)) === 'on leave' && $r['log_date'] === $today && !$isOnLeaveRow)) {
                $status = 'On Leave';
                $remarks = 'On Leave';
            }

            $all[] = [
                'employee_name' => $r['full_name'],
                'employee_id'   => $r['emp_id'],
                'branch_name'   => $r['branch_name'],
                'branch_location'=> $r['branch_location'],
                'date'          => $r['log_date'],
                'check_in'      => $ci,
                'check_out'     => $co,
                'total_hours'   => $hours,
                'status'        => $status,
                'remarks'       => $remarks,
                'shift_start'   => ($empShiftStart[$r['emp_id']] ?? null),
                'is_dayoff'     => (int)($r['is_dayoff'] ?? 0),
                'employee_status'=> $empStat,
                'is_onleave'     => $hasOnLeaveCol ? (int)($r['is_onleave'] ?? 0) : 0
            ];
        }
    }
    $stmt->close();
}

// Inject synthetic On Leave rows into `recent` and `all` when an employee/date is considered On Leave
// by the in-memory `onLeaveDates` map but no attendance_logs row exists. This is non-destructive
// and makes the dashboard and recent logs show on-leave AEs even when no DB row was persisted.
if ($hasOnLeaveCol && !empty($onLeaveDates)) {
    // Build a quick lookup of existing all rows by key emp|date
    $existingAll = [];
    foreach ($all as $a) {
        $existingAll[$a['employee_id'] . '|' . $a['date']] = true;
    }
    // Helper to fetch basic employee info (cached)
    $empInfoCache = [];
    $getEmpInfo = function($eid) use (&$conn, &$empInfoCache) {
        if (isset($empInfoCache[$eid])) return $empInfoCache[$eid];
        $stmt = $conn->prepare("SELECT emp_id, full_name, branch_name, branch_location, shift_schedule, status FROM employees WHERE emp_id=? LIMIT 1");
        $info = ['emp_id'=>$eid,'full_name'=>$eid,'branch_name'=>null,'branch_location'=>null,'shift_start'=>null,'status'=>null];
        if ($stmt) {
            $stmt->bind_param('s', $eid);
            if ($stmt->execute()) {
                $r = $stmt->get_result(); $row = $r ? $r->fetch_assoc() : null;
                if ($row) {
                    $info['emp_id'] = $row['emp_id'];
                    $info['full_name'] = $row['full_name'];
                    $info['branch_name'] = $row['branch_name'];
                    $info['branch_location'] = $row['branch_location'];
                    $info['shift_start'] = parse_shift_start($row['shift_schedule'] ?? null);
                    $info['status'] = $row['status'] ?? null;
                }
            }
            $stmt->close();
        }
        $empInfoCache[$eid] = $info; return $info;
    };

    foreach ($onLeaveDates as $d => $map) {
        foreach ($map as $eid => $_) {
            $key = $eid . '|' . $d;
            if (isset($existingAll[$key])) continue; // preserve real rows
            // Skip future dates
            if ($d > date('Y-m-d')) continue;
            $info = $getEmpInfo($eid);
            $synth = [
                'employee_name' => $info['full_name'] ?? $eid,
                'employee_id'   => $eid,
                'branch_name'   => $info['branch_name'] ?? null,
                'branch_location'=> $info['branch_location'] ?? null,
                'date'          => $d,
                'check_in'      => null,
                'check_out'     => null,
                'total_hours'   => null,
                'status'        => 'On Leave',
                'remarks'       => 'On Leave',
                'shift_start'   => $info['shift_start'] ?? null,
                'is_dayoff'     => 0,
                'employee_status'=> $info['status'] ?? null,
                'is_onleave'     => 1
            ];
            // Add to all and recent if appropriate
            $all[] = $synth;
            // Include in recent if within last 30 days and recent count limit hasn't been exceeded
            $recent[] = $synth;
            $existingAll[$key] = true;
        }
    }
}

// 6) Summary and breakdown
$total_slots = $total_employees * max(1, $days_count);
$attendance_rate = ($total_slots > 0) ? round((($present_total) / $total_slots) * 100) : 0;

$trend = [];
foreach ($dates as $d => $vals) {
    $trend[] = [
            'date' => $d,
            'present' => (int)$vals['present'],
            'late' => (int)$vals['late'],
            'absent' => (int)$vals['absent'],
            'underhours' => (int)$vals['underhours'],
            'onleave' => (int)$vals['onleave'],
            // number of employees in scope who are scheduled for a day-off on this date
            'dayoff' => isset($dayoffsByDate[$d]) ? (int)count($dayoffsByDate[$d]) : 0,
    ];
}

// Aggregate per-day occurrences (present/late merged with underhours, onleave, absent)
$sum_present = 0; $sum_late = 0; $sum_absent = 0; $sum_under = 0; $sum_onleave = 0;
foreach ($trend as $t) {
    $sum_present += $t['present'];
    $sum_late    += $t['late'];
    $sum_absent  += $t['absent'];
    $sum_under   += ($t['underhours'] ?? 0);
    $sum_onleave += ($t['onleave'] ?? 0);
}

// Static roster statuses (terminated / suspended) counted once per employee; normalize to per-day slot count for fair % distribution.
$terminated_count = 0; $suspended_count = 0;
foreach ($empStatus as $st) {
    if (!$st) continue;
    $s = strtolower(trim((string)$st));
    if ($s === 'terminated') $terminated_count++;
    elseif ($s === 'suspended') $suspended_count++;
}
// Convert to occurrences across range (so comparable to per-day sums)
$terminated_occ = $terminated_count * max(1,$days_count);
$suspended_occ  = $suspended_count * max(1,$days_count);

$sum_total = $sum_present + $sum_late + $sum_under + $sum_onleave + $sum_absent + $terminated_occ + $suspended_occ;
$breakdown = [
    'present_pct'    => $sum_total>0 ? round(($sum_present/$sum_total)*100) : 0,
    'late_pct'       => $sum_total>0 ? round(($sum_late/$sum_total)*100) : 0,
    'under_pct'      => $sum_total>0 ? round(($sum_under/$sum_total)*100) : 0,
    'onleave_pct'    => $sum_total>0 ? round(($sum_onleave/$sum_total)*100) : 0,
    'absent_pct'     => $sum_total>0 ? round(($sum_absent/$sum_total)*100) : 0,
    'terminated_pct' => $sum_total>0 ? round(($terminated_occ/$sum_total)*100) : 0,
    'suspended_pct'  => $sum_total>0 ? round(($suspended_occ/$sum_total)*100) : 0,
    // raw counts (per-day occurrences for dynamic statuses; single counts for roster statuses)
    'present'    => $sum_present,
    'late'       => $sum_late,
    'under'      => $sum_under,
    'onleave'    => $sum_onleave,
    'absent'     => $sum_absent,
    'terminated' => $terminated_count,
    'suspended'  => $suspended_count,
    'days'       => $days_count
];

$summary = [
    'total_employees' => (int)$total_employees,
    'present' => (int)($present_total),
    'late' => (int)($late_total),
    'absent' => (int)($absent_total),
    'onleave' => (int)($onleave_total),
    'terminated' => (int)$terminated_count,
    'suspended'  => (int)$suspended_count,
    'overtime_hours' => $overtime_hours,
    'attendance_rate' => $attendance_rate,
];

echo json_encode([
    'success' => true,
    'meta' => [ 'branch' => $branch, 'from' => $from, 'to' => $to, 'cutoff' => $cutoff, 'roster_source' => $roster_source ],
    'summary' => $summary,
    'trend' => $trend,
    'breakdown' => $breakdown,
    'recent_logs' => $recent,
    'all_logs' => $all,
]);
exit;
