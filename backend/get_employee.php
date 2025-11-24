<?php
// More robust get_employee with debug-friendly messages
session_start();
include 'employee_db.php';

header('Content-Type: application/json');

$debug = isset($_GET['debug']) && $_GET['debug'];

// Helper to output and exit
function out($arr) {
    echo json_encode($arr);
    exit;
}

if (!isset($_GET['id'])) {
    out(['success' => false, 'message' => 'No ID provided', 'received' => $_GET]);
}

$idRaw = $_GET['id'];

// If numeric, treat as primary `id`, else try to lookup by `emp_id`
if (is_numeric($idRaw)) {
    $id = (int)$idRaw;
    $sql = "SELECT * FROM employees WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) out(['success' => false, 'message' => 'Prepare failed', 'error' => $conn->error]);
    $stmt->bind_param('i', $id);
} else {
    $empId = $idRaw;
    $sql = "SELECT * FROM employees WHERE emp_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) out(['success' => false, 'message' => 'Prepare failed (emp_id)', 'error' => $conn->error]);
    $stmt->bind_param('s', $empId);
}

if (!$stmt->execute()) {
    out(['success' => false, 'message' => 'Execute failed', 'error' => $stmt->error]);
}

$result = $stmt->get_result();
if (!$result) out(['success' => false, 'message' => 'Get result failed', 'error' => $conn->error]);

if ($result->num_rows === 0) {
    out(['success' => false, 'message' => 'Employee not found', 'queried' => $idRaw]);
}

$employee = $result->fetch_assoc();

// If auditor_name missing for account_executive, attempt to enrich from other employees
if ((empty($employee['auditor_name']) || trim($employee['auditor_name']) === '') && (empty($employee['role']) || $employee['role'] === 'account_executive')) {
    $auditorName = null;
    $tryCols = ['branch_name', 'branch_city', 'branch_location'];
    foreach ($tryCols as $col) {
        if (!empty($employee[$col])) {
            $sqlA = "SELECT full_name FROM employees WHERE role='auditor' AND `".$col."` = ? ORDER BY id DESC LIMIT 1";
            $stmtA = $conn->prepare($sqlA);
            if ($stmtA) {
                $stmtA->bind_param('s', $employee[$col]);
                if ($stmtA->execute()) {
                    $ra = $stmtA->get_result();
                    if ($ra && ($rowA = $ra->fetch_assoc())) { $auditorName = $rowA['full_name']; }
                }
                $stmtA->close();
            }
        }
        if ($auditorName) break;
    }
    if ($auditorName) { $employee['auditor_name'] = $auditorName; }
}

out(['success' => true, 'employee' => $employee]);

$stmt->close();
$conn->close();
?>