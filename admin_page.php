<?php
// Set PHP configuration for file uploads
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');

session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
	header('Location: login.html');
	exit();
}

require_once "config.php";

// Initialize variables
$title = '';
$description = '';
$fileType = '';
$usefulTo = '';
$tags = '';
$price = 1.00;
$errors = array();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	// Check if either file was uploaded or YouTube URL was provided for video type
	if ($_POST['file_type'] === 'video' && empty($_FILES["file"]["name"]) && empty(trim($_POST['youtube_url'] ?? ''))) {
		$errors[] = "Please either upload a video file or provide a YouTube URL.";
	} elseif ($_POST['file_type'] !== 'video' && empty($_FILES["file"]["name"])) {
		$errors[] = "Please select a file to upload.";
	}
	$title = trim($_POST["title"]);
	$description = trim($_POST["description"]);
	$fileType = $_POST["file_type"];
	$usefulTo = isset($_POST["useful_to"]) ? implode(",", $_POST["useful_to"]) : '';
	$tags = trim($_POST["tags"]);
	$price = floatval($_POST["price"]) > 0 ? floatval($_POST["price"]) : 1.00;
	$isRestricted = isset($_POST['is_restricted']) ? 1 : 0;
	$viewRestricted = isset($_POST['view_restricted']) ? 1 : 0;
	$adminId = $_SESSION["user_id"];
	$username = $_SESSION["username"];

	// Initialize error array
	$errors = array();

	// Validate required fields
	if (empty($title)) {
		$errors[] = "Title is required";
	}
	if (empty($description)) {
		$errors[] = "Description is required";
	}
	if (empty($fileType)) {
		$errors[] = "File type is required";
	}
	if (empty($usefulTo)) {
		$errors[] = "Please select at least one option for 'Useful To'";
	}

	// Clean username for file naming (remove special characters)
	$cleanUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);

	$targetDir = "uploads/" . $fileType . "/";
	$fileName = basename($_FILES["file"]["name"]);
	$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
	$fileNameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);

	// Create new filename with username and timestamp
	$newFileName = $fileNameWithoutExt . '_by_' . $cleanUsername . '_' . time() . '.' . $fileExtension;
	$targetFilePath = $targetDir . $newFileName;

	// Create directory if it doesn't exist
	if (!file_exists($targetDir)) {
		mkdir($targetDir, 0777, true);
	}

	// Allow certain file formats
	$allowedTypes = array(
		'pdf' => array('pdf'),
		'video' => array('mp4', 'avi', 'mov'),
		'audio' => array('mp3', 'wav'),
		'ppt' => array('ppt', 'pptx'),
		'doc' => array('doc', 'docx'),
		'excel' => array('xls', 'xlsx'),
		'image' => array('jpg', 'jpeg', 'png', 'gif')
	);

	// File size limits (in bytes)
	$maxSizes = array(
		'pdf' => 10 * 1024 * 1024,      // 10MB
		'video' => 100 * 1024 * 1024,    // 100MB
		'audio' => 50 * 1024 * 1024,     // 50MB
		'ppt' => 20 * 1024 * 1024,       // 20MB
		'doc' => 10 * 1024 * 1024,       // 10MB
		'excel' => 10 * 1024 * 1024,     // 10MB
		'image' => 5 * 1024 * 1024       // 5MB
	);

	$uploadOk = true;
	$isYoutube = ($fileType === 'video' && !empty(trim($_POST['youtube_url'] ?? '')));

	if ($isYoutube) {
		$youtubeUrl = trim($_POST['youtube_url']);
		// Basic YouTube URL validation
		if (!preg_match('/(youtube\.com\/watch\?v=|youtu\.be\/)[^\s&\/\?]+/i', $youtubeUrl)) {
			$errors[] = "Please enter a valid YouTube URL.";
			$uploadOk = false;
		} else {
			// Extract video ID from URL
			preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^\s&\/\?]+)/i', $youtubeUrl, $matches);
			$youtubeId = $matches[1] ?? '';
			if (empty($youtubeId)) {
				$errors[] = "Could not extract YouTube video ID from URL.";
				$uploadOk = false;
			} else {
				// Store the full YouTube URL in file_path
				$targetFilePath = "https://www.youtube.com/watch?v=" . $youtubeId;
			}
		}
	} else {
		// Handle file upload for non-YouTube content
		if (!isset($_FILES["file"]) || $_FILES["file"]["error"] === UPLOAD_ERR_NO_FILE) {
			$errors[] = "No file was uploaded.";
			$uploadOk = false;
		} else {
			// Check file size
			if ($_FILES["file"]["size"] > $maxSizes[$fileType]) {
				$maxSizeMB = $maxSizes[$fileType] / (1024 * 1024);
				$errors[] = "File is too large. Maximum size for {$fileType} files is {$maxSizeMB}MB.";
				$uploadOk = false;
			}

			// Validate file type
			if (!isset($allowedTypes[$fileType]) || !in_array($fileExtension, $allowedTypes[$fileType])) {
				$errors[] = "Sorry, only " . implode(", ", $allowedTypes[$fileType]) . " files are allowed for " . $fileType . " type.";
				$uploadOk = false;
			}

			// Check if file with same name exists (excluding timestamp)
			$baseNamePattern = $fileNameWithoutExt . '_by_' . $cleanUsername . '_';
			$existingFiles = glob($targetDir . $baseNamePattern . "*." . $fileExtension);

			if (count($existingFiles) > 0) {
				// Add version number to filename
				$version = count($existingFiles) + 1;
				$newFileName = $fileNameWithoutExt . '_by_' . $cleanUsername . '_v' . $version . '_' . time() . '.' . $fileExtension;
				$targetFilePath = $targetDir . $newFileName;
			}
		}
	}

	if ($uploadOk && empty($errors)) {
		$fileUploaded = true;
		if (!$isYoutube) {
			// Only move uploaded file if it's not a YouTube URL
			if (!move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
				$errors[] = "Sorry, there was an error uploading your file.";
				$fileUploaded = false;
			}
		}

		if ($fileUploaded) {
			// Conversion logic - DISABLED for Excel, DOC, PPT files
			$convertedFilePath = null;
			$convertedDir = 'converted/pdf/';
			if (!file_exists($convertedDir)) {
				mkdir($convertedDir, 0777, true);
			}
			$convertedFileName = $fileNameWithoutExt . '_by_' . $cleanUsername . '_' . time() . '.pdf';
			$convertedFullPath = $convertedDir . $convertedFileName;
			// Disable conversion for Excel, DOC, PPT files as requested
			$shouldConvert = false; // in_array($fileExtension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);
			if ($shouldConvert) {
				// Use LibreOffice to convert to PDF
				$cmd = "soffice --headless --convert-to pdf --outdir " . escapeshellarg($convertedDir) . " " . escapeshellarg($targetFilePath);
				exec($cmd, $output, $resultCode);
				// Find the converted file (LibreOffice uses the original base name)
				$originalBaseName = pathinfo($fileName, PATHINFO_FILENAME);
				$expectedPdf = $convertedDir . $originalBaseName . '.pdf';
				if (file_exists($expectedPdf)) {
					// Rename to our naming convention
					if (rename($expectedPdf, $convertedFullPath)) {
						$convertedFilePath = $convertedFullPath;
					} else {
						$errors[] = "File uploaded, but failed to rename converted PDF.";
					}
				} elseif (file_exists($convertedFullPath)) {
					$convertedFilePath = $convertedFullPath;
				} else {
					$errors[] = "File uploaded, but conversion to PDF failed. Command output: " . implode("\n", $output);
				}
			} elseif ($fileExtension === 'pdf') {
				// For PDFs, just copy to converted/pdf/
				if (copy($targetFilePath, $convertedFullPath)) {
					$convertedFilePath = $convertedFullPath;
				} else {
					$errors[] = "File uploaded, but failed to copy PDF for preview.";
				}
			}
			// Determine video type (file or youtube)
			$videoType = ($isYoutube) ? 'youtube' : 'file';

			// Insert file information into database (now with video_type and converted_file_path)
			$sql = "INSERT INTO content (title, description, file_type, video_type, file_path, useful_to, tags, admin_id, price, is_restricted, view_restricted, converted_file_path) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
			if ($stmt = mysqli_prepare($conn, $sql)) {
				mysqli_stmt_bind_param($stmt, "sssssssiiiis", $title, $description, $fileType, $videoType, $targetFilePath, $usefulTo, $tags, $adminId, $price, $isRestricted, $viewRestricted, $convertedFilePath);
				if (mysqli_stmt_execute($stmt)) {
					$_SESSION['message'] = empty($errors) ? "The file has been uploaded successfully." : join(' ', $errors);
					$_SESSION['message_type'] = empty($errors) ? "success" : "warning";
					header("Location: " . $_SERVER['PHP_SELF']);
					exit();
				} else {
					$errors[] = "Database Error: " . mysqli_error($conn);
				}
				mysqli_stmt_close($stmt);
			} else {
				$errors[] = "Database Error: " . mysqli_error($conn);
			}
		} else {
			$errors[] = "Sorry, there was an error uploading your file.";
		}
	}
}

