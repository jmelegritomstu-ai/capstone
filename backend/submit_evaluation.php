<?php
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/employee_db.php'; // provides $conn (mysqli)
// Load session helpers to resolve current evaluator from session
require_once __DIR__ . '/session_user.php';

// Resolve evaluator name from session (authoritative)
$me = session_employee($conn);
$evaluator_from_session = $me['full_name'] ?? ($_SESSION['fullname'] ?? $_SESSION['username'] ?? '');


if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not initialized']);
    exit;
}

// Helper to fetch POST values safely
function post($key, $default = null) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// Required fields
$employee_id     = post('employee_id');
$evaluator_name  = $evaluator_from_session;
$evaluation_date = post('evaluation_date');

if ($employee_id === null || $employee_id === '' || $evaluation_date === null || $evaluation_date === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields (employee, date).']);
    exit;
}

// Ensure evaluator exists in session
if (empty(trim((string)$evaluator_name))) {
    echo json_encode(['success' => false, 'message' => 'Evaluator not identified from your session. Please login with a linked employee account.']);
    exit;
}

// Optional fields
$branch_name       = post('branch_name');
$branch_location   = post('branch_location');
$total_sales       = (float) post('total_sales', 0);
$company_quota     = (float) post('company_quota', 0);
$daily_sales       = (float) post('daily_sales', 0);
$sales_gap         = (float) post('sales_gap', 0);
$vault_quota       = (float) post('vault_quota', 0);
$vault_collected   = (float) post('vault_collected', 0);
$quality_percentage= (float) post('quality_percentage', 0);
$evaluator_notes   = post('evaluator_notes');
$total_units_sent  = (int) post('total_units_sent', 0);
$return_rate_auto  = (float) post('return_rate_auto', 0); // will be recalculated server-side
$absentees         = (int) post('absentees', 0);
$lates             = (int) post('lates', 0);

// Performance section (will be RE-CALCULATED server-side to ensure integrity)
$vault_percent       = 0.0;
$sales_percent       = 0.0;
$attendance_percent  = 0.0;
$return_rate_percent = 0.0;
$quality_percent     = 0.0;
$total_points_percent= 0.0;

$comments          = post('comments');

// Ensure table exists (idempotent)
$createSql = "CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) NOT NULL,
    branch_name VARCHAR(100),
    branch_location VARCHAR(150),
    total_sales DECIMAL(12,2) DEFAULT 0,
    company_quota DECIMAL(12,2) DEFAULT 0,
    daily_sales DECIMAL(12,2) DEFAULT 0,
    sales_gap DECIMAL(12,2) DEFAULT 0,
    vault_quota DECIMAL(12,2) DEFAULT 0,
    vault_collected DECIMAL(12,2) DEFAULT 0,
    quality_percentage DECIMAL(5,2) DEFAULT 0,
    evaluator_notes VARCHAR(255),
    total_units_sent INT DEFAULT 0,
    return_rate_auto DECIMAL(5,2) DEFAULT 0,
    absentees INT DEFAULT 0,
    lates INT DEFAULT 0,
    vault_percent DECIMAL(5,2) DEFAULT 0,
    sales_percent DECIMAL(5,2) DEFAULT 0,
    attendance_percent DECIMAL(5,2) DEFAULT 0,
    return_rate_percent DECIMAL(5,2) DEFAULT 0,
    quality_percent DECIMAL(5,2) DEFAULT 0,
    total_points_percent DECIMAL(6,2) DEFAULT 0,
    evaluator_name VARCHAR(100) NOT NULL,
    evaluation_date DATE NOT NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (!$conn->query($createSql)) {
    echo json_encode(['success' => false, 'message' => 'Failed to ensure evaluations table exists: ' . $conn->error]);
    exit;
}

// Recalculate performance metrics on the server (authoritative)
// Normalize raw inputs
$total_units_sent = max(0, (int)$total_units_sent);
$absentees = max(0, (int)$absentees);
$lates = max(0, (int)$lates);
$total_sales = max(0.0, (float)$total_sales);
$company_quota = max(0.0, (float)$company_quota);
$daily_sales = max(0.0, (float)$daily_sales);
$vault_quota = max(0.0, (float)$vault_quota);
$vault_collected = max(0.0, (float)$vault_collected);
$quality_percentage = max(0.0, (float)$quality_percentage);

