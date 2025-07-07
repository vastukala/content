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
    
    // Fetch file path
    $query = "SELECT file_path FROM content WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($content = mysqli_fetch_assoc($result)) {
        $file = $content['file_path'];
        if (file_exists($file)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($file).'"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit();
        }
    }
    header('Location: index.php');
    exit();
}

// Fetch content details for viewing
$query = "SELECT * FROM content WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$content = mysqli_fetch_assoc($result);

// If content not found, redirect to index
if (!$content) {
    header('Location: index.php');
    exit();
}

// Function to get file type from path
function getFileType($filePath) {
    if (strpos($filePath, '/pdf/') !== false) return 'pdf';
    if (strpos($filePath, '/video/') !== false) return 'video';
    if (strpos($filePath, '/audio/') !== false) return 'audio';
    if (strpos($filePath, '/image/') !== false) return 'image';
    if (strpos($filePath, '/doc/') !== false) return 'doc';
    if (strpos($filePath, '/ppt/') !== false) return 'ppt';
    if (strpos($filePath, '/excel/') !== false) return 'excel';
    return 'unknown';
}

$fileType = getFileType($content['file_path']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Viewer</title>
    <style>
        body, html {
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
        }
    </style>
</head>
<body>
    <div class="viewer-container">
        <div class="preview-container">
            <?php if ($fileType === 'pdf'): ?>
                <embed src="<?php echo htmlspecialchars($content['file_path']); ?>" type="application/pdf" width="100%" height="100%">
            <?php elseif ($fileType === 'video'): ?>
                <video controls>
                    <source src="<?php echo htmlspecialchars($content['file_path']); ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            <?php elseif ($fileType === 'audio'): ?>
                <div class="audio-container">
                    <audio controls>
                        <source src="<?php echo htmlspecialchars($content['file_path']); ?>" type="audio/mpeg">
                        Your browser does not support the audio tag.
                    </audio>
                </div>
            <?php elseif ($fileType === 'image'): ?>
                <img src="<?php echo htmlspecialchars($content['file_path']); ?>" alt="Content">
            <?php else: ?>
                <div class="file-message">
                    <p>Preview not available for this file type.</p>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <p><a href="viewer.php?id=<?php echo $id; ?>&download=1">Download to view</a></p>
                    <?php else: ?>
                        <p>Please <a href="login.html">login</a> to download and view this content.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 
