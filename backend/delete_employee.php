<?php
include 'employee_db.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Start transaction for data consistency
    $conn->begin_transaction();
    
    try {
        // Get employee data to delete image
        $stmt = $conn->prepare("SELECT profile_img FROM employees WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();
        $stmt->close();
        
        // Delete employee
        $stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        // Delete image file if exists
        if ($employee && $employee['profile_img'] && file_exists("../uploads/" . $employee['profile_img'])) {
            unlink("../uploads/" . $employee['profile_img']);
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Employee deleted successfully']);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
}

$conn->close();
?>