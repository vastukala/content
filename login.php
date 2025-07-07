<?php
session_start();
require_once "config.php";

// Set header to return JSON
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message, $role = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'role' => $role
    ]);
    exit();
}

// If already logged in, return appropriate response
if (isset($_SESSION['user_id'])) {
    sendJsonResponse(true, 'Already logged in', $_SESSION['role']);
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

// Validate input
if (!isset($_POST['username']) || !isset($_POST['password'])) {
    sendJsonResponse(false, 'Username and password are required');
}

$username = $_POST['username'];
$password = $_POST['password'];

// Query to check user credentials
$query = "SELECT id, username, password, role FROM users WHERE username = ?";
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
    sendJsonResponse(false, 'Database error');
}

mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($user = mysqli_fetch_assoc($result)) {
    if (password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        sendJsonResponse(true, 'Login successful', $user['role']);
    }
}

// If we get here, login failed
sendJsonResponse(false, 'Invalid username or password');
?> 
 