// Handle comment restriction/unrestriction
if (isset($_POST['restrict_comment'])) {
	$commentId = intval($_POST['comment_id']);
	$restrict = intval($_POST['restrict']); // 1 to restrict, 0 to unrestrict
	$sql = "UPDATE comments SET is_restricted = ? WHERE id = ? AND content_id IN (SELECT id FROM content WHERE admin_id = ? )";
	$stmt = mysqli_prepare($conn, $sql);
	mysqli_stmt_bind_param($stmt, "iii", $restrict, $commentId, $_SESSION['user_id']);
	if (mysqli_stmt_execute($stmt)) {
		$_SESSION['message'] = $restrict ? "Comment restricted successfully." : "Comment unrestricted successfully.";
		$_SESSION['message_type'] = "success";
	} else {
		$_SESSION['message'] = "Error updating comment restriction.";
		$_SESSION['message_type'] = "danger";
	}
	header("Location: " . $_SERVER['PHP_SELF']);
	exit();
}

// Handle view restriction toggle
if (isset($_POST['toggle_view_restriction'])) {
	$contentId = intval($_POST['content_id']);
	$sql = "UPDATE content SET view_restricted = NOT view_restricted WHERE id = ? AND admin_id = ?";
	if ($stmt = mysqli_prepare($conn, $sql)) {
		mysqli_stmt_bind_param($stmt, "ii", $contentId, $_SESSION['user_id']);
		if (mysqli_stmt_execute($stmt)) {
			$_SESSION['message'] = "Content visibility updated successfully.";
			$_SESSION['message_type'] = "success";
		} else {
			$_SESSION['message'] = "Error updating content visibility.";
			$_SESSION['message_type'] = "danger";
		}
		mysqli_stmt_close($stmt);
	}
	header("Location: " . $_SERVER['PHP_SELF']);
	exit();
}

