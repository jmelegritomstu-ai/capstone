<?php
require_once __DIR__ . '/backend/employee_db.php';
$res = $conn->query("SELECT emp_id, status, full_name FROM employees WHERE LOWER(IFNULL(status,'')) LIKE '%on leave%'");
if (!$res) { echo "Query failed: " . $conn->error . "\n"; exit; }
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
echo json_encode($rows, JSON_PRETTY_PRINT) . "\n";
