<?php
// Centralized session + user/employee lookup helpers
session_start();
require_once __DIR__ . '/employee_db.php';

date_default_timezone_set('Asia/Manila');

// Basic guards
function require_auth() {
    global $conn; // Add this line to access the database connection
    
    if (!isset($_SESSION['username'])) {
        header('Location: ../user/login.php');
        exit;
    }
    // Prevent access if employee status disallows login (Terminated or Suspended)
    $emp = session_employee($conn);
    if ($emp && isset($emp['status'])) {
        $s = strtolower(trim((string)$emp['status']));
        if ($s === 'terminated' || $s === 'suspended') {
            session_unset(); session_destroy();
            header('Location: ../user/login.php');
            exit;
        }
    }
}

function require_role($role) {
    require_auth();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        // Default: send account executives to their attendance, auditors to dashboard
        $dest = ($role === 'account_executive') ? '../user/attendance.php' : '../admin/dashboard.php';
        header("Location: $dest");
        exit;
    }
}

// Cached per-request
$__SESSION_EMP_CACHE = null;

// Attempt to enrich from employees table when linked
function session_employee(mysqli $conn) {
    global $__SESSION_EMP_CACHE;
    if ($__SESSION_EMP_CACHE !== null) return $__SESSION_EMP_CACHE;
    if (!isset($_SESSION['username'])) return null;
    $username = $_SESSION['username'];

    // Check if connection is valid
    if (!$conn || !($conn instanceof mysqli)) {
        error_log("Database connection is invalid in session_employee()");
        return null;
    }

    // Single source: employees table — fetch full row to avoid missing fields on profile
    // Note: Using explicit column list is recommended; SELECT * here intentionally
    // simplifies maintenance across pages that read many optional fields.
    // Primary lookup by username
    $stmt = $conn->prepare("SELECT * FROM employees WHERE username = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $username);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $emp = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($emp) {
                // Fallback enrichment for missing auditor_name (derive by branch auditor)
                $sessionRole = $_SESSION['role'] ?? '';
                $isAE = (($emp['role'] ?? '') === 'account_executive') || ($sessionRole === 'account_executive');
                if ((empty($emp['auditor_name']) || trim((string)$emp['auditor_name']) === '') && $isAE) {
                    // Try match by branch_name, then branch_city, then branch_location
                    $audName = null;
                    $branchName = $emp['branch_name'] ?? null;
                    $branchCity = $emp['branch_city'] ?? null;
                    $branchLoc  = $emp['branch_location'] ?? null;

                    // Helper closure to attempt a single criterion
                    $tryFetch = function($column, $value) use (&$audName, $conn) {
                        if (!$value || $audName) return; // skip if no value or already found
                        $sql = "SELECT full_name FROM employees WHERE role='auditor' AND $column = ? ORDER BY id DESC LIMIT 1";
                        $stmtA = $conn->prepare($sql);
                        if ($stmtA) {
                            $stmtA->bind_param('s', $value);
                            if ($stmtA->execute()) {
                                $rA = $stmtA->get_result();
                                if ($rA && ($rowA = $rA->fetch_assoc())) { $audName = $rowA['full_name']; }
                            }
                            $stmtA->close();
                        }
                    };

                    $tryFetch('branch_name', $branchName);
                    $tryFetch('branch_city', $branchCity);
                    $tryFetch('branch_location', $branchLoc);

                    if ($audName) { $emp['auditor_name'] = $audName; }
                }
                $__SESSION_EMP_CACHE = $emp; return $__SESSION_EMP_CACHE; }
        } else { $stmt->close(); }
    }

    // Fallback: if employee_id stored in session (legacy), use emp_id
    if (isset($_SESSION['employee_id']) && $_SESSION['employee_id']) {
        $empId = $_SESSION['employee_id'];
        $stmt2 = $conn->prepare("SELECT * FROM employees WHERE emp_id = ? LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param('s', $empId);
            if ($stmt2->execute()) {
                $res2 = $stmt2->get_result();
                $emp2 = $res2 ? $res2->fetch_assoc() : null;
                $stmt2->close();
                if ($emp2) {
                    $sessionRole = $_SESSION['role'] ?? '';
                    $isAE = (($emp2['role'] ?? '') === 'account_executive') || ($sessionRole === 'account_executive');
                    if ((empty($emp2['auditor_name']) || trim((string)$emp2['auditor_name']) === '') && $isAE) {
                        $audName = null;
                        $branchName = $emp2['branch_name'] ?? null;
                        $branchCity = $emp2['branch_city'] ?? null;
                        $branchLoc  = $emp2['branch_location'] ?? null;
                        $tryFetch = function($column, $value) use (&$audName, $conn) {
                            if (!$value || $audName) return;
                            $sql = "SELECT full_name FROM employees WHERE role='auditor' AND $column = ? ORDER BY id DESC LIMIT 1";
                            $stmtA = $conn->prepare($sql);
                            if ($stmtA) {
                                $stmtA->bind_param('s', $value);
                                if ($stmtA->execute()) {
                                    $rA = $stmtA->get_result();
                                    if ($rA && ($rowA = $rA->fetch_assoc())) { $audName = $rowA['full_name']; }
                                }
                                $stmtA->close();
                            }
                        };
                        $tryFetch('branch_name', $branchName);
                        $tryFetch('branch_city', $branchCity);
                        $tryFetch('branch_location', $branchLoc);
                        if ($audName) { $emp2['auditor_name'] = $audName; }
                    }
                    $__SESSION_EMP_CACHE = $emp2; return $__SESSION_EMP_CACHE; }
            } else { $stmt2->close(); }
        }
    }
    return null;
}

?>