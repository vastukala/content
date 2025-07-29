<?php
session_start();
require_once 'config.php';

// Check if video ID is provided
if (!isset($_GET['v']) || empty(trim($_GET['v']))) {
    header('Location: index.php?error=invalid_video');
    exit();
}

$video_id = trim($_GET['v']);

// Fetch content details from database
$stmt = mysqli_prepare($conn, "SELECT * FROM content WHERE file_path LIKE ?");
$search_term = "%" . $video_id . "%";
mysqli_stmt_bind_param($stmt, "s", $search_term);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$content = mysqli_fetch_assoc($result);

if (!$content) {
    header('Location: index.php?error=video_not_found');
    exit();
}

// Check if user has access
$has_access = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $check_access = mysqli_prepare($conn, "SELECT * FROM file_access WHERE content_id = ? AND user_id = ? AND status = 'approved'");
    mysqli_stmt_bind_param($check_access, "ii", $content['id'], $user_id);
    mysqli_stmt_execute($check_access);
    $has_access = mysqli_num_rows(mysqli_stmt_get_result($check_access)) > 0;
}

$is_preview = !$has_access;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($content['title'] ?? 'YouTube Video Preview'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .video-container {
            max-width: 1000px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .video-wrapper {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
            background: #000;
        }
        #youtube-player {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        .preview-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            padding: 20px;
            z-index: 10;
            display: none;
        }
        .preview-overlay h3 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .preview-overlay p {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            max-width: 600px;
            line-height: 1.6;
        }
        .btn-access {
            padding: 10px 25px;
            font-size: 1.1rem;
            font-weight: 500;
            border-radius: 30px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        .video-info {
            padding: 1.5rem;
        }
        .video-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        .video-description {
            color: #6c757d;
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        .back-btn {
            margin-bottom: 1.5rem;
            display: inline-flex;
            align-items: center;
            color: #6c757d;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-btn:hover {
            color: #0d6efd;
            text-decoration: none;
        }
        .back-btn i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Content
        </a>
        
        <div class="video-container">
            <div class="video-wrapper">
                <div id="youtube-player"></div>
                <?php if ($is_preview): ?>
                <div id="preview-overlay" class="preview-overlay">
                    <h3>Preview Locked</h3>
                    <p>You've reached the 30-second preview limit. To continue watching, please request full access to this content.</p>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button id="request-access-btn" class="btn btn-primary btn-access">
                            <i class="fas fa-unlock-alt me-2"></i>Request Full Access
                        </button>
                    <?php else: ?>
                        <a href="login.html?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary btn-access">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In to Request Access
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="video-info">
                <h1 class="video-title"><?php echo htmlspecialchars($content['title']); ?></h1>
                <?php if (!empty($content['description'])): ?>
                    <p class="video-description"><?php echo nl2br(htmlspecialchars($content['description'])); ?></p>
                <?php endif; ?>
                <?php if ($is_preview): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        You're currently viewing a preview. The full video is <?php echo $content['duration'] ?? 'available' ?> long.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Load the YouTube IFrame API -->
    <script>
        let player;
        let previewTimer;
        const PREVIEW_DURATION = 30; // 30 seconds preview
        const isPreview = <?php echo $is_preview ? 'true' : 'false'; ?>;
        const videoId = '<?php echo $video_id; ?>';
        
        // This function creates an <iframe> (and YouTube player) after the API code downloads.
        function onYouTubeIframeAPIReady() {
            player = new YT.Player('youtube-player', {
                height: '100%',
                width: '100%',
                videoId: videoId,
                playerVars: {
                    'autoplay': 1,
                    'controls': 1,
                    'rel': 0,
                    'modestbranding': 1,
                    'enablejsapi': 1,
                    'playsinline': 1,
                    'origin': window.location.origin
                },
                events: {
                    'onReady': onPlayerReady,
                    'onStateChange': onPlayerStateChange,
                    'onError': onPlayerError
                }
            });
        }
        
        // Function to handle player errors
        function onPlayerError(event) {
            console.error('YouTube Player Error:', event.data);
            alert('Error loading the video. Please try again later.');
        }

        function onPlayerReady(event) {
            // Start preview timer if in preview mode
            if (isPreview) {
                startPreviewTimer();
            }
        }

        function onPlayerStateChange(event) {
            // If video ends or is paused, clear the preview timer
            if (event.data === YT.PlayerState.ENDED || event.data === YT.PlayerState.PAUSED) {
                if (previewTimer) {
                    clearTimeout(previewTimer);
                    previewTimer = null;
                }
            }
            // If video is playing and in preview mode, start the preview timer
            else if (event.data === YT.PlayerState.PLAYING && isPreview && !previewTimer) {
                startPreviewTimer();
            }
        }

        function startPreviewTimer() {
            if (previewTimer) clearTimeout(previewTimer);
            
            previewTimer = setTimeout(() => {
                if (player && player.pauseVideo) {
                    player.pauseVideo();
                    // Show the preview lock overlay
                    const overlay = document.getElementById('preview-overlay');
                    if (overlay) {
                        overlay.style.display = 'flex';
                    }
                }
            }, PREVIEW_DURATION * 1000);
        }

        // Handle request access button click
        document.addEventListener('DOMContentLoaded', function() {
            const requestBtn = document.getElementById('request-access-btn');
            if (requestBtn) {
                requestBtn.addEventListener('click', function() {
                    // Redirect to the request access page with the content ID
                    window.location.href = `request_access.php?content_id=<?php echo $content['id'] ?? ''; ?>`;
                });
            }
            
            // Load the YouTube API
            const tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            const firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
        });
    </script>
</body>
</html>