// Handle content deletion
if (isset($_POST['delete_content']) && isset($_POST['delete_content_id'])) {
	$contentId = intval($_POST['delete_content_id']);
	$adminId = $_SESSION['user_id'];

	// Get file path and converted file path
	$sql = "SELECT file_path, converted_file_path FROM content WHERE id = ? AND admin_id = ?";
	if ($stmt = mysqli_prepare($conn, $sql)) {
		mysqli_stmt_bind_param($stmt, "ii", $contentId, $adminId);
		if (mysqli_stmt_execute($stmt)) {
			$result = mysqli_stmt_get_result($stmt);
			if ($row = mysqli_fetch_assoc($result)) {
				// Delete files from server
				if (!empty($row['file_path']) && file_exists($row['file_path'])) {
					unlink($row['file_path']);
				}
				if (!empty($row['converted_file_path']) && file_exists($row['converted_file_path'])) {
					unlink($row['converted_file_path']);
				}
				// Delete from database
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
		}
		mysqli_stmt_close($stmt);
	}
	header("Location: " . $_SERVER['PHP_SELF']);
	exit();
}

// Function to get comments for a content
function getContentComments($contentId, $limit = 3)
{
	global $conn;
	$sql = "SELECT c.*, u.username 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.content_id = ? 
            ORDER BY c.created_at DESC" . ($limit ? " LIMIT " . intval($limit) : "");
	$stmt = mysqli_prepare($conn, $sql);
	mysqli_stmt_bind_param($stmt, "i", $contentId);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);
	$comments = array();
	while ($row = mysqli_fetch_assoc($result)) {
		$comments[] = $row;
	}

	// Get total comments count
	$countSql = "SELECT COUNT(*) as total FROM comments WHERE content_id = ?";
	$countStmt = mysqli_prepare($conn, $countSql);
	mysqli_stmt_bind_param($countStmt, "i", $contentId);
	mysqli_stmt_execute($countStmt);
	$countResult = mysqli_stmt_get_result($countStmt);
	$totalComments = mysqli_fetch_assoc($countResult)['total'];

	return array(
		'comments' => $comments,
		'total' => $totalComments
	);
}


// Fetch admin's uploaded content
$adminId = $_SESSION["user_id"];
$sql = "SELECT * FROM content WHERE admin_id = ? ORDER BY upload_date DESC";
$content = array();

if ($stmt = mysqli_prepare($conn, $sql)) {
	mysqli_stmt_bind_param($stmt, "i", $adminId);
	if (mysqli_stmt_execute($stmt)) {
		$result = mysqli_stmt_get_result($stmt);
		while ($row = mysqli_fetch_assoc($result)) {
			$content[] = $row;
		}
	}
	mysqli_stmt_close($stmt);
}

// Fetch pending file requests
$requestsSql = "SELECT fr.*, c.title, c.file_path, u.username 
                FROM file_requests fr 
                JOIN content c ON fr.content_id = c.id 
                JOIN users u ON fr.user_id = u.id 
                WHERE c.admin_id = ? AND fr.status = 'pending'
                ORDER BY fr.request_date DESC";
$requests = array();

if ($stmt = mysqli_prepare($conn, $requestsSql)) {
	mysqli_stmt_bind_param($stmt, "i", $adminId);
	if (mysqli_stmt_execute($stmt)) {
		$result = mysqli_stmt_get_result($stmt);
		while ($row = mysqli_fetch_assoc($result)) {
			$requests[] = $row;
		}
	}
	mysqli_stmt_close($stmt);
}

// Handle request approval
if (isset($_POST['approve_request'])) {
	$requestId = intval($_POST['request_id']);
	$updateSql = "UPDATE file_requests SET status = 'approved' WHERE id = ?";
	if ($stmt = mysqli_prepare($conn, $updateSql)) {
		mysqli_stmt_bind_param($stmt, "i", $requestId);
		if (mysqli_stmt_execute($stmt)) {
			$_SESSION['message'] = "Access granted successfully.";
			$_SESSION['message_type'] = "success";
			header("Location: " . $_SERVER['PHP_SELF']);
			exit();
		}
		mysqli_stmt_close($stmt);
	}
}

// Fetch all file requests (not just pending)
$allRequestsSql = "SELECT fr.*, c.title, c.file_path, u.username, c.price 
                FROM file_requests fr 
                JOIN content c ON fr.content_id = c.id 
                JOIN users u ON fr.user_id = u.id 
                WHERE c.admin_id = ? AND fr.status = 'pending'
                ORDER BY fr.request_date DESC";
$allRequests = array();

