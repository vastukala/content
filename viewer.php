<?php
session_start();
require_once 'config.php';

// Get content ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if this is a download request
if (isset($_GET['download'])) {
	if (!isset($_SESSION['user_id'])) {
		header('Location: login.html');
		exit();
	}

	// Fetch file path and check restrictions
	$query = "SELECT c.*, fr.status as request_status 
              FROM content c 
              LEFT JOIN file_requests fr ON c.id = fr.content_id AND fr.user_id = ? 
              WHERE c.id = ?";
	$stmt = mysqli_prepare($conn, $query);
	mysqli_stmt_bind_param($stmt, "ii", $_SESSION['user_id'], $id);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);

	if ($content = mysqli_fetch_assoc($result)) {
		// Check if content is restricted and user has permission
		if ($content['is_restricted'] && (!isset($content['request_status']) || $content['request_status'] !== 'approved')) {
			header('Location: index.php?error=restricted');
			exit();
		}

		$file = $content['file_path'];
		if (file_exists($file)) {
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="' . basename($file) . '"');
			header('Content-Length: ' . filesize($file));
			readfile($file);
			exit();
		}
	}
	header('Location: index.php');
	exit();
}

// Handle file request submission
if (isset($_POST['request_access']) && isset($_SESSION['user_id'])) {
	$userId = $_SESSION['user_id'];

	// Check if request already exists
	$checkQuery = "SELECT id FROM file_requests WHERE content_id = ? AND user_id = ?";
	$stmt = mysqli_prepare($conn, $checkQuery);
	mysqli_stmt_bind_param($stmt, "ii", $id, $userId);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);

	if (mysqli_num_rows($result) == 0) {
		// Insert new request
		$insertQuery = "INSERT INTO file_requests (content_id, user_id) VALUES (?, ?)";
		$stmt = mysqli_prepare($conn, $insertQuery);
		mysqli_stmt_bind_param($stmt, "ii", $id, $userId);
		mysqli_stmt_execute($stmt);

		header("Location: viewer.php?id=$id&message=request_sent");
		exit();
	}
}

// Fetch content details for viewing
$query = "SELECT c.*, fr.status as request_status 
          FROM content c 
          LEFT JOIN file_requests fr ON c.id = fr.content_id AND fr.user_id = ? 
          WHERE c.id = ?";
$stmt = mysqli_prepare($conn, $query);
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
mysqli_stmt_bind_param($stmt, "ii", $userId, $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$content = mysqli_fetch_assoc($result);

// If content not found or is view-restricted, redirect to index
if (!$content || ($content['view_restricted'] && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'))) {
	header('Location: index.php?error=restricted');
	exit();
}

// Function to get file type from path
function getFileType($filePath)
{
	$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
	if ($ext === 'pdf') return 'pdf';
	if (in_array($ext, ['mp4', 'avi', 'mov', 'wmv'])) return 'video';
	if (in_array($ext, ['mp3', 'wav', 'aac', 'ogg', 'flac'])) return 'audio';
	if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) return 'image';
	if (in_array($ext, ['doc', 'docx'])) return 'doc';
	if (in_array($ext, ['ppt', 'pptx'])) return 'ppt';
	if (in_array($ext, ['xls', 'xlsx', 'csv'])) return 'excel';
	// Fallback to folder-based detection
	if (strpos($filePath, '/pdf/') !== false) return 'pdf';
	if (strpos($filePath, '/video/') !== false) return 'video';
	if (strpos($filePath, '/audio/') !== false) return 'audio';
	if (strpos($filePath, '/image/') !== false) return 'image';
	if (strpos($filePath, '/doc/') !== false) return 'doc';
	if (strpos($filePath, '/ppt/') !== false) return 'ppt';
	if (strpos($filePath, '/excel/') !== false) return 'excel';
	return 'unknown';
}

$previewFile = !empty($content['converted_file_path']) ? $content['converted_file_path'] : $content['file_path'];
$fileType = getFileType($content['file_path']);
$canViewContent = !$content['is_restricted'] ||
	(isset($_SESSION['user_id']) && $content['request_status'] === 'approved');
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Content Viewer</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		body,
		html {
			margin: 0;
			padding: 0;
			width: 100%;
			height: 100%;
			overflow: hidden;
		}

		.viewer-container {
			width: 100%;
			height: 100vh;
			display: flex;
			justify-content: center;
			align-items: center;
			background: #fff;
		}

		.preview-container {
			width: 100%;
			height: 100%;
			display: flex;
			justify-content: center;
			align-items: center;
			flex-direction: column;
		}

		.preview-container embed,
		.preview-container video,
		.preview-container img {
			max-width: 100%;
			max-height: 100%;
			width: 100%;
			height: 100%;
			object-fit: contain;
		}

		.preview-container audio {
			width: 80%;
		}

		.audio-container {
			width: 100%;
			text-align: center;
			padding: 20px;
		}

		.file-message {
			text-align: center;
			color: #666;
			font-family: Arial, sans-serif;
			padding: 20px;
		}

		.restricted-message {
			background: #f8d7da;
			border: 1px solid #f5c2c7;
			padding: 20px;
			border-radius: 5px;
			margin: 20px;
			text-align: center;
		}

		.request-form {
			margin-top: 20px;
		}

		.excel-like-table td,
		.excel-like-table th {
			border: 1px solid #d1d5db;
			padding: 8px 12px;
			min-width: 80px;
			max-width: 220px;
			white-space: nowrap;
			text-align: left;
			font-family: Calibri, Arial, sans-serif;
			font-size: 1em;
			background: #fff;
		}

		.excel-like-table th {
			background: #ffe082;
			color: #222;
			font-weight: bold;
			text-align: center;
		}

		.excel-like-table {
			border-collapse: collapse;
			background: #fff;
			width: fit-content;
			min-width: 600px;
			border-radius: 8px;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
			margin-bottom: 24px;
		}

		#excel-preview-wrapper {
			user-select: none;
		}
	</style>
