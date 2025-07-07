<?php
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Validate admin code if role is admin
    if ($role === 'admin') {
        $adminCode = $_POST['adminCode'];
        if ($adminCode !== 'admin@123') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'field' => 'adminCode',
                'message' => 'Invalid admin code'
            ]);
            exit();
        }
    }
    
    // Check if username exists
    $sql = "SELECT id FROM users WHERE username = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'field' => 'username',
                    'message' => 'This username is already taken'
                ]);
                exit();
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Insert new user
    $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        mysqli_stmt_bind_param($stmt, "sss", $username, $hashed_password, $role);
        
        if (mysqli_stmt_execute($stmt)) {
            // Start session and set session variables
            session_start();
            $_SESSION["loggedin"] = true;
            $_SESSION["user_id"] = mysqli_insert_id($conn);
            $_SESSION["username"] = $username;
            $_SESSION["role"] = $role;
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Registration successful',
                'role' => $role,
                'redirect' => $role === 'admin' ? 'admin_page.php' : 'index.php'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Something went wrong. Please try again later.'
            ]);
        }
        
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($conn);
?> 
