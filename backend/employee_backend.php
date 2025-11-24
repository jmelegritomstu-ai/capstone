<?php
include 'employee_db.php';

// INSERT Employee
if (isset($_POST['save'])) {
    // Core fields
    $emp_id = $_POST['emp_id'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $position = $_POST['position'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $email = $_POST['email'] ?? '';
    $date_hired = $_POST['date_hired'] ?? null;
    $branch_name = $_POST['branch_name'] ?? '';
    $branch_location = $_POST['branch_location'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    // Newly added extended fields
    $username = $_POST['username'] ?? null;
    $role = $_POST['role'] ?? 'account_executive';
    $gender = $_POST['gender'] ?? null;
    $birthday = $_POST['birthday'] ?? null;
    $branch_city = $_POST['branch_city'] ?? null;
    $contract_type = $_POST['contract_type'] ?? null;
    $shift_schedule = $_POST['shift_schedule'] ?? null;
    $auditor_name = $_POST['auditor_name'] ?? null;
    $address = $_POST['address'] ?? null;
    // Dayoff weekday (1=Mon .. 6=Sat). Optional.
    $dayoff_weekday = isset($_POST['dayoff_weekday']) && $_POST['dayoff_weekday'] !== '' ? (int)$_POST['dayoff_weekday'] : null;

    // ✅ Ensure uploads folder exists
    $uploadDir = "../uploads/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // ✅ Handle profile image upload
    $profile_img = null;
    if (!empty($_FILES['profile_img']['name'])) {
        $imageName = time() . "_" . basename($_FILES['profile_img']['name']);
        $targetPath = $uploadDir . $imageName;
        if (move_uploaded_file($_FILES['profile_img']['tmp_name'], $targetPath)) {
            $profile_img = $imageName;
        } else {
            echo "<script>alert('Image upload failed. Check permissions.'); window.history.back();</script>";
            exit;
        }
    }

    // ✅ Check if employee ID already exists
    $check_stmt = $conn->prepare("SELECT id FROM employees WHERE emp_id = ?");
    $check_stmt->bind_param("s", $emp_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo "<script>alert('Employee ID already exists! Please use a different ID.'); window.history.back();</script>";
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();

    // Ensure new columns exist (idempotent) - safe guards if schema not yet migrated
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS username VARCHAR(100) NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS role ENUM('auditor','account_executive') DEFAULT 'account_executive'");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS gender VARCHAR(50) NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS birthday DATE NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS branch_city VARCHAR(100) NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS contract_type VARCHAR(100) NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS shift_schedule VARCHAR(100) NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS auditor_name VARCHAR(150) NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS address TEXT NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS dayoff_weekday TINYINT NULL COMMENT '1=Mon .. 6=Sat; preferred rest day for half-month scheduling'");

    // ✅ Prepare and insert with extended fields (position is provided at creation)
    $stmt = $conn->prepare("INSERT INTO employees (emp_id, full_name, position, contact_number, email, date_hired, branch_name, branch_location, status, profile_img, username, role, gender, birthday, branch_city, contract_type, shift_schedule, auditor_name, address, dayoff_weekday) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssssssssssssssssssss", $emp_id, $full_name, $position, $contact_number, $email, $date_hired, $branch_name, $branch_location, $status, $profile_img, $username, $role, $gender, $birthday, $branch_city, $contract_type, $shift_schedule, $auditor_name, $address, $dayoff_weekday);

    if ($stmt->execute()) {
        echo "<script>alert('Employee added successfully!'); window.location='../admin/employees.php';</script>";
    } else {
        echo "<script>alert('Error inserting data: " . addslashes($stmt->error) . "'); window.history.back();</script>";
    }

    $stmt->close();
}

// UPDATE Employee
if (isset($_POST['update'])) {
    $id = $_POST['employee_id'];
    $emp_id = $_POST['emp_id'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    // Position should NOT be editable (ignore incoming position change)
    $position = null; // We'll keep existing value from DB
    $contact_number = $_POST['contact_number'] ?? '';
    $email = $_POST['email'] ?? '';
    $date_hired = $_POST['date_hired'] ?? null;
    $branch_name = $_POST['branch_name'] ?? '';
    $branch_location = $_POST['branch_location'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $current_image = $_POST['current_image'] ?? '';
    // Extended fields
    $username = $_POST['username'] ?? null;
    $role = $_POST['role'] ?? 'account_executive';
    $gender = $_POST['gender'] ?? null;
    $birthday = $_POST['birthday'] ?? null;
    $branch_city = $_POST['branch_city'] ?? null;
    $contract_type = $_POST['contract_type'] ?? null;
    $shift_schedule = $_POST['shift_schedule'] ?? null;
    $auditor_name = $_POST['auditor_name'] ?? null;
    $address = $_POST['address'] ?? null;
    $dayoff_weekday = isset($_POST['dayoff_weekday']) && $_POST['dayoff_weekday'] !== '' ? (int)$_POST['dayoff_weekday'] : null;

    // ✅ Check if employee ID already exists (excluding current employee)
    $check_stmt = $conn->prepare("SELECT id FROM employees WHERE emp_id = ? AND id != ?");
    $check_stmt->bind_param("si", $emp_id, $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo "<script>alert('Employee ID already exists! Please use a different ID.'); window.history.back();</script>";
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();

    // Handle image upload
    $profile_img = $current_image;
    if (!empty($_FILES['profile_img']['name'])) {
        $uploadDir = "../uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $imageName = time() . "_" . basename($_FILES['profile_img']['name']);
        $targetPath = $uploadDir . $imageName;
        
        if (move_uploaded_file($_FILES['profile_img']['tmp_name'], $targetPath)) {
            // Delete old image if exists and is different from new one
            if ($current_image && file_exists($uploadDir . $current_image) && $current_image != $imageName) {
                unlink($uploadDir . $current_image);
            }
            $profile_img = $imageName;
        } else {
            echo "<script>alert('Image upload failed. Check permissions.'); window.history.back();</script>";
            exit;
        }
    }

    // Fetch current position to preserve it
    $pos_stmt = $conn->prepare("SELECT position FROM employees WHERE id=?");
    $pos_stmt->bind_param("i", $id);
    $pos_stmt->execute();
    $pos_res = $pos_stmt->get_result();
    $existing = $pos_res->fetch_assoc();
    $pos_stmt->close();
    $position_keep = $existing ? $existing['position'] : '';

    // Ensure columns exist (in case UPDATE occurs before INSERT since add modal might not be used)
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS username VARCHAR(100) NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS role ENUM('auditor','account_executive') DEFAULT 'account_executive'");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS gender VARCHAR(50) NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS birthday DATE NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS branch_city VARCHAR(100) NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS contract_type VARCHAR(100) NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS shift_schedule VARCHAR(100) NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS auditor_name VARCHAR(150) NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS address TEXT NULL");
    $conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS dayoff_weekday TINYINT NULL COMMENT '1=Mon .. 6=Sat; preferred rest day for half-month scheduling'");

    $stmt = $conn->prepare("UPDATE employees SET emp_id=?, full_name=?, position=?, contact_number=?, email=?, date_hired=?, branch_name=?, branch_location=?, status=?, profile_img=?, username=?, role=?, gender=?, birthday=?, branch_city=?, contract_type=?, shift_schedule=?, auditor_name=?, address=?, dayoff_weekday=? WHERE id=?");
    
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("sssssssssssssssssssii", $emp_id, $full_name, $position_keep, $contact_number, $email, $date_hired, $branch_name, $branch_location, $status, $profile_img, $username, $role, $gender, $birthday, $branch_city, $contract_type, $shift_schedule, $auditor_name, $address, $dayoff_weekday, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Employee updated successfully!'); window.location='../admin/employees.php';</script>";
    } else {
        echo "<script>alert('Error updating data: " . addslashes($stmt->error) . "'); window.history.back();</script>";
    }

    $stmt->close();
}

$conn->close();
?>