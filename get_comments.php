<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['content_id'])) {
    echo json_encode(['error' => 'Content ID is required']);
    exit;
}

$contentId = intval($_GET['content_id']);


$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

$sql = "SELECT c.*, u.username 
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.content_id = ?";
if (!$isAdmin) {
    $sql .= " AND c.is_restricted = 0";
}
$sql .= " ORDER BY c.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $contentId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$comments = array();
while ($row = mysqli_fetch_assoc($result)) {
    $comments[] = $row;
}

// Get total count (including restricted for admin)
$countSql = "SELECT COUNT(*) as total FROM comments WHERE content_id = ?";
if (!$isAdmin) {
    $countSql .= " AND is_restricted = 0";
}
$countStmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($countStmt, "i", $contentId);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalComments = mysqli_fetch_assoc($countResult)['total'];

echo json_encode([
    'comments' => $comments,
    'total' => $totalComments
]); 