</head>

<body>
	<div class="viewer-container">
		<div class="preview-container">
			<?php if (isset($_GET['message']) && $_GET['message'] === 'request_sent'): ?>
				<div class="alert alert-success" role="alert">
					Your request has been sent. You will be notified once it's approved.
				</div>
			<?php endif; ?>

			<?php if ($content['is_restricted']): ?>
				<div class="restricted-message">
					<h4>Restricted Content</h4>
					<p>This content requires approval from the administrator to view.</p>

					<?php if (!isset($_SESSION['user_id'])): ?>
						<p>Please <a href="login.html">login</a> to request access.</p>
					<?php elseif ($content['request_status'] === 'approved'): ?>
						<!-- Show content for approved users -->
						<?php if ($fileType === 'pdf'): ?>
							<embed src="<?php echo htmlspecialchars($content['file_path']); ?>" type="application/pdf" width="100%" height="100%">
						<?php elseif ($fileType === 'video'): ?>
							<?php 
							$isYoutube = isset($content['video_type']) && $content['video_type'] === 'youtube';
							$youtubeId = '';
							if ($isYoutube) {
								// Extract YouTube video ID from URL
								preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^\s&\/\?]+)/i', $content['file_path'], $matches);
								$youtubeId = $matches[1] ?? '';
							}
							?>
							<div id="video-container" style="width:100%;max-width:800px;margin:0 auto;position:relative;background:#000;">
								<?php if ($isYoutube && $youtubeId): ?>
									<!-- YouTube Player -->
									<div id="youtube-player"></div>
								<?php else: ?>
									<!-- HTML5 Video Player -->
									<video id="preview-video" style="width:100%;max-height:80vh;display:block;">
										<source src="<?php echo htmlspecialchars($content['file_path']); ?>" type="video/mp4">
										Your browser does not support the video tag.
									</video>
								<?php endif; ?>
								
								<!-- Preview Lock Overlay -->
								<div id="preview-lock" style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);color:white;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;padding:20px;z-index:10;">
									<h4>Preview Locked</h4>
									<p>You've reached the 30-second preview limit.</p>
									<p>Please sign in or purchase to watch the full video.</p>
									<?php if (!isset($_SESSION['user_id'])): ?>
										<a href="login.html" class="btn btn-primary">Sign In</a>
									<?php else: ?>
										<button class="btn btn-primary" onclick="requestFullAccess()">Request Full Access</button>
									<?php endif; ?>
								</div>
							</div>
							<script>
								let previewVideo = document.getElementById('preview-video');
								let previewLock = document.getElementById('preview-lock');
								let previewTimer = null;

								function playPreviewVideo() {
									previewVideo.play();
									previewLock.style.display = 'none';
									if (previewTimer) clearTimeout(previewTimer);
									previewTimer = setTimeout(function() {
										previewVideo.pause();
										previewLock.style.display = 'flex';
									}, 30000);
									const video = document.getElementById('video-preview');
									const overlay = video.parentElement.querySelector('.video-overlay');
									const playBtn = video.parentElement.querySelector('.custom-play-btn');
									overlay.style.display = 'none';
									playBtn.style.visibility = 'hidden';
									video.play();
									let maxTime = null;
									video.addEventListener('loadedmetadata', function() {
										maxTime = video.duration * 0.15;
									});
									video.addEventListener('timeupdate', function() {
										if (maxTime && video.currentTime >= maxTime) {
											video.pause();
											video.currentTime = maxTime;
											document.getElementById('video-msg').style.display = 'block';
											showVideoPurchaseModal();
										}
									});
									video.addEventListener('seeking', function() {
										if (maxTime && video.currentTime > maxTime) {
											video.currentTime = maxTime;
										}
									});
								}

								function showVideoPurchaseModal() {
									let modal = document.getElementById('videoPurchaseModal');
									if (!modal) {
										modal = document.createElement('div');
										modal.id = 'videoPurchaseModal';
										modal.style.position = 'fixed';
										modal.style.top = '0';
										modal.style.left = '0';
										modal.style.width = '100vw';
										modal.style.height = '100vh';
										modal.style.background = 'rgba(0,0,0,0.5)';
										modal.style.display = 'flex';
										modal.style.alignItems = 'center';
										modal.style.justifyContent = 'center';
										modal.style.zIndex = '9999';
										modal.innerHTML = `<div style="background:#fff;padding:32px 24px;border-radius:8px;max-width:90vw;text-align:center;">
										<h4>Preview limit reached</h4>
										<p>To watch the full video, please purchase or request access.</p>
										<button onclick="document.getElementById('videoPurchaseModal').remove()" class="btn btn-primary mt-2">Close</button>
									</div>`;
										document.body.appendChild(modal);
									}
								}
								document.addEventListener('DOMContentLoaded', function() {
									document.querySelectorAll('.video-preview-container').forEach(function(container) {
										container.addEventListener('contextmenu', function(e) {
											e.preventDefault();
										});
									});
								});
							</script>
						<?php elseif ($fileType === 'audio'): ?>
							<div class="audio-container" style="position:relative;width:100%;max-width:600px;margin:auto;">
								<div id="custom-audio-player" style="width:100%;background:#f8f9fa;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);display:flex;flex-direction:column;align-items:center;">
									<button id="audio-start-btn" class="btn btn-success mb-2" style="width:120px;">Start</button>
									<button id="audio-stop-btn" class="btn btn-danger mb-2" style="width:120px;display:none;">Stop</button>
									<div id="audio-status" class="text-muted mb-2">Preview: First 30 seconds only</div>
									<audio id="audio-preview" src="<?php echo htmlspecialchars($previewFile); ?>" preload="metadata" style="display:none;"></audio>
									<div id="audio-overlay" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:2;cursor:not-allowed;"></div>
								</div>
								<div id="audio-msg" class="text-danger small mt-2" style="display:none;text-align:center;">Preview ended. Please purchase to access full audio.</div>
							</div>
							<script>
								document.addEventListener('DOMContentLoaded', function() {
									const audio = document.getElementById('audio-preview');
									const startBtn = document.getElementById('audio-start-btn');
									const stopBtn = document.getElementById('audio-stop-btn');
									const status = document.getElementById('audio-status');
									const overlay = document.getElementById('audio-overlay');
									const msg = document.getElementById('audio-msg');
									let previewEnded = false;
									overlay.style.pointerEvents = 'none';
									let previewTimer = null;
									startBtn.onclick = function() {
										if (previewEnded) return;
										audio.currentTime = 0;
										audio.play();
										startBtn.style.display = 'none';
										stopBtn.style.display = 'block';
										status.style.display = 'block';
										msg.style.display = 'none';
										if (previewTimer) clearTimeout(previewTimer);
										previewTimer = setTimeout(function() {
											audio.pause();
											audio.currentTime = 30;
											startBtn.style.display = 'none';
											stopBtn.style.display = 'none';
											status.style.display = 'none';
											msg.style.display = 'block';
											previewEnded = true;
										}, 30000);
									};
									stopBtn.onclick = function() {
										audio.pause();
										audio.currentTime = 0;
										startBtn.style.display = 'block';
										stopBtn.style.display = 'none';
										status.style.display = 'block';
										msg.style.display = 'none';
										if (previewTimer) clearTimeout(previewTimer);
									};
									audio.ontimeupdate = function() {
										if (audio.currentTime >= 30 && !previewEnded) {
											audio.pause();
											audio.currentTime = 30;
											startBtn.style.display = 'none';
											stopBtn.style.display = 'none';
											status.style.display = 'none';
											msg.style.display = 'block';
											previewEnded = true;
											if (previewTimer) clearTimeout(previewTimer);
										}
									};
									audio.onseeking = function() {
										if (audio.currentTime > 30) {
											audio.currentTime = 30;
										}
									};
									document.getElementById('custom-audio-player').oncontextmenu = function(e) {
										e.preventDefault();
									};
									document.getElementById('custom-audio-player').style.userSelect = 'none';
								});
							</script>
							<style>
								#custom-audio-player {
									user-select: none;
								}
							</style>
						<?php elseif ($fileType === 'image'): ?>
							<img src="<?php echo htmlspecialchars($content['file_path']); ?>" alt="Content">
						<?php else: ?>
							<div class="file-message">
								<p>Preview not available for this file type.</p>
								<p><a href="viewer.php?id=<?php echo $id; ?>&download=1" class="btn btn-primary">Download to view</a></p>
							</div>
						<?php endif; ?>
					<?php elseif ($content['request_status'] === 'pending'): ?>
						<p>Your request is pending approval.</p>
					<?php else: ?>
						<form method="POST" class="request-form">
							<button type="submit" name="request_access" class="btn btn-primary">Request Access</button>
						</form>
					<?php endif; ?>
				</div>
			<?php else: ?>
				<div class="preview-container mb-2">
					<?php if ($fileType === 'image'): ?>
						<div style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:10000;background:#000;display:flex;align-items:center;justify-content:center;">
							<img src="<?php echo htmlspecialchars($previewFile); ?>" alt="Preview" class="img-fluid" style="max-width:100vw;max-height:100vh;object-fit:contain;" id="preview-image">
						</div>
						<script>
							document.addEventListener('DOMContentLoaded', function() {
								var img = document.getElementById('preview-image');
								if (img) {
									img.addEventListener('contextmenu', function(e) {
										e.preventDefault();
									});
								}
							});
						</script>
					<?php elseif ($fileType === 'video'): ?>
						<div class="video-preview-container" style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:10000;background:#000;display:flex;align-items:center;justify-content:center;">
							<video id="video-preview" src="<?php echo htmlspecialchars($previewFile); ?>" style="width:100vw;height:100vh;object-fit:contain;display:block;background:#000;"></video>
							<div class="video-overlay" style="position:absolute;top:0;left:0;width:100vw;height:100vh;z-index:2;cursor:not-allowed;"></div>
							<button class="custom-play-btn" onclick="playPreviewVideo()" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:3;background:#28a745;color:#fff;border:none;border-radius:50%;width:64px;height:64px;font-size:2em;display:flex;align-items:center;justify-content:center;cursor:pointer;">▶</button>
						</div>
						<div id="video-msg" class="text-danger small mt-1" style="display:none;">Preview limited. Please request access to view full content.</div>
						<script>
							let videoPreviewTimer = null;

							function playPreviewVideo() {
								const video = document.getElementById('video-preview');
								const overlay = video.parentElement.querySelector('.video-overlay');
								const playBtn = video.parentElement.querySelector('.custom-play-btn');
								overlay.style.display = 'none';
								playBtn.style.display = 'none';
								video.play();
								// Remove any previous listeners
								video.onplay = null;
								video.onpause = null;
								video.onended = null;
								video.onseeking = null;
								// Always stop at 30 seconds
								if (videoPreviewTimer) clearTimeout(videoPreviewTimer);
								videoPreviewTimer = setTimeout(function() {
									video.pause();
									video.currentTime = 30;
									document.getElementById('video-msg').style.display = 'block';
									showVideoPurchaseModal();
								}, 30000);
								video.addEventListener('timeupdate', function onTimeUpdate() {
									if (video.currentTime >= 30) {
										video.pause();
										video.currentTime = 30;
										document.getElementById('video-msg').style.display = 'block';
										showVideoPurchaseModal();
									}
								});
								video.addEventListener('seeking', function onSeeking() {
									if (video.currentTime > 30) {
										video.currentTime = 30;
									}
								});
							}

							function showVideoPurchaseModal() {
								let modal = document.getElementById('videoPurchaseModal');
								if (!modal) {
									modal = document.createElement('div');
									modal.id = 'videoPurchaseModal';
									modal.style.position = 'fixed';
									modal.style.top = '0';
									modal.style.left = '0';
									modal.style.width = '100vw';
									modal.style.height = '100vh';
									modal.style.background = 'rgba(0,0,0,0.7)';
									modal.style.display = 'flex';
									modal.style.alignItems = 'center';
									modal.style.justifyContent = 'center';
									modal.style.zIndex = '10001';
									modal.innerHTML = `<div style="background:#fff;padding:32px 24px;border-radius:8px;max-width:90vw;text-align:center;">
									<h4>Preview limit reached</h4>
									<p>To watch the full video, please purchase or request access.</p>
									<button onclick="window.location.href='index.php'" class="btn btn-primary mt-2">OK</button>
								</div>`;
									document.body.appendChild(modal);
								}
							}
							document.addEventListener('DOMContentLoaded', function() {
								document.querySelectorAll('.video-preview-container').forEach(function(container) {
									container.addEventListener('contextmenu', function(e) {
										e.preventDefault();
									});
								});
							});
						</script>
					<?php elseif ($fileType === 'doc' || $fileType === 'docx' || $fileType === 'word'): ?>
						<div id="docx-preview" style="width:100%;max-width:800px;margin:0 auto;height:100vh;overflow:auto;border:1px solid #ccc;padding:20px;background:white;font-family:'Times New Roman',serif;line-height:1.6;"></div>
						<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.7.0/mammoth.browser.min.js"></script>
						<script>
							function renderMammothDocPreview(blob) {
								const options = {
									convertImage: mammoth.images.inline(function(element) {
										return element.read("base64").then(function(imageBuffer) {
											return {
												src: "data:" + element.contentType + ";base64," + imageBuffer
											};
										});
									}),
									styleMap: [
										"table => table.doc-table",
										"p[style-name='Title'] => h1.doc-title:fresh",
										"p[style-name='Subtitle'] => h2.doc-subtitle:fresh",
										"p[style-name='Heading 1'] => h1.doc-heading:fresh",
										"p[style-name='Heading 2'] => h2.doc-heading:fresh",
										"p[style-name='Heading 3'] => h3.doc-heading:fresh",
										"b => strong.doc-bold",
										"i => em.doc-italic",
										"u => span.doc-underline",
										"p:unordered-list(1) => ul.doc-list > li:fresh",
										"p:ordered-list(1) => ol.doc-list > li:fresh"
									]
								};
								const reader = new FileReader();
								reader.onload = function(e) {
									mammoth.convertToHtml({
											arrayBuffer: e.target.result
										}, options)
										.then(result => {
											document.getElementById('docx-preview').innerHTML = result.value;
										})
										.catch(() => {
											document.getElementById('docx-preview').innerHTML = '<div class="text-danger">Failed to load Word preview.</div>';
										});
								};
								reader.readAsArrayBuffer(blob);
							}
							fetch('<?php echo htmlspecialchars($previewFile); ?>')
								.then(res => res.blob())
								.then(blob => {
									renderMammothDocPreview(blob);
								});
						</script>
						<style>
							/* Enhanced DOC viewer styles */
							#docx-preview {
								font-size: 12pt;
								color: #000;
							}

							#docx-preview .doc-title {
								font-size: 18pt;
								font-weight: bold;
								text-align: center;
								margin: 0 0 12pt 0;
							}

							#docx-preview .doc-subtitle {
								font-size: 14pt;
								font-weight: bold;
								text-align: center;
								margin: 0 0 12pt 0;
							}

							#docx-preview .doc-heading {
								font-weight: bold;
								margin: 12pt 0 6pt 0;
							}

							#docx-preview h1.doc-heading {
								font-size: 16pt;
							}

							#docx-preview h2.doc-heading {
								font-size: 14pt;
							}

							#docx-preview h3.doc-heading {
								font-size: 12pt;
							}

							#docx-preview .doc-bold {
								font-weight: bold;
							}

							#docx-preview .doc-italic {
								font-style: italic;
							}

							#docx-preview .doc-underline {
								text-decoration: underline;
							}

							#docx-preview .doc-list {
								margin: 6pt 0;
								padding-left: 24pt;
							}

							#docx-preview .doc-list li {
								margin: 3pt 0;
							}

							#docx-preview .doc-table {
								width: 100%;
								border-collapse: collapse;
								margin: 12pt 0;
							}

							#docx-preview .doc-table th,
							#docx-preview .doc-table td {
								border: 1px solid #000;
								padding: 6pt 8pt;
								vertical-align: top;
							}

							#docx-preview .doc-table th {
								background: #f0f0f0;
								font-weight: bold;
							}

							#docx-preview p {
								margin: 0 0 6pt 0;
								text-align: justify;
							}
						</style>
						<div class="text-muted small mt-2">If you do not see tables or formatting, please upload a DOCX file for best results.</div>
					<?php elseif ($fileType === 'excel' || $fileType === 'xlsx' || $fileType === 'xls'): ?>
						<div id="excel-preview-wrapper" style="width:100%;height:100vh;overflow:auto;background:#f8f9fa;">
							<div id="excel-preview" style="width:fit-content;min-width:600px;margin:32px auto 0 auto;box-shadow:0 2px 8px rgba(0,0,0,0.08);border-radius:8px;overflow:auto;"></div>
							<div id="excel-locked-msg" class="text-warning small mt-2" style="display:none;text-align:center;position:fixed;bottom:0;left:0;width:100%;background:#fff8e1;padding:8px 0;border-top:1px solid #ffc107;z-index:10;">Unlock full sheet by signing in or purchasing.</div>
						</div>
						<script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
						<script>
							document.addEventListener('DOMContentLoaded', function() {
								fetch('<?php echo htmlspecialchars($previewFile); ?>')
									.then(res => res.arrayBuffer())
									.then(data => {
										if (typeof XLSX === 'undefined') {
											document.getElementById('excel-preview').innerHTML = '<div class="text-danger">Excel preview failed: XLSX library not loaded.</div>';
											return;
										}
										const workbook = XLSX.read(data, {
											type: 'array'
										});
										let html = '';
										let sheetCount = 0;
										const maxSheets = 3;
										for (let sheetName of workbook.SheetNames) {
											if (sheetCount >= maxSheets) break;
											const sheet = workbook.Sheets[sheetName];
											const rows = XLSX.utils.sheet_to_json(sheet, {
												header: 1
											});
											const totalRows = rows.length;
											const showRows = Math.min(Math.max(5, Math.ceil(totalRows * 0.1)), totalRows); // Show at least 5 rows, or all if less
											html += `<div style='font-size:1.5em;font-weight:bold;padding:8px 0 0 8px;color:#222;'>${sheetName} <span style='color:#dc3545;font-size:0.9em;'>(Preview Only)</span></div>`;
											html += '<div style="overflow-x:auto;">';
											html += '<table class="excel-like-table" style="border-collapse:collapse;background:#fff;width:fit-content;min-width:600px;">';
											// Render header row from Excel file
											if (rows.length > 0) {
												html += '<tr>';
												for (let c = 0; c < rows[0].length; c++) {
													let colName = rows[0][c] !== undefined ? rows[0][c] : '';
													html += `<th style="border:1px solid #d1d5db;padding:8px 12px;min-width:80px;max-width:220px;white-space:nowrap;text-align:center;font-family:Calibri,Arial,sans-serif;font-size:1em;background:#ffe082;color:#222;font-weight:bold;">${colName}</th>`;
												}
												html += '</tr>';
											}
											// Render data rows
											for (let r = 1; r < showRows; r++) {
												html += '<tr>';
												for (let c = 0; c < rows[r]?.length || 0; c++) {
													let cellValue = rows[r][c] !== undefined ? rows[r][c] : '';
													// Truncate cell text if longer than 20 characters
													const displayValue = cellValue.length > 20 ? cellValue.substring(0, 20) + '...' : cellValue;
													html += `<td style="border:1px solid #d1d5db;padding:8px 12px;min-width:80px;max-width:220px;white-space:nowrap;text-align:left;font-family:Calibri,Arial,sans-serif;font-size:1em;" title="${cellValue}">${displayValue}</td>`;
												}
												html += '</tr>';
											}
											html += '</table>';
											html += '</div>';
											sheetCount++;
										}
										document.getElementById('excel-preview').innerHTML = html;
										// Show locked message if more sheets/rows exist
										if (workbook.SheetNames.length > maxSheets || workbook.SheetNames.some(name => XLSX.utils.sheet_to_json(workbook.Sheets[name], {
												header: 1
											}).length > Math.ceil(XLSX.utils.sheet_to_json(workbook.Sheets[name], {
												header: 1
											}).length * 0.1))) {
											document.getElementById('excel-locked-msg').style.display = 'block';
										}
										// Prevent right-click and selection
										document.getElementById('excel-preview-wrapper').oncontextmenu = function(e) {
											e.preventDefault();
										};
										document.getElementById('excel-preview-wrapper').style.userSelect = 'none';
									})
									.catch(() => {
										document.getElementById('excel-preview').innerHTML = '<div class="text-danger">Failed to load Excel file for preview.</div>';
									});
							});
						</script>
						<style>
							.excel-like-table td,
							.excel-like-table th {
								border: 1px solid #d1d5db;
								padding: 8px 12px;
								min-width: 80px;
								max-width: 220px;
								white-space: nowrap;
								text-align: left;
								font-family: Calibri, Arial, sans-serif;
								font-size: 1em;
								background: #fff;
							}

							.excel-like-table th {
								background: #ffe082;
								color: #222;
								font-weight: bold;
								text-align: center;
							}

							.excel-like-table {
								border-collapse: collapse;
								background: #fff;
								width: fit-content;
								min-width: 600px;
								border-radius: 8px;
								box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
								margin-bottom: 24px;
							}

							#excel-preview-wrapper {
								user-select: none;
							}
						</style>
					<?php elseif ($fileType === 'ppt' || $fileType === 'pptx'): ?>
						<div id="pptx-viewer-container" style="width:100%;max-width:900px;margin:auto;">
							<div class="text-center text-muted">Loading PPTX preview...</div>
						</div>
						<script>
							// Initialize PPTX preview using client-side JavaScript
							document.addEventListener('DOMContentLoaded', function() {
								const fileUrl = '<?php echo htmlspecialchars($previewFile); ?>';
								const container = document.getElementById('pptx-viewer-container');
								renderPptxPreview(fileUrl, container);
							});
						</script>

					<?php elseif ($fileType === 'pdf'): ?>
						<div id="pdf-preview" style="width:100%;height:100vh;overflow:auto;border:1px solid #ccc;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:20px;box-sizing:border-box;"></div>
						<div class="text-danger small mt-1">Preview limited. Please request access to view full content.</div>
						<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
						<script>
							document.addEventListener('DOMContentLoaded', function() {
								var url = '<?php echo htmlspecialchars($previewFile); ?>';
								var container = document.getElementById('pdf-preview');
								if (!window.pdfjsLib) return;
								pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
								pdfjsLib.getDocument(url).promise.then(function(pdf) {
									var total = pdf.numPages;
									var showPages = Math.max(1, Math.floor(total * 0.1));

									function renderPage(num) {
										pdf.getPage(num).then(function(page) {
											var canvas = document.createElement('canvas');
											var ctx = canvas.getContext('2d');
											var viewport = page.getViewport({
												scale: 1.2
											});
											canvas.height = viewport.height;
											canvas.width = viewport.width;
											page.render({
												canvasContext: ctx,
												viewport: viewport
											}).promise.then(function() {
												container.appendChild(canvas);
												if (num < showPages) renderPage(num + 1);
											});
										});
									}
									renderPage(1);
								});
							});
						</script>
					<?php else: ?>
						<div class="text-muted">Preview not available for this file type.</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<!-- YouTube IFrame API -->
	<script src="https://www.youtube.com/iframe_api"></script>
	
	<script>
	// YouTube Player API
	let youtubePlayer;
	let previewTimer;
	const PREVIEW_DURATION = 30; // 30 seconds preview
	
	function onYouTubeIframeAPIReady() {
		const isYoutube = document.getElementById('youtube-player') !== null;
		if (!isYoutube) return;
		
		youtubePlayer = new YT.Player('youtube-player', {
			height: '480',
			width: '100%',
			videoId: '<?php echo $youtubeId ?? ''; ?>',
			playerVars: {
				'playsinline': 1,
				'controls': 1,
				'showinfo': 0,
				'rel': 0,
				'modestbranding': 1
			},
			events: {
				'onReady': onPlayerReady,
				'onStateChange': onPlayerStateChange
			}
		});
	}
	
	function onPlayerReady(event) {
		// Start the preview timer
		startPreviewTimer();
	}
	
	function onPlayerStateChange(event) {
		// Pause video if it's playing beyond preview duration
		if (event.data === YT.PlayerState.PLAYING) {
			const currentTime = event.target.getCurrentTime();
			if (currentTime > PREVIEW_DURATION) {
				event.target.pauseVideo();
				event.target.seekTo(PREVIEW_DURATION, true);
				showPreviewLock();
			}
		}
	}
	
	function startPreviewTimer() {
		// Clear any existing timer
		if (previewTimer) clearTimeout(previewTimer);
		
		// Set timer to show lock after preview duration
		previewTimer = setTimeout(() => {
			showPreviewLock();
			if (youtubePlayer) {
				youtubePlayer.pauseVideo();
			}
		}, PREVIEW_DURATION * 1000);
	}
	
	function showPreviewLock() {
		document.getElementById('preview-lock').style.display = 'flex';
	}
	
	function requestFullAccess() {
		// This function will be called when the request access button is clicked
		const contentId = <?php echo $content['id']; ?>;
		fetch('request_access.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: `content_id=${contentId}`
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				alert('Your request has been submitted. You will be notified once approved.');
				document.getElementById('preview-lock').innerHTML = `
					<h4>Request Submitted</h4>
					<p>Your request is pending approval.</p>
					<button class="btn btn-secondary" disabled>Processing...</button>
				`;
			} else {
				alert('Error: ' + (data.message || 'Failed to process your request.'));
			}
		})
		.catch(error => {
			console.error('Error:', error);
			alert('An error occurred. Please try again.');
		});
	}
	
	// Handle HTML5 video preview timer
	document.addEventListener('DOMContentLoaded', function() {
		const video = document.getElementById('preview-video');
		if (!video) return;
		
		video.addEventListener('timeupdate', function() {
			// Show lock when reaching preview duration
			if (video.currentTime >= PREVIEW_DURATION) {
				video.pause();
				showPreviewLock();
			}
		});
		
		// Start the timer when video starts playing
		video.addEventListener('play', startPreviewTimer);
		
		// Pause the timer when video is paused
		video.addEventListener('pause', function() {
			if (previewTimer) clearTimeout(previewTimer);
		});
	});
		// Simple PPTX Preview - Working Implementation
		function loadPptxPreview(filePath) {
			const container = document.getElementById('pptx-preview');
			if (!container) return;
			
			// Show simple preview with download option
			container.innerHTML = `
				<div style="
					background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
					backdrop-filter: blur(10px);
					border: 1px solid rgba(255,255,255,0.2);
					border-radius: 15px;
					padding: 30px;
					text-align: center;
					box-shadow: 0 8px 32px rgba(0,0,0,0.1);
				">
					<div style="font-size: 4rem; color: #d63384; margin-bottom: 20px;">
						<i class="fas fa-file-powerpoint"></i>
					</div>
					<h4 style="color: #333; margin-bottom: 15px;">PowerPoint Presentation Preview</h4>
					<p style="color: #666; margin-bottom: 25px;">This is a PowerPoint presentation file (.pptx)</p>
					<div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
						<a href="${filePath}" download class="btn btn-primary" style="
							background: linear-gradient(45deg, #007bff, #0056b3);
							border: none;
							padding: 12px 25px;
							border-radius: 25px;
							text-decoration: none;
							color: white;
							transition: all 0.3s ease;
						">
							<i class="fas fa-download"></i> Download to View
						</a>
						<button onclick="showPptxInfo('${filePath}')" class="btn btn-outline-secondary" style="
							padding: 12px 25px;
							border-radius: 25px;
							transition: all 0.3s ease;
						">
							<i class="fas fa-info-circle"></i> File Info
						</button>
					</div>
					<div style="margin-top: 20px; padding: 15px; background: rgba(255,193,7,0.1); border-radius: 10px;">
						<small style="color: #856404;">
							<i class="fas fa-lightbulb"></i> 
							Tip: Download the file and open with Microsoft PowerPoint, LibreOffice Impress, or Google Slides
						</small>
					</div>
				</div>
			`;
		}
		
		function showPptxInfo(filePath) {
			fetch(filePath, { method: 'HEAD' })
				.then(response => {
					const size = response.headers.get('content-length');
					const sizeKB = size ? Math.round(size / 1024) : 'Unknown';
					
					alert(`File Information:\n\nName: ${filePath.split('/').pop()}\nSize: ${sizeKB} KB\nType: PowerPoint Presentation (.pptx)\n\nTo view this presentation, please download it and open with:\n• Microsoft PowerPoint\n• LibreOffice Impress\n• Google Slides\n• Any compatible presentation software`);
			})
			.catch(() => {
				alert(`File Information:\n\nName: ${filePath.split('/').pop()}\nType: PowerPoint Presentation (.pptx)\n\nTo view this presentation, please download it and open with compatible software.`);
			});
		}
		
		// Initialize PPTX preview if this is a PPTX file
		<?php if ($fileExtension === 'pptx'): ?>
			document.addEventListener('DOMContentLoaded', function() {
				loadPptxPreview('<?php echo $filePath; ?>');
			});
		<?php endif; ?>
		
	</script>
</body>

</html>
