<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to comment']);
    exit;
}

// Validate input
if (empty($_POST['content_id']) || empty($_POST['comment'])) {
    echo json_encode(['success' => false, 'message' => 'Content ID and comment are required']);
    exit;
}

$contentId = intval($_POST['content_id']);
$comment = trim($_POST['comment']);
$userId = $_SESSION['user_id'];

try {
    // Insert comment into database
    $stmt = $conn->prepare("INSERT INTO comments (content_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $contentId, $userId, $comment);
    
    if ($stmt->execute()) {
        // Get the inserted comment with username
        $commentId = $stmt->insert_id;
        $result = $conn->query("SELECT c.*, u.username 
                              FROM comments c 
                              JOIN users u ON c.user_id = u.id 
                              WHERE c.id = $commentId");
        $newComment = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'comment' => $newComment
        ]);
    } else {
        throw new Exception('Failed to save comment');
    }
} catch (Exception $e) {
    error_log('Error adding comment: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while adding your comment. Please try again.'
    ]);
}

$conn->close();
?>