if ($stmt = mysqli_prepare($conn, $allRequestsSql)) {
	mysqli_stmt_bind_param($stmt, "i", $adminId);
	if (mysqli_stmt_execute($stmt)) {
		$result = mysqli_stmt_get_result($stmt);
		while ($row = mysqli_fetch_assoc($result)) {
			$allRequests[] = $row;
		}
	}
	mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
	<style>
		.content-list {
			background-color: white;
			padding: 20px;
			border-radius: 10px;
			box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
		}

		.content-item {
			border-bottom: 1px solid #dee2e6;
			padding: 15px 0;
		}

		.content-item:last-child {
			border-bottom: none;
		}

		.btn-add-content {
			margin-bottom: 20px;
		}

		.preview-container {
			margin-top: 10px;
			max-width: 100%;
			overflow: hidden;
		}

		.preview-container img {
			max-width: 200px;
			height: auto;
		}

		.preview-container video {
			max-width: 200px;
			height: auto;
		}

		.preview-container audio {
			width: 100%;
			max-width: 200px;
		}

		.file-icon {
			font-size: 2.5em;
			margin-right: 10px;
			color: #0d6efd;
		}

		.modal-xl {
			max-width: 95%;
		}

		#previewModal .modal-body {
			text-align: center;
			padding: 20px;
		}

		#previewModal img,
		#previewModal video {
			max-width: 100%;
			max-height: 80vh;
		}

		#previewModal audio {
			width: 100%;
		}

		#previewModal iframe {
			width: 100%;
			height: 80vh;
			border: none;
		}

		/* Form validation styles */
		.form-control.is-invalid,
		.form-select.is-invalid {
			border-color: #dc3545;
			padding-right: calc(1.5em + 0.75rem);
			background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
			background-repeat: no-repeat;
			background-position: right calc(0.375em + 0.1875rem) center;
			background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
		}

		.invalid-feedback {
			display: block;
			width: 100%;
			margin-top: 0.25rem;
			font-size: 0.875em;
			color: #dc3545;
		}

		.alert {
			margin-bottom: 1rem;
		}

		.alert-danger {
			color: #842029;
			background-color: #f8d7da;
			border-color: #f5c2c7;
		}

		.alert-success {
			color: #0f5132;
			background-color: #d1e7dd;
			border-color: #badbcc;
		}

		/* Style for checkbox validation */
		.form-check-input.is-invalid~.form-check-label {
			color: #dc3545;
		}

		.useful-to-error {
			color: #dc3545;
			font-size: 0.875em;
			margin-top: 0.25rem;
		}

		.table {
			margin-bottom: 0;
		}

		.badge {
			font-size: 0.9em;
			padding: 0.5em 0.7em;
		}

		.btn-sm {
			padding: 0.25rem 0.5rem;
			font-size: 0.875rem;
		}
	</style>
</head>

