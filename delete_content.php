<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

require_once "config.php";

if (isset($_GET['id'])) {
    $contentId = $_GET['id'];
    $adminId = $_SESSION['user_id'];
    
    // First, get the file path and verify ownership
    $sql = "SELECT file_path FROM content WHERE id = ? AND admin_id = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $contentId, $adminId);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($result)) {
                $filePath = $row['file_path'];
                
                // Delete the file from the server
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Delete the database record
                $deleteSql = "DELETE FROM content WHERE id = ? AND admin_id = ?";
                
                if ($deleteStmt = mysqli_prepare($conn, $deleteSql)) {
                    mysqli_stmt_bind_param($deleteStmt, "ii", $contentId, $adminId);
                    
                    if (mysqli_stmt_execute($deleteStmt)) {
                        $_SESSION['message'] = "Content deleted successfully.";
                        $_SESSION['message_type'] = "success";
                    } else {
                        $_SESSION['message'] = "Error deleting content from database.";
                        $_SESSION['message_type'] = "danger";
                    }
                    
                    mysqli_stmt_close($deleteStmt);
                }
            } else {
                $_SESSION['message'] = "Content not found or you don't have permission to delete it.";
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Error executing query.";
            $_SESSION['message_type'] = "danger";
        }
        
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['message'] = "Error preparing query.";
        $_SESSION['message_type'] = "danger";
    }
} else {
    $_SESSION['message'] = "No content ID specified.";
    $_SESSION['message_type'] = "danger";
}

// Redirect back to admin page
header('Location: admin_page.php');
exit();
?> 
