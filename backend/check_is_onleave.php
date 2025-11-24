<?php
require_once __DIR__ . '/employee_db.php';
$res = $conn->query("SHOW COLUMNS FROM attendance_logs LIKE 'is_onleave'");
if ($res && $res->num_rows > 0) {
    echo "is_onleave column exists\n";
    $r = $res->fetch_assoc(); print_r($r);
} else {
    echo "is_onleave missing\n";
}