<body style="margin:0;padding:0;overflow-x:hidden;" class="bg-light">
    <div class="container-fluid px-3 px-md-4 px-lg-5 py-4">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<h2>Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h2>
			<a href="logout.php" class="btn btn-danger">Logout</a>
		</div>

		<!-- Action Buttons -->
		<div class="d-flex gap-2 mb-4">
			<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
				<i class="fas fa-plus"></i> Add New Content
			</button>
			<button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#requestsModal">
				<i class="fas fa-list"></i> Show All Requests
			</button>
		</div>

		<!-- Content List -->
		<div class="content-list">
			<h3 class="mb-4">Your Uploaded Content</h3>

			<?php if (isset($_SESSION['message'])): ?>
				<div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
					<?php
					echo htmlspecialchars($_SESSION['message']);
					unset($_SESSION['message']);
					unset($_SESSION['message_type']);
					?>
					<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
				</div>
			<?php endif; ?>

			<?php if (empty($content)): ?>
				<p>No content uploaded yet.</p>
			<?php else: ?>
				<?php foreach ($content as $item): ?>
					<div class="content-item">
						<div class="d-flex justify-content-between align-items-start">
							<div>
								<div class="d-flex align-items-center">
									<?php
									$fileIcon = [
										'pdf' => 'fa-file-pdf',
										'video' => 'fa-file-video',
										'audio' => 'fa-file-audio',
										'ppt' => 'fa-file-powerpoint',
										'doc' => 'fa-file-word',
										'excel' => 'fa-file-excel',
										'image' => 'fa-file-image'
									][$item['file_type']] ?? 'fa-file';
									?>
									<i class="fas <?php echo $fileIcon; ?> file-icon"></i>
									<h5 class="mb-0"><?php echo htmlspecialchars($item['title']); ?></h5>
								</div>
								<p class="text-muted mb-2">
									Type: <?php echo ucfirst($item['file_type']); ?> |
									Uploaded: <?php echo date('M d, Y', strtotime($item['upload_date'])); ?>
								</p>
								<p class="mb-2"><?php echo htmlspecialchars($item['description']); ?></p>
								<p class="mb-2">
									<small class="text-muted">
										Useful to: <?php echo str_replace(',', ', ', $item['useful_to']); ?>
									</small>
								</p>
								<?php if (!empty($item['tags'])): ?>
									<p class="mb-0">
										<small class="text-muted">
											Tags: <?php echo htmlspecialchars($item['tags']); ?>
										</small>
									</p>
								<?php endif; ?>
								<p class="mb-2">
									<span class="badge <?php echo $item['is_restricted'] ? 'bg-danger' : 'bg-success'; ?>">
										<?php echo $item['is_restricted'] ? 'Restricted' : 'Public'; ?>
									</span>
									<?php if ($item['view_restricted']): ?>
										<span class="badge bg-warning">View Restricted</span>
									<?php endif; ?>
								</p>

								<!-- Preview Container -->
								<div class="preview-container">
									<?php
									$filePath = htmlspecialchars($item['file_path']);
									switch ($item['file_type']) {
										case 'image':
											echo "<img src='{$filePath}' alt='Preview' class='img-thumbnail' onclick='showPreview(\"{$filePath}\", \"image\")'>";
											break;
										case 'video':
											echo "<video width='200' controls>
                                                    <source src='{$filePath}' type='video/mp4'>
                                                    Your browser does not support the video tag.
                                                  </video>";
											break;
										case 'audio':
											echo "<audio controls>
                                                    <source src='{$filePath}' type='audio/mpeg'>
                                                    Your browser does not support the audio tag.
                                                  </audio>";
											break;
									}
									?>
								</div>

								<!-- Comments Section -->
								<div class="comments-section mt-3">
									<h6 class="mb-2"><i class="fas fa-comments"></i> Comments</h6>
									<?php
									$commentsData = getContentComments($item['id'], 3);
									$comments = $commentsData['comments'];
									$totalComments = $commentsData['total'];
									if (!empty($comments)):
										foreach ($comments as $comment):
											// Hide restricted comments from non-admins
											if ($comment['is_restricted'] && $_SESSION['role'] !== 'admin') {
												continue;
											}
									?>
											<div class="comment-item border-bottom py-2 d-flex align-items-start">
												<div>
													<strong><?php echo htmlspecialchars($comment['username']); ?></strong>
													<small class="text-muted ms-2">
														<?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?>
													</small>
													<p class="mb-0"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
													<?php if ($comment['is_restricted']): ?>
														<span class="badge bg-warning text-dark">Restricted</span>
													<?php endif; ?>
													<?php if ($_SESSION['role'] === 'admin'): ?>
														<form method="POST" class="d-inline ms-2"
															onsubmit="return confirm('Are you sure you want to <?php echo $comment['is_restricted'] ? 'unrestrict' : 'restrict'; ?> this comment?');">
															<input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
															<input type="hidden" name="restrict"
																value="<?php echo $comment['is_restricted'] ? 0 : 1; ?>">
															<button type="submit" name="restrict_comment"
																class="btn btn-<?php echo $comment['is_restricted'] ? 'secondary' : 'danger'; ?> btn-sm align-middle">
																<i class="fas fa-ban"></i>
																<?php echo $comment['is_restricted'] ? 'Unrestrict' : 'Restrict'; ?>
															</button>
														</form>
													<?php endif; ?>
												</div>
											</div>
										<?php
										endforeach;
										if ($totalComments > 3):
										?>
											<div class="text-center mt-2">
												<button class="btn btn-link btn-sm"
													onclick="showAllComments(<?php echo $item['id']; ?>)">
													View all <?php echo $totalComments; ?> comments
												</button>
											</div>
										<?php
										endif;
									else:
										?>
										<p class="text-muted"><small>No comments yet.</small></p>
									<?php endif; ?>
								</div>
							</div>
							<div class="btn-group">
								<form method="POST" class="d-inline" style="margin:0; padding:0;">
									<input type="hidden" name="content_id" value="<?php echo $item['id']; ?>">
									<button type="submit" name="toggle_view_restriction"
										class="btn btn-sm <?php echo $item['view_restricted'] ? 'btn-secondary' : 'btn-primary'; ?>"
										onclick="return confirm('Are you sure you want to <?php echo $item['view_restricted'] ? 'enable' : 'disable'; ?> viewing for this content?')">
										<i class="fas <?php echo $item['view_restricted'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
									</button>
								</form>
								<a href="<?php echo $filePath; ?>" class="btn btn-sm btn-success" download>
									<i class="fas fa-download"></i>
								</a>
								<a href="edit_content.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning">
									<i class="fas fa-edit"></i>
								</a>
								<form method="POST" class="d-inline"
									onsubmit="return confirm('Are you sure you want to permanently remove this content? This action cannot be undone.');">
									<input type="hidden" name="delete_content_id" value="<?php echo $item['id']; ?>">
									<button type="submit" name="delete_content" class="btn btn-sm btn-danger">
										<i class="fas fa-trash"></i> Remove
									</button>
								</form>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<!-- File Requests Section -->


		<!-- Upload Modal -->
		<div class="modal fade" id="uploadModal" tabindex="-1">
			<div class="modal-dialog modal-lg">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Upload New Content</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<?php if (!empty($errors)): ?>
							<div class="alert alert-danger">
								<ul class="mb-0">
									<?php foreach ($errors as $error): ?>
										<li><?php echo htmlspecialchars($error); ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>

						<?php if (isset($_SESSION['message'])): ?>
							<div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show"
								role="alert">
								<?php
								echo htmlspecialchars($_SESSION['message']);
								unset($_SESSION['message']);
								unset($_SESSION['message_type']);
								?>
								<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
							</div>
						<?php endif; ?>

						<form method="POST" enctype="multipart/form-data">
							<div class="row">
								<div class="col-md-6 mb-3">
									<label for="title" class="form-label">Title</label>
									<input type="text" class="form-control" id="title" name="title"
										value="<?php echo htmlspecialchars($title); ?>" required>
								</div>
								<div class="col-md-6 mb-3">
									<label for="file_type" class="form-label">File Type</label>
									<select class="form-select" id="file_type" name="file_type" required>
										<option value="">Select Type</option>
										<option value="pdf" <?php echo ($fileType === 'pdf') ? 'selected' : ''; ?>>PDF
										</option>
										<option value="video" <?php echo ($fileType === 'video') ? 'selected' : ''; ?>>
											Video</option>
										<option value="audio" <?php echo ($fileType === 'audio') ? 'selected' : ''; ?>>
											Audio</option>
										<option value="ppt" <?php echo ($fileType === 'ppt') ? 'selected' : ''; ?>>PPT
										</option>
										<option value="doc" <?php echo ($fileType === 'doc') ? 'selected' : ''; ?>>DOC
										</option>
										<option value="excel" <?php echo ($fileType === 'excel') ? 'selected' : ''; ?>>
											Excel</option>
										<option value="image" <?php echo ($fileType === 'image') ? 'selected' : ''; ?>>
											Image</option>
									</select>
								</div>
							</div>

							<div class="mb-3">
								<label for="description" class="form-label">Description</label>
								<textarea class="form-control" id="description" name="description" rows="3"
									required><?php echo htmlspecialchars($description); ?></textarea>
							</div>

							<div class="mb-3">
								<label class="form-label">Useful To</label>
								<?php
								$usefulToArray = !empty($usefulTo) ? explode(',', $usefulTo) : array();
								?>
								<div class="form-check">
									<input class="form-check-input" type="checkbox" name="useful_to[]" value="student"
										id="useful_student" <?php echo in_array('student', $usefulToArray) ? 'checked' : ''; ?>>
									<label class="form-check-label" for="useful_student">Student</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="checkbox" name="useful_to[]" value="teacher"
										id="useful_teacher" <?php echo in_array('teacher', $usefulToArray) ? 'checked' : ''; ?>>
									<label class="form-check-label" for="useful_teacher">Teacher</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="checkbox" name="useful_to[]" value="public"
										id="useful_public" <?php echo in_array('public', $usefulToArray) ? 'checked' : ''; ?>>
									<label class="form-check-label" for="useful_public">Public</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="checkbox" name="useful_to[]" value="others"
										id="useful_others" <?php echo in_array('others', $usefulToArray) ? 'checked' : ''; ?>>
									<label class="form-check-label" for="useful_others">Others</label>
								</div>
							</div>

							<div class="mb-3">
								<label for="tags" class="form-label">Tags (comma-separated)</label>
								<input type="text" class="form-control" id="tags" name="tags"
									value="<?php echo htmlspecialchars($tags); ?>"
									placeholder="e.g., education, math, science">
							</div>

							<div class="mb-3">
								<label for="price" class="form-label">Price (₹)</label>
								<input type="number" class="form-control" id="price" name="price" value="1.00" min="1"
									step="0.01" required>
							</div>

							<!-- Conditional Video Source Section -->
							<div class="mb-3" id="video-source-section" style="display:none;">
								<label class="form-label">How would you like to provide the video?</label>
								<div>
									<div class="form-check form-check-inline">
										<input class="form-check-input" type="radio" name="video_source" id="video_source_file" value="file" checked>
										<label class="form-check-label" for="video_source_file">Upload a file</label>
									</div>
									<div class="form-check form-check-inline">
										<input class="form-check-input" type="radio" name="video_source" id="video_source_youtube" value="youtube">
										<label class="form-check-label" for="video_source_youtube">Provide a YouTube link</label>
									</div>
								</div>
							</div>

							<div class="mb-3" id="file-upload-container">
								<label for="file" class="form-label">Select File:</label>
								<input type="file" class="form-control" id="file" name="file"
									accept="video/mp4,video/avi,video/quicktime,video/mov">
							</div>
							<div class="mb-3" id="youtube-url-container" style="display:none;">
								<label for="youtube_url" class="form-label">YouTube Video Link</label>
								<input type="url" class="form-control" id="youtube_url" name="youtube_url"
									placeholder="https://www.youtube.com/watch?v=...">
								<div class="form-text">Enter a valid YouTube URL (e.g., https://www.youtube.com/watch?v=...)</div>
							</div>
						</form>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-primary" onclick="submitForm()">Upload Content</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Preview Modal -->
		<div class="modal fade" id="previewModal" tabindex="-1">
			<div class="modal-dialog modal-xl">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Content Preview</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div id="previewContent"></div>
					</div>
				</div>
			</div>
		</div>

		<!-- Requests Modal -->
		<div class="modal fade" id="requestsModal" tabindex="-1">
			<div class="modal-dialog modal-xl">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">All File Requests</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<?php if (empty($allRequests)): ?>
							<p>No requests found.</p>
						<?php else: ?>
							<div class="table-responsive">
								<table class="table table-striped">
									<thead>
										<tr>
											<th>File</th>
											<th>User</th>
											<th>Price</th>
											<th>Request Date</th>
											<th>Status</th>
											<th>Action</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($allRequests as $request): ?>
											<tr>
												<td><?php echo htmlspecialchars($request['title']); ?></td>
												<td><?php echo htmlspecialchars($request['username']); ?></td>
												<td>₹<?php echo number_format($request['price'], 2); ?></td>
												<td><?php echo date('M d, Y H:i', strtotime($request['request_date'])); ?></td>
												<td>
													<span class="badge <?php
																		echo $request['status'] === 'approved' ? 'bg-success' : ($request['status'] === 'rejected' ? 'bg-danger' : 'bg-warning');
																		?>">
														<?php echo ucfirst($request['status']); ?>
													</span>
												</td>
												<td>
													<?php if ($request['status'] === 'pending'): ?>
														<form method="POST" class="d-inline">
															<input type="hidden" name="request_id"
																value="<?php echo $request['id']; ?>">
															<button type="submit" name="approve_request"
																class="btn btn-sm btn-success">
																<i class="fas fa-check"></i> Grant Access
															</button>
														</form>
													<?php elseif ($request['status'] === 'approved'): ?>
														<span class="text-success">
															<i class="fas fa-check-circle"></i> Access Granted
														</span>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<!-- Comments Modal -->
		<div class="modal fade" id="commentsModal" tabindex="-1">
			<div class="modal-dialog modal-lg">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">All Comments</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div id="allComments"></div>
					</div>
				</div>
			</div>
		</div>

		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
		<script>
			// Show modal only if there are form validation errors
			window.addEventListener('load', function() {
				const formErrors = document.querySelector('#uploadModal .alert-danger');
				if (formErrors) {
					new bootstrap.Modal(document.getElementById('uploadModal')).show();
				}
			});

			function validateForm() {
				const form = document.querySelector('#uploadModal form');
				const title = form.querySelector('#title').value.trim();
				const description = form.querySelector('#description').value.trim();
				const fileType = form.querySelector('#file_type').value;
				const file = form.querySelector('#file').files[0];
				const usefulTo = form.querySelectorAll('input[name="useful_to[]"]:checked');
				const videoSource = fileType === 'video' ?
					form.querySelector('input[name="video_source"]:checked')?.value :
					null;
				const youtubeUrl = form.querySelector('#youtube_url').value.trim();

				let errors = [];

				// Clear previous error messages
				const existingErrors = form.querySelectorAll('.invalid-feedback');
				existingErrors.forEach(error => error.remove());

				// Reset validation states
				form.querySelectorAll('.is-invalid').forEach(field => {
					field.classList.remove('is-invalid');
				});

				// Validate title
				if (!title) {
					errors.push({
						field: 'title',
						message: 'Title is required'
					});
				}

				// Validate description
				if (!description) {
					errors.push({
						field: 'description',
						message: 'Description is required'
					});
				}

				// Validate file type
				if (!fileType) {
					errors.push({
						field: 'file_type',
						message: 'Please select a file type'
					});
				}

				// Validate useful to
				if (usefulTo.length === 0) {
					errors.push({
						field: 'useful_to',
						message: 'Please select at least one option for "Useful To"'
					});
				}

				// Validate file/youtube for video
				if (fileType === 'video') {
					if (videoSource === 'file') {
						if (!file) {
							errors.push({
								field: 'file',
								message: 'Please select a video file to upload'
							});
						}
					} else if (videoSource === 'youtube') {
						if (!youtubeUrl) {
							errors.push({
								field: 'youtube_url',
								message: 'Please enter a YouTube video link'
							});
						} else if (!/^https?:\/\/(www\.)?(youtube\.com|youtu\.be)\//.test(youtubeUrl)) {
							errors.push({
								field: 'youtube_url',
								message: 'Please enter a valid YouTube URL'
							});
						}
					}
				} else {
					// For non-video, require file
					if (!file) {
						errors.push({
							field: 'file',
							message: 'Please select a file to upload'
						});
					}
				}

				// Display errors if any
				if (errors.length > 0) {
					errors.forEach(error => {
						const field = form.querySelector(`#${error.field}`);
						if (field) {
							field.classList.add('is-invalid');
							const feedback = document.createElement('div');
							feedback.className = 'invalid-feedback';
							feedback.textContent = error.message;
							field.parentNode.appendChild(feedback);
						}
					});
					return false;
				}

				return true;
			}

			function submitForm() {
				if (validateForm()) {
					document.querySelector('#uploadModal form').submit();
				}
			}

			function showPreview(filePath, fileType, button) {
				// Disable the button
				if (button) {
					button.disabled = true;
					button.innerHTML = '<i class="fas fa-eye-slash"></i>';
				}

				const previewContent = document.getElementById('previewContent');
				let content = '';

				switch (fileType) {
					case 'image':
						content = `<img src="${filePath}" alt="Preview" class="img-fluid">`;
						break;
					case 'video':
						content = `<video controls class="w-100">
                                <source src="${filePath}" type="video/mp4">
                                Your browser does not support the video tag.
                              </video>`;
						break;
					case 'audio':
						content = `<audio controls class="w-100">
                                <source src="${filePath}" type="audio/mpeg">
                                Your browser does not support the audio tag.
                              </audio>`;
						break;
					case 'pdf':
						content = `<iframe src="${filePath}"></iframe>`;
						break;
					default:
						content = `<div class="text-center">
                                <i class="fas fa-file fa-5x mb-3"></i>
                                <p>Preview not available for this file type.</p>
                                <a href="${filePath}" class="btn btn-primary" download>Download to View</a>
                              </div>`;
				}

				previewContent.innerHTML = content;
				const modal = new bootstrap.Modal(document.getElementById('previewModal'));

				// Re-enable the button when modal is closed
				modal._element.addEventListener('hidden.bs.modal', function() {
					if (button) {
						button.disabled = false;
						button.innerHTML = '<i class="fas fa-eye"></i>';
					}
				}, {
					once: true
				}); // Remove listener after it's called once

				modal.show();
			}

			function showAllComments(contentId) {
				const modal = new bootstrap.Modal(document.getElementById('commentsModal'));
				const commentsContainer = document.getElementById('allComments');
				commentsContainer.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading comments...</div>';

				// Fetch all comments for this content
				fetch(`get_comments.php?content_id=${contentId}&limit=0`)
					.then(response => response.json())
					.then(data => {
						const comments = data.comments;
						if (comments.length > 0) {
							commentsContainer.innerHTML = comments.map(comment => `
                            <div class="comment-item border-bottom py-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <strong>${escapeHtml(comment.username)}</strong>
                                        <small class="text-muted ms-2">
                                            ${new Date(comment.created_at).toLocaleString()}
                                        </small>
                                        ${comment.is_restricted ? '<span class="badge bg-warning ms-2"><i class="fas fa-eye-slash"></i> Restricted</span>' : ''}
                                        <p class="mb-0 ${comment.is_restricted ? 'text-muted' : ''}">${escapeHtml(comment.comment)}</p>
                                    </div>
                                    <form method="POST" class="ms-2">
                                        <input type="hidden" name="comment_id" value="${comment.id}">
                                        <input type="hidden" name="restrict" value="${comment.is_restricted ? '0' : '1'}">
                                        <button type="submit" name="restrict_comment" class="btn btn-sm ${comment.is_restricted ? 'btn-success' : 'btn-warning'}" 
                                                title="${comment.is_restricted ? 'Unrestrict comment' : 'Restrict comment'}">
                                            <i class="fas ${comment.is_restricted ? 'fa-eye' : 'fa-eye-slash'}"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        `).join('');
						} else {
							commentsContainer.innerHTML = '<p class="text-muted text-center">No comments found.</p>';
						}
					})
					.catch(error => {
						commentsContainer.innerHTML = '<p class="text-danger text-center">Error loading comments. Please try again.</p>';
						console.error('Error:', error);
					});

				modal.show();
			}

			function escapeHtml(unsafe) {
				return unsafe
					.replace(/&/g, "&amp;")
					.replace(/</g, "&lt;")
					.replace(/>/g, "&gt;")
					.replace(/"/g, "&quot;")
					.replace(/'/g, "&#039;");
			}

			// Enhanced video upload logic
			function updateVideoSourceSection() {
				const fileType = document.getElementById('file_type').value;
				const videoSourceSection = document.getElementById('video-source-section');
				const fileUploadContainer = document.getElementById('file-upload-container');
				const youtubeUrlContainer = document.getElementById('youtube-url-container');
				const fileInput = document.getElementById('file');
				const youtubeInput = document.getElementById('youtube_url');

				if (fileType === 'video') {
					videoSourceSection.style.display = '';
					// Show/hide based on selected radio
					const selectedSource = document.querySelector('input[name="video_source"]:checked')?.value || 'file';
					if (selectedSource === 'file') {
						fileUploadContainer.style.display = '';
						youtubeUrlContainer.style.display = 'none';
						fileInput.required = true;
						youtubeInput.required = false;
					} else {
						fileUploadContainer.style.display = 'none';
						youtubeUrlContainer.style.display = '';
						fileInput.required = false;
						youtubeInput.required = true;
					}
				} else {
					videoSourceSection.style.display = 'none';
					fileUploadContainer.style.display = '';
					youtubeUrlContainer.style.display = 'none';
					fileInput.required = true;
					youtubeInput.required = false;
				}
			}

			document.addEventListener('DOMContentLoaded', function() {
				document.getElementById('file_type').addEventListener('change', updateVideoSourceSection);
				document.getElementsByName('video_source').forEach(function(el) {
					el.addEventListener('change', updateVideoSourceSection);
				});
				updateVideoSourceSection();
			});

			// Update validateForm to check only the visible input for video
			function validateForm() {
				const form = document.querySelector('#uploadModal form');
				const title = form.querySelector('#title').value.trim();
				const description = form.querySelector('#description').value.trim();
				const fileType = form.querySelector('#file_type').value;
				const file = form.querySelector('#file').files[0];
				const usefulTo = form.querySelectorAll('input[name="useful_to[]"]:checked');
				const videoSource = fileType === 'video' ?
					form.querySelector('input[name="video_source"]:checked')?.value :
					null;
				const youtubeUrl = form.querySelector('#youtube_url').value.trim();

				let errors = [];

				// Clear previous error messages
				const existingErrors = form.querySelectorAll('.invalid-feedback');
				existingErrors.forEach(error => error.remove());

				// Reset validation states
				form.querySelectorAll('.is-invalid').forEach(field => {
					field.classList.remove('is-invalid');
				});

				// Validate title
				if (!title) {
					errors.push({
						field: 'title',
						message: 'Title is required'
					});
				}

				// Validate description
				if (!description) {
					errors.push({
						field: 'description',
						message: 'Description is required'
					});
				}

				// Validate file type
				if (!fileType) {
					errors.push({
						field: 'file_type',
						message: 'Please select a file type'
					});
				}

				// Validate useful to
				if (usefulTo.length === 0) {
					errors.push({
						field: 'useful_to',
						message: 'Please select at least one option for "Useful To"'
					});
				}

				// Validate file/youtube for video
				if (fileType === 'video') {
					if (videoSource === 'file') {
						if (!file) {
							errors.push({
								field: 'file',
								message: 'Please select a video file to upload'
							});
						}
					} else if (videoSource === 'youtube') {
						if (!youtubeUrl) {
							errors.push({
								field: 'youtube_url',
								message: 'Please enter a YouTube video link'
							});
						} else if (!/^https?:\/\/(www\.)?(youtube\.com|youtu\.be)\//.test(youtubeUrl)) {
							errors.push({
								field: 'youtube_url',
								message: 'Please enter a valid YouTube URL'
							});
						}
					}
				} else {
					// For non-video, require file
					if (!file) {
						errors.push({
							field: 'file',
							message: 'Please select a file to upload'
						});
					}
				}

				// Display errors if any
				if (errors.length > 0) {
					errors.forEach(error => {
						const field = form.querySelector(`#${error.field}`);
						if (field) {
							field.classList.add('is-invalid');
							const feedback = document.createElement('div');
							feedback.className = 'invalid-feedback';
							feedback.textContent = error.message;
							field.parentNode.appendChild(feedback);
						}
					});
					return false;
				}

				return true;
			}
		</script>
</body>

</html>