// Recompute sales gap to trust server-side math
$sales_gap = $total_sales - $company_quota;
// 1) Sales: (total_sales / company_quota) * 20 (capped 0..20)
if ($company_quota > 0) {
    $sales_percent = ($total_sales / $company_quota) * 20.0;
}
$sales_percent = max(0.0, min(20.0, $sales_percent));

// 2) Vault: (vault_collected / vault_quota) * 20 (capped 0..20)
if ($vault_quota > 0) {
    $vault_percent = ($vault_collected / $vault_quota) * 20.0;
}
$vault_percent = max(0.0, min(20.0, $vault_percent));

// 3) Attendance: 20 − (absentees*5 + lates*2), capped 0..20
$attendance_percent = 20.0 - (($absentees * 5.0) + ($lates * 2.0));
$attendance_percent = max(0.0, min(20.0, $attendance_percent));

// 4) Quality: quality_percentage * 0.2 (capped 0..20)
$quality_percent = ($quality_percentage * 0.2);
$quality_percent = max(0.0, min(20.0, $quality_percent));

// 5) Rate of Return: 20 − units sent (capped 0..20)
$return_rate_percent = 20.0 - min(20.0, (float)$total_units_sent);
$return_rate_percent = max(0.0, min(20.0, $return_rate_percent));
// Keep return_rate_auto aligned with computed points for consistency
$return_rate_auto = $return_rate_percent;

// Total points: sum of 5 sections (0..100)
$total_points_percent = $vault_percent + $sales_percent + $attendance_percent + $quality_percent + $return_rate_percent;

// Prepare insert
$sql = "INSERT INTO evaluations (
    employee_id, branch_name, branch_location,
    total_sales, company_quota, daily_sales, sales_gap,
    vault_quota, vault_collected, quality_percentage, evaluator_notes,
    total_units_sent, return_rate_auto, absentees, lates,
    vault_percent, sales_percent, attendance_percent, return_rate_percent, quality_percent, total_points_percent,
    evaluator_name, evaluation_date, comments
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    'sssdddddddsidiiddddddsss',
    $employee_id,
    $branch_name,
    $branch_location,
    $total_sales,
    $company_quota,
    $daily_sales,
    $sales_gap,
    $vault_quota,
    $vault_collected,
    $quality_percentage,
    $evaluator_notes,
    $total_units_sent,
    $return_rate_auto,
    $absentees,
    $lates,
    $vault_percent,
    $sales_percent,
    $attendance_percent,
    $return_rate_percent,
    $quality_percent,
    $total_points_percent,
    $evaluator_name,
    $evaluation_date,
    $comments
);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$stmt->close();
// Auto-create training recommendation when total score <= 60
try {
    if ($total_points_percent <= 60.0 && $employee_id) {
        // Ensure training table exists
        $conn->query("CREATE TABLE IF NOT EXISTS training_recommendations (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Compute next Monday as schedule
        $tz = new DateTimeZone('Asia/Manila');
        $d = new DateTime('now', $tz);
        while ((int)$d->format('N') !== 1) { $d->modify('+1 day'); }
        $scheduled_date = $d->format('Y-m-d');

        $title = 'Performance Improvement Coaching';
        $desc  = 'Overall score is at or below 60. Recommended coaching covering sales execution, vault, attendance, return handling, and quality fundamentals.';
        $source = 'evaluation-auto';

        $ins = $conn->prepare("INSERT IGNORE INTO training_recommendations (emp_id, title, description, scheduled_date, status, source) VALUES (?,?,?,?, 'active', ?)");
        if ($ins) {
            $ins->bind_param('sssss', $employee_id, $title, $desc, $scheduled_date, $source);
            $ins->execute();
            $ins->close();
        }
    }
} catch (Throwable $e) {
    // Do not fail the evaluation if training creation encounters an issue
}

echo json_encode(['success' => true, 'message' => 'Evaluation saved', 'id' => $conn->insert_id]);
exit;
