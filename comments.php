<?php
session_start();
require_once 'config.php';

$contentId = isset($_GET['content_id']) ? intval($_GET['content_id']) : 0;

// Handle AJAX comment submission
if (isset($_POST['add_comment']) && isset($_SESSION['user_id'])) {
	$comment = trim($_POST['comment']);
	$userId = $_SESSION['user_id'];

	if (!empty($comment)) {
		$sql = "INSERT INTO comments (content_id, user_id, comment) VALUES (?, ?, ?)";
		$stmt = mysqli_prepare($conn, $sql);
		mysqli_stmt_bind_param($stmt, "iis", $contentId, $userId, $comment);

		$response = array(
			'success' => mysqli_stmt_execute($stmt),
			'message' => mysqli_stmt_execute($stmt) ? 'Comment added successfully.' : 'Error adding comment.'
		);

		header('Content-Type: application/json');
		echo json_encode($response);
		exit;
	}
}

// Get comments for this content
$sql = "SELECT c.*, u.username 
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.content_id = ? 
        ORDER BY c.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $contentId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get content title
$titleSql = "SELECT title FROM content WHERE id = ?";
$titleStmt = mysqli_prepare($conn, $titleSql);
mysqli_stmt_bind_param($titleStmt, "i", $contentId);
mysqli_stmt_execute($titleStmt);
$titleResult = mysqli_stmt_get_result($titleStmt);
$contentTitle = mysqli_fetch_assoc($titleResult)['title'] ?? 'Content';
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<title>Comments - <?php echo htmlspecialchars($contentTitle); ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
	<style>
		body {
			padding: 20px;
		}

		.comment-container {
			max-height: calc(100vh - 200px);
			overflow-y: auto;
		}

		.comment-item {
			border-bottom: 1px solid #eee;
			padding: 10px 0;
		}

		.comment-form {
			position: sticky;
			bottom: 0;
			background: white;
			padding: 15px 0;
			border-top: 1px solid #eee;
		}
	</style>
</head>

<body>
	<div class="container-fluid">
		<h4 class="mb-3">Comments - <?php echo htmlspecialchars($contentTitle); ?></h4>

		<div class="comment-container mb-3">
			<?php if (mysqli_num_rows($result) > 0): ?>
				<?php while ($comment = mysqli_fetch_assoc($result)): ?>
					<div class="comment-item">
						<div class="d-flex justify-content-between">
							<strong><?php echo htmlspecialchars($comment['username']); ?></strong>
							<small class="text-muted">
								<?php echo date('F j, Y g:i a', strtotime($comment['created_at'])); ?>
							</small>
						</div>
						<p class="mb-0"><?php echo htmlspecialchars($comment['comment']); ?></p>
					</div>
				<?php endwhile; ?>
			<?php else: ?>
				<p class="text-center text-muted">No comments yet.</p>
			<?php endif; ?>
		</div>

		<?php if (isset($_SESSION['user_id'])): ?>
			<div class="comment-form">
				<form id="commentForm" class="mb-0">
					<div class="input-group">
						<input type="text" class="form-control" placeholder="Add a comment..." required name="comment">
						<button type="submit" class="btn btn-primary">
							<i class="fas fa-paper-plane"></i> Post
						</button>
					</div>
				</form>
			</div>
		<?php else: ?>
			<p class="text-center text-muted">Please <a href="login.html" target="_blank">login</a> to add comments.</p>
		<?php endif; ?>
	</div>

	<script>
		document.getElementById('commentForm')?.addEventListener('submit', function(e) {
			e.preventDefault();
			const comment = this.comment.value.trim();
			if (!comment) return;

			fetch('comments.php?content_id=<?php echo $contentId; ?>', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: `add_comment=1&comment=${encodeURIComponent(comment)}`
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						location.reload();
					} else {
						alert('Failed to add comment. Please try again.');
					}
				})
				.catch(() => {
					alert('Failed to add comment. Please try again.');
				});
		});
	</script>
</body>

</html>
}
});
});
</script>
</body>

</html>