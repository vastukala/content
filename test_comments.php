<?php
session_start();
require_once 'config.php';

// Simple test script to check if comments are working
echo "<h2>Comment System Test</h2>";

// Check if comments table exists and has is_restricted column
$result = mysqli_query($conn, "DESCRIBE comments");
if ($result) {
    echo "<h3>Comments table structure:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Error: Comments table not found!</p>";
}

// Test comment fetching
echo "<h3>Testing comment fetch for content_id = 1:</h3>";
$testResult = mysqli_query($conn, "SELECT c.*, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.content_id = 1 LIMIT 5");
if ($testResult && mysqli_num_rows($testResult) > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Username</th><th>Comment</th><th>Restricted</th><th>Created</th></tr>";
    while ($row = mysqli_fetch_assoc($testResult)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['username'] . "</td>";
        echo "<td>" . htmlspecialchars($row['comment']) . "</td>";
        echo "<td>" . (isset($row['is_restricted']) ? ($row['is_restricted'] ? 'Yes' : 'No') : 'Column missing') . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No comments found for content_id = 1</p>";
}

// Test get_comments.php endpoint
echo "<h3>Testing get_comments.php endpoint:</h3>";
echo "<p><a href='get_comments.php?content_id=1' target='_blank'>Test get_comments.php?content_id=1</a></p>";

mysqli_close($conn);
?>
