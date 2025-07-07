<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $useful_to = $_POST['useful_to'];
    
    $query = "UPDATE content SET title = ?, description = ?, useful_to = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sssi", $title, $description, $useful_to, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        header('Location: admin_page.php?edit=success');
    } else {
        header('Location: admin_page.php?edit=error');
    }
    exit();
}

// If GET request, fetch content details for editing
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $query = "SELECT * FROM content WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $content = mysqli_fetch_assoc($result);
    
    if (!$content) {
        header('Location: admin_page.php');
        exit();
    }
} else {
    header('Location: admin_page.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Content</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Edit Content</h2>
        <form action="edit_content.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($content['id']); ?>">
            
            <div class="mb-3">
                <label for="title" class="form-label">Title</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($content['title']); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($content['description']); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="useful_to" class="form-label">Useful To</label>
                <input type="text" class="form-control" id="useful_to" name="useful_to" value="<?php echo htmlspecialchars($content['useful_to']); ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">Update Content</button>
            <a href="admin_page.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
