<?php
session_start();
require_once 'config.php';

// If this is an AJAX request for search
if (isset($_GET['ajax_search'])) {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $searchCondition = '';
    if (!empty($search)) {
        $searchCondition = " WHERE title LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' 
                            OR description LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'
                            OR tags LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'";
    }

    $query = "SELECT * FROM content" . $searchCondition . " ORDER BY upload_date DESC";
    $result = mysqli_query($conn, $query);
    $content = array();

    while ($row = mysqli_fetch_assoc($result)) {
        // For dynamic search: check access and pending status for logged-in user
        $access_status = null;
        $pending_status = null;
        $is_logged_in = false;
        if (isset($_SESSION['user_id'])) {
            $is_logged_in = true;
            $access_status = checkFileAccess($row['id'], $_SESSION['user_id']);
            $pending_status = checkPendingRequest($row['id'], $_SESSION['user_id']);
        }
        $content[] = array(
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'useful_to' => $row['useful_to'],
            'upload_date' => date('F j, Y', strtotime($row['upload_date'])),
            'file_path' => $row['file_path'],
            'price' => $row['price'],
            'view_restricted' => $row['view_restricted'],
            'access_status' => $access_status,
            'pending_status' => $pending_status,
            'is_logged_in' => $is_logged_in
        );
    }

    header('Content-Type: application/json');
    echo json_encode($content);
    exit;
}

// Handle file request submission
if (isset($_POST['request_file']) && isset($_SESSION['user_id'])) {
    $contentId = intval($_POST['content_id']);
    $userId = $_SESSION['user_id'];

    // Check if request already exists
    $checkSql = "SELECT id FROM file_requests WHERE content_id = ? AND user_id = ? AND (status = 'pending' OR status = 'approved')";
    $stmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($stmt, "ii", $contentId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 0) {
        // Create new request
        $sql = "INSERT INTO file_requests (content_id, user_id) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $contentId, $userId);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "Request submitted successfully. Please complete the payment.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error submitting request.";
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "You already have a pending or approved request for this file.";
        $_SESSION['message_type'] = "warning";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle comment submission
if (isset($_POST['add_comment']) && isset($_SESSION['user_id'])) {
    $contentId = intval($_POST['content_id']);
    $comment = trim($_POST['comment']);
    $userId = $_SESSION['user_id'];

    if (!empty($comment)) {
        $sql = "INSERT INTO comments (content_id, user_id, comment) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iis", $contentId, $userId, $comment);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "Comment added successfully.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error adding comment.";
            $_SESSION['message_type'] = "danger";
        }
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "#content-" . $contentId);
    exit();
}

// Function to get comments for a content
function getComments($contentId, $limit = 3)
{
    global $conn;
    $isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $sql = "SELECT c.*, u.username 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.content_id = ?";
    if (!$isAdmin) {
        $sql .= " AND c.is_restricted = 0";
    }
    $sql .= " ORDER BY c.created_at DESC" . ($limit ? " LIMIT " . intval($limit) : "");
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

// Check file access status
function checkFileAccess($contentId, $userId)
{
    global $conn;
    $sql = "SELECT status FROM file_requests WHERE content_id = ? AND user_id = ? AND status = 'approved'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $contentId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['status'];
    }
    return null;
}

// Check for pending file request
function checkPendingRequest($contentId, $userId)
{
    global $conn;
    $sql = "SELECT status FROM file_requests WHERE content_id = ? AND user_id = ? AND status = 'pending'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $contentId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result) ? true : false;
}

// Function to get file type from path
function getFileType($filePath)
{
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    switch (strtolower($extension)) {
        case 'pdf':
            return 'pdf';
        case 'mp4':
        case 'webm':
        case 'ogg':
            return 'video';
        case 'mp3':
        case 'wav':
            return 'audio';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return 'image';
        case 'doc':
        case 'docx':
            return 'doc'; // Group doc and docx
        case 'xls':
        case 'xlsx':
            return 'excel'; // Group xls and xlsx
        case 'ppt':
        case 'pptx':
            return 'ppt'; // Group ppt and pptx
        default:
            return 'unknown';
    }
}

// Initial content load with prices
$query = "SELECT * FROM content ORDER BY upload_date DESC";
$result = mysqli_query($conn, $query);

// Get user info if logged in
$userInfo = null;
if (isset($_SESSION['user_id'])) {
    $userQuery = "SELECT username, role FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $userQuery);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $userResult = mysqli_stmt_get_result($stmt);
    $userInfo = mysqli_fetch_assoc($userResult);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Viewer</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .auth-buttons {
            position: fixed;
            right: 20px;
            top: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }

        .search-container {
            margin: 80px 0 20px 0;
        }

        .content-list {
            margin-top: 20px;
        }

        .content-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px;
            transition: transform 0.2s;
        }

        .content-item:hover {
            transform: translateX(10px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logout-btn {
            color: #dc3545;
            text-decoration: none;
        }

        .logout-btn:hover {
            color: #bb2d3b;
        }

        #loading {
            display: none;
            margin-left: 10px;
        }

        .no-results {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        #paymentModal .modal-body {
            text-align: center;
        }

        #paymentModal img {
            max-width: 300px;
            margin: 20px auto;
        }

        .timer {
            font-size: 1.2em;
            margin: 15px 0;
            color: #6c757d;
        }

        .payment-buttons {
            margin-top: 20px;
        }

        .payment-status {
            margin-top: 15px;
            font-style: italic;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Auth Buttons -->
        <div class="auth-buttons d-flex align-items-center">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-info d-flex align-items-center">
                    <span class="me-2">Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm" 
                       style="border-radius: 20px; padding: 5px 15px; font-weight: 500; border-width: 2px;">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            <?php else: ?>
                <a href="login.html" class="btn btn-outline-primary me-3" 
                   style="border-radius: 20px; padding: 8px 20px; font-weight: 500; border-width: 2px;">
                    <i class="fas fa-sign-in-alt me-1"></i> Login
                </a>
                <a href="register.html" class="btn btn-primary" 
                   style="border-radius: 20px; padding: 8px 20px; font-weight: 500; background: linear-gradient(135deg, #4a6cf7, #2541b2); border: none; box-shadow: 0 2px 8px rgba(74, 108, 247, 0.3);">
                    <i class="fas fa-user-plus me-1"></i> Get Started
                </a>
            <?php endif; ?>
        </div>

        <!-- Modern Search Bar -->
        <div class="search-container mb-5">
            <div class="position-relative">
                <input type="text" 
                       class="form-control form-control-lg shadow-sm border-0 rounded-pill ps-4 py-3" 
                       id="searchInput" 
                       placeholder="Search content..."
                       style="background: #f8f9fa; color: #333; border: 2px solid #e0e0e0; transition: all 0.3s ease;">
                <button class="btn btn-primary position-absolute end-0 top-0 h-100 rounded-pill px-4" 
                        type="button" 
                        id="searchButton"
                        style="border-top-left-radius: 0 !important; border-bottom-left-radius: 0 !important; background: linear-gradient(135deg, #4a6cf7, #2541b2); border: none; font-weight: 500;">
                    <i class="fas fa-search me-2"></i> Search
                </button>
            </div>
        </div>

        <!-- Content List -->
        <?php
        // 1. Group content by type
        mysqli_data_seek($result, 0); // Reset result pointer
        $content_by_type = [
            'video' => [],
            'pdf' => [],
            'image' => [],
            'doc' => [],
            'excel' => [],
            'audio' => [],
            'ppt' => []
        ];
        while ($row = mysqli_fetch_assoc($result)) {
            $type = getFileType($row['file_path']);
            if (isset($content_by_type[$type])) {
                $content_by_type[$type][] = $row;
            }
        }
        // 2. Sort types by number of files (descending)
        uasort($content_by_type, function ($a, $b) {
            return count($b) - count($a);
        });
        // 3. Section titles
        $section_titles = [
            'video' => 'Videos',
            'pdf' => 'PDFs',
            'image' => 'Images',
            'doc' => 'Documents',
            'excel' => 'Excel Files',
            'audio' => 'Audio Files',
            'ppt' => 'Presentations'
        ];
        ?>
        <div id="contentSections" class="mt-4">
            <?php foreach ($content_by_type as $type => $items): ?>
                <?php if (count($items) === 0)
                    continue; ?>
                <section class="mb-5">
                    <div class="d-flex align-items-center mb-4">
                        <h3 class="section-title mb-0"
                            style="font-size:1.8rem;font-weight:700;letter-spacing:-0.5px;line-height:1.2;color:#2c3e50;text-transform:uppercase;position:relative;display:inline-block;padding-bottom:10px;">
                            <span style="position:relative;z-index:1;background:linear-gradient(90deg, #3498db, #2ecc71);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
                                <?php echo $section_titles[$type]; ?>
                            </span>
                            <span style="position:absolute;bottom:0;left:0;width:60px;height:4px;background:linear-gradient(90deg, #3498db, #2ecc71);border-radius:2px;"></span>
                        </h3>
                    </div>
                    <div class="slider-container">
                        <button class="slider-btn slider-btn-left"
                            onclick="slideLeft('<?php echo $type; ?>')">&#8249;</button>
                        <div class="slider-track" id="slider-track-<?php echo $type; ?>">
                            <?php foreach ($items as $row): ?>
                                <div class="slider-card">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($row['title']); ?></h5>
                                    <div class="price-tag"
                                        style="display:inline-block;padding:4px 18px;border-radius:18px;background:#28a745;color:#fff;font-weight:600;font-size:1.15em;margin-bottom:8px;min-width:0;max-width:100%;">
                                        ₹<?php echo number_format($row['price'], 2); ?></div>
                                    <p class="text-muted mb-1" style="font-size:0.95em;">
                                        <?php echo htmlspecialchars($row['description']); ?>
                                    </p>
                                    <?php if (!empty($row['useful_to'])): ?>
                                        <p class="mb-1"><strong>Useful to:</strong>
                                            <?php echo htmlspecialchars($row['useful_to']); ?></p>
                                    <?php endif; ?>
                                    <p class="text-muted mb-2" style="font-size:0.85em;">
                                        <small>Uploaded: <?php echo date('F j, Y', strtotime($row['upload_date'])); ?></small>
                                    </p>
                                    <div class="action-buttons d-flex flex-wrap gap-2 mt-3">
                                        <?php if (!$row['view_restricted']): ?>
                                            <?php if ($type === 'audio'): ?>
                                                <a href="audio_viewer.php?file=<?php echo urlencode($row['file_path']); ?>"
                                                    class="btn btn-info btn-sm" target="_blank">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php elseif ($type === 'ppt' && strtolower(pathinfo($row['file_path'], PATHINFO_EXTENSION)) === 'pptx'): ?>
                                                <a href="pptx_preview_enhanced.php?file=<?php echo urlencode($row['file_path']); ?>"
                                                    class="btn btn-info btn-sm" target="_blank">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php else: ?>
                                                <a href="viewer.php?id=<?php echo $row['id']; ?>" 
                                                   class="btn btn-primary btn-sm d-flex align-items-center justify-content-center"
                                                   target="_blank"
                                                   style="min-width: 100px; border-radius: 8px; background: linear-gradient(135deg, #3498db, #2ecc71); border: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm d-flex align-items-center justify-content-center" 
                                                    disabled
                                                    style="min-width: 100px; border-radius: 8px; opacity: 0.7; cursor: not-allowed;">
                                                <i class="fas fa-eye-slash me-1"></i> Restricted
                                            </button>
                                        <?php endif; ?>
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <?php
                                            $accessStatus = checkFileAccess($row['id'], $_SESSION['user_id']);
                                            $pendingStatus = checkPendingRequest($row['id'], $_SESSION['user_id']);
                                            if ($accessStatus === 'approved'):
                                                ?>
                                                <a href="<?php echo htmlspecialchars($row['file_path']); ?>"
                                                    class="btn btn-success btn-sm d-flex align-items-center justify-content-center" 
                                                    download
                                                    style="min-width: 120px; border-radius: 8px; background: linear-gradient(135deg, #2ecc71, #27ae60); border: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                                    <i class="fas fa-download me-1"></i> Download
                                                </a>
                                            <?php elseif ($pendingStatus): ?>
                                                <button class="btn btn-warning btn-sm d-flex align-items-center justify-content-center" 
                                                        disabled
                                                        style="min-width: 100px; border-radius: 8px; background: linear-gradient(135deg, #f39c12, #e67e22); color: #fff; border: none; opacity: 0.8;">
                                                    <i class="fas fa-clock me-1"></i> Pending
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-primary btn-sm d-flex align-items-center justify-content-center"
                                                        onclick="showPaymentModal(<?php echo $row['id']; ?>, <?php echo $row['price']; ?>)"
                                                        style="min-width: 120px; border-radius: 8px; background: linear-gradient(135deg, #9b59b6, #8e44ad); border: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                                    <i class="fas fa-shopping-cart me-1"></i> Request
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn btn-outline-primary btn-sm d-flex align-items-center justify-content-center" 
                                                    onclick="window.location.href='login.html'"
                                                    style="min-width: 140px; border-radius: 8px; border: 1px solid #3498db; color: #3498db;">
                                                <i class="fas fa-lock me-1"></i> Login to Access
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-outline-primary btn-sm comment-btn d-flex align-items-center justify-content-center" 
                                                data-content-id="<?php echo $row['id']; ?>"
                                                style="min-width: 120px; border-radius: 8px; border: 1px solid #3498db; color: #3498db;">
                                            <i class="far fa-comment me-1"></i> Comments
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="slider-btn slider-btn-right"
                            onclick="slideRight('<?php echo $type; ?>')">&#8250;</button>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
        <style>
            .slider-container {
                position: relative;
                width: 100%;
                overflow: visible;
                margin-bottom: 10px;
                padding: 0 40px;
            }

            .slider-track {
                display: flex;
                gap: 20px;
                transition: transform 0.4s cubic-bezier(.4, 2.2, .6, 1);
                will-change: transform;
                margin-left: 16px;
            }

            .slider-card {
                background: #fff;
                border-radius: 15px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.09);
                min-width: 310px;
                max-width: 310px;
                padding: 24px 24px 18px 24px;
                margin: 18px 0;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                border: 2px solid #000;
                /* Black border for every card */
            }

            .slider-btn {
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                background: #3a7afe;
                color: #fff;
                border: none;
                border-radius: 50%;
                width: 36px;
                height: 36px;
                font-size: 1.5em;
                cursor: pointer;
                z-index: 2;
                transition: background 0.2s;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            }

            .slider-btn-left {
                left: -18px;
            }

            .slider-btn-right {
                right: -18px;
            }

            .slider-btn-left {
                left: 0;
            }

            .slider-btn-right {
                right: 0;
            }

            .slider-btn:active {
                background: #2956b2;
            }

            @media (max-width: 600px) {
                .slider-card {
                    min-width: 90vw;
                    max-width: 90vw;
                }
            }
        </style>
        <script>
            // Function to show comments modal and load comments
            async function showCommentsModal(contentId) {
                const modal = new bootstrap.Modal(document.getElementById('commentsModal'));
                const modalBody = document.getElementById('commentsModalBody');
                const commentForm = document.getElementById('commentForm');
                const commentContentId = document.getElementById('commentContentId');
                
                // Set the content ID in the form
                commentContentId.value = contentId;
                
                // Show loading state
                modalBody.innerHTML = `
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading comments...</p>
                    </div>`;
                
                // Show the modal
                modal.show();
                
                try {
                    // Fetch comments
                    const response = await fetch(`get_comments.php?content_id=${contentId}`);
                    const data = await response.json();
                    
                    if (data.comments && data.comments.length > 0) {
                        let commentsHtml = '<div class="comments-list" style="max-height: 300px; overflow-y: auto;">';
                        data.comments.forEach(comment => {
                            commentsHtml += `
                                <div class="card mb-2">
                                    <div class="card-body p-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h6 class="card-subtitle mb-1 text-muted">${escapeHtml(comment.username)}</h6>
                                            <small class="text-muted">${new Date(comment.created_at).toLocaleString()}</small>
                                        </div>
                                        <p class="card-text mb-0">${escapeHtml(comment.comment)}</p>
                                    </div>
                                </div>`;
                        });
                        commentsHtml += '</div>';
                        modalBody.innerHTML = commentsHtml;
                    } else {
                        modalBody.innerHTML = '<p class="text-muted text-center">No comments yet. Be the first to comment!</p>';
                    }
                } catch (error) {
                    console.error('Error loading comments:', error);
                    modalBody.innerHTML = '<p class="text-danger">Failed to load comments. Please try again later.</p>';
                }
                
                // Handle comment form submission
                if (commentForm) {
                    commentForm.onsubmit = async function(e) {
                        e.preventDefault();
                        const commentText = document.getElementById('commentText').value.trim();
                        if (!commentText) return;
                        
                        try {
                            const response = await fetch('add_comment.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `content_id=${encodeURIComponent(contentId)}&comment=${encodeURIComponent(commentText)}`
                            });
                            
                            const result = await response.json();
                            if (result.success) {
                                // Reload comments after successful submission
                                document.getElementById('commentText').value = '';
                                showCommentsModal(contentId);
                            } else {
                                alert(result.message || 'Failed to post comment');
                            }
                        } catch (error) {
                            console.error('Error posting comment:', error);
                            alert('Failed to post comment. Please try again.');
                        }
                    };
                }
            }

            // Simple slider logic for each section
            function slideLeft(type) {
                const track = document.getElementById('slider-track-' + type);
                const card = track.querySelector('.slider-card');
                if (!card) return;
                const cardWidth = card.offsetWidth + 20; // card + gap
                track.scrollLeft = (track.scrollLeft || 0) - cardWidth * 2;
            }

            function slideRight(type) {
                const track = document.getElementById('slider-track-' + type);
                const card = track.querySelector('.slider-card');
                if (!card) return;
                const cardWidth = card.offsetWidth + 20;
                track.scrollLeft = (track.scrollLeft || 0) + cardWidth * 2;
            }
            // Make slider-track scrollable
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.slider-track').forEach(function (track) {
                    track.style.overflowX = 'auto';
                    track.style.scrollBehavior = 'smooth';
                });
            });

            // --- DYNAMIC CLIENT-SIDE SEARCH FOR GROUPED CONTENT SECTIONS ---
            document.addEventListener('DOMContentLoaded', function () {
                const searchInput = document.getElementById('searchInput');
                if (!searchInput) return;
                searchInput.addEventListener('input', function () {
                    const keyword = searchInput.value.trim().toLowerCase();
                    let anySectionVisible = false;
                    document.querySelectorAll('#contentSections section').forEach(section => {
                        let anyVisible = false;
                        section.querySelectorAll('.slider-card').forEach(card => {
                            // Only check title, description, and useful_to fields
                            const title = card.querySelector('h5')?.innerText.toLowerCase() || '';
                            const desc = card.querySelector('p.text-muted.mb-1')?.innerText.toLowerCase() || '';
                            let useful = '';
                            const usefulElem = Array.from(card.querySelectorAll('p')).find(p => p.innerText.trim().toLowerCase().startsWith('useful to:'));
                            if (usefulElem) useful = usefulElem.innerText.toLowerCase();
                            if (
                                title.includes(keyword) ||
                                desc.includes(keyword) ||
                                useful.includes(keyword)
                            ) {
                                card.style.display = '';
                                anyVisible = true;
                            } else {
                                card.style.display = 'none';
                            }
                        });
                        // Hide section if no visible cards
                        section.style.display = anyVisible ? '' : 'none';
                        if (anyVisible) anySectionVisible = true;
                    });
                    // If no sections visible, show message
                    let noResultMsg = document.getElementById('noResultMsg');
                    if (!anySectionVisible) {
                        if (!noResultMsg) {
                            noResultMsg = document.createElement('div');
                            noResultMsg.id = 'noResultMsg';
                            noResultMsg.className = 'text-center text-muted py-5';
                            noResultMsg.innerHTML = '<h4>No content found.</h4>';
                            document.getElementById('contentSections').appendChild(noResultMsg);
                        }
                    } else if (noResultMsg) {
                        noResultMsg.remove();
                    }
                });
            });
            // --- END DYNAMIC CLIENT-SIDE SEARCH ---

            // Dynamic search and re-render
            document.addEventListener('DOMContentLoaded', function () {
                const searchInput = document.querySelector('input[name="search"]');
                if (!searchInput) return;
                let searchTimeout = null;
                searchInput.addEventListener('input', function () {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function () {
                        fetchContentSections(searchInput.value.trim());
                    }, 300);
                });
            });

            function fetchContentSections(query) {
                const loading = document.getElementById('loading');
                if (loading) loading.style.display = 'block';
                fetch('?ajax_search=1&search=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        renderContentSections(data);
                        if (loading) loading.style.display = 'none';
                    })
                    .catch(() => {
                        if (loading) loading.style.display = 'none';
                    });
            }

            function renderContentSections(content) {
                // Group by type
                const sectionTitles = {
                    video: 'Videos',
                    pdf: 'PDFs',
                    image: 'Images',
                    doc: 'Documents',
                    excel: 'Excel Files',
                    audio: 'Audio Files',
                    ppt: 'Presentations'
                };
                const contentByType = {
                    video: [],
                    pdf: [],
                    image: [],
                    doc: [],
                    excel: [],
                    audio: [],
                    ppt: []
                };
                content.forEach(row => {
                    const fileExtension = row.file_path.split('.').pop().toLowerCase();
                    let type = 'unknown';
                    if (['pdf'].includes(fileExtension)) type = 'pdf';
                    else if (['mp4', 'webm', 'ogg'].includes(fileExtension)) type = 'video';
                    else if (['mp3', 'wav'].includes(fileExtension)) type = 'audio';
                    else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) type = 'image';
                    else if (['doc', 'docx'].includes(fileExtension)) type = 'doc'; // Group doc and docx
                    else if (['xls', 'xlsx'].includes(fileExtension)) type = 'excel'; // Group xls and xlsx
                    else if (['ppt', 'pptx'].includes(fileExtension)) type = 'ppt'; // Group ppt and pptx
                    if (contentByType[type]) contentByType[type].push(row);
                });
                // Sort by count
                const sortedTypes = Object.keys(contentByType).sort((a, b) => contentByType[b].length - contentByType[a].length);
                let html = '';
                let found = false;
                sortedTypes.forEach(type => {
                    const items = contentByType[type];
                    if (items.length === 0) return;
                    found = true;
                    html += `<section class="mb-5">
                 
                    <div class="slider-container">
                        <button class="slider-btn slider-btn-left" onclick="slideLeft('${type}')">&#8249;</button>
                        <div class="slider-track" id="slider-track-${type}">
                `;
                    items.forEach(row => {
                        const previewFile = row.converted_file_path || row.file_path;
                        const fileType = getFileType(row.file_path);

                        let previewHtml = '';
                        if (fileType === 'image') {
                            previewHtml = `<img src="${escapeHtml(previewFile)}" alt="Preview" class="img-fluid" style="max-width:100%;max-height:200px;">`;
                        } else if (fileType === 'video') {
                            if (row.video_type === 'youtube') {
                                // Extract YouTube video ID from URL
                                const youtubeId = row.file_path.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]{11})/)?.[1] || '';
                                previewHtml = `
    <div class="youtube-preview-container" style="position:relative;max-width:100%;max-height:200px;" data-video-id="${youtubeId}" data-content-id="${row.id}">
        <div id="youtube-preview-${row.id}" style="width:100%;height:200px;"></div>
        <div class="youtube-overlay" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:2;cursor:not-allowed;background:rgba(0,0,0,0.5);"></div>
        <div class="youtube-controls" style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.7);padding:8px;display:flex;justify-content:space-between;align-items:center;z-index:3;">
            <div class="youtube-time" style="color:white;font-size:12px;">0:00 / 0:30</div>
            <div class="youtube-progress" style="flex-grow:1;height:4px;background:rgba(255,255,255,0.3);margin:0 10px;position:relative;cursor:pointer;">
                <div class="youtube-progress-filled" style="position:absolute;top:0;left:0;height:100%;width:0%;background:#ff0000;"></div>
            </div>
            <button class="youtube-play-pause" style="background:none;border:none;color:white;cursor:pointer;font-size:16px;">
                <i class="fas fa-pause"></i>
            </button>
        </div>
    </div>
    <div id="youtube-msg-${row.id}" class="text-danger small mt-1">Preview limited to 30 seconds. Please request access to view full video.</div>`;
                            } else {
                                // Local video preview
                                previewHtml = `
    <div class="video-preview-container" style="position:relative;max-width:100%;max-height:200px;" data-content-id="${row.id}">
        <video id="video-preview-${row.id}" src="${escapeHtml(previewFile)}" style="width:100%;max-height:200px;display:block;"></video>
        <div class="video-controls" style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.7);padding:8px;display:flex;justify-content:space-between;align-items:center;z-index:3;">
            <div class="video-time" style="color:white;font-size:12px;">0:00 / 0:30</div>
            <div class="video-progress" style="flex-grow:1;height:4px;background:rgba(255,255,255,0.3);margin:0 10px;position:relative;cursor:pointer;">
                <div class="video-progress-filled" style="position:absolute;top:0;left:0;height:100%;width:0%;background:#ff0000;"></div>
            </div>
            <button class="video-play-pause" style="background:none;border:none;color:white;cursor:pointer;font-size:16px;">
                <i class="fas fa-play"></i>
            </button>
        </div>
        <div class="video-overlay" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:2;cursor:not-allowed;display:none;"></div>
    </div>
    <div id="video-msg-${row.id}" class="text-danger small mt-1">Preview limited to 30 seconds. Please request access to view full video.</div>`;
                            }
                        } else if (fileType === 'excel') {
                            previewHtml = `<div id="office-preview-${row.id}" style="width:100%;max-width:320px;height:220px;overflow:auto;border:1px solid #ccc;"></div><button class="btn btn-outline-info btn-sm mt-1" onclick="fetchAndPreviewOfficeFile('${row.id}', '${escapeHtml(previewFile)}', 'excel')">Preview</button>`;
                        } else if (fileType === 'docx') {
                            previewHtml = `<div id="office-preview-${row.id}" style="width:100%;max-width:320px;height:220px;overflow:auto;border:1px solid #ccc;"></div><button class="btn btn-outline-info btn-sm mt-1" onclick="fetchAndPreviewOfficeFile('${row.id}', '${escapeHtml(previewFile)}', 'docx')">Preview</button>`;
                        } else if (fileType === 'ppt' || fileType === 'pptx') {
                            previewHtml = `<div class="text-warning">Preview not supported for PowerPoint files. Please upload a PDF version for preview.</div>`;
                        } else if (["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx"].includes(fileType)) {
                            previewHtml = `<div id="pdf-preview-ajax-${row.id}" style="width:100%;max-width:320px;height:220px;overflow:auto;border:1px solid #ccc;"></div><div class="text-danger small mt-1">Preview limited. Please request access to view full content.</div>`;
                        } else {
                            previewHtml = '<div class="text-muted">Preview not available for this file type.</div>';
                        }

                        html += `<div class="slider-card">
                        <h5 class="mb-1">${escapeHtml(row.title)}</h5>
                        <div class="price-tag" style="display:inline-block;padding:4px 18px;border-radius:18px;background:#28a745;color:#fff;font-weight:600;font-size:1.15em;margin-bottom:8px;min-width:0;max-width:100%;">₹${parseFloat(row.price).toFixed(2)}</div>
                        <p class="text-muted mb-1" style="font-size:0.95em;">${escapeHtml(row.description)}</p>
                        ${row.useful_to ? `<p class="mb-1"><strong>Useful to:</strong> ${escapeHtml(row.useful_to)}</p>` : ''}
                        <p class="text-muted mb-2" style="font-size:0.85em;"><small>Uploaded: ${escapeHtml(row.upload_date)}</small></p>
                        <div class="preview-container mb-2">
                            ${previewHtml}
                        </div>
                        <div class="action-buttons d-flex flex-column gap-2 mt-2">
                            ${!row.view_restricted ? `<a href="viewer.php?id=${row.id}" class="btn btn-info btn-sm" target="_blank"><i class="fas fa-eye"></i> View</a>` : `<button class="btn btn-secondary btn-sm" disabled><i class="fas fa-eye-slash"></i> View Restricted</button>`}
                            ${row.access_status === 'approved' ? `<a href="${escapeHtml(row.file_path)}" class="btn btn-primary btn-sm" download><i class="fas fa-download"></i> Download</a>` : row.pending_status ? `<button class="btn btn-warning btn-sm" disabled><i class="fas fa-clock"></i> Pending</button>` : row.is_logged_in ? `<button class="btn btn-primary btn-sm" onclick="showPaymentModal(${row.id}, ${row.price})"><i class="fas fa-shopping-cart"></i> Request</button>` : `<button class="btn btn-secondary btn-sm" onclick="window.location.href='login.html'"><i class="fas fa-lock"></i> Login to Access</button>`}
                            <button class="btn btn-outline-secondary btn-sm mt-1" id="show-comments-${row.id}">
                                <i class="fas fa-comments"></i> Comments
                            </button>
                        </div>
                    </div>`;
                    });
                    html += `</div>
                        <button class="slider-btn slider-btn-right" onclick="slideRight('${type}')">&#8250;</button>
                    </div>
                </section>`;
                });
                if (!found) {
                    html = '<div class="text-center text-muted py-5"><h4>No content found.</h4></div>';
                }
                document.getElementById('contentSections').innerHTML = html;
                // Re-enable slider scroll
                document.querySelectorAll('.slider-track').forEach(function (track) {
                    track.style.overflowX = 'auto';
                    track.style.scrollBehavior = 'smooth';
                });
                // Re-initialize PDF.js and video listeners for newly rendered content
                document.querySelectorAll('[id^="pdf-preview-ajax-"]').forEach(function (container) {
                    const contentId = container.id.replace('pdf-preview-ajax-', '');
                    const rowData = content.find(item => item.id == contentId);
                    if (rowData && window.pdfjsLib) {
                        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                        pdfjsLib.getDocument(rowData.converted_file_path || rowData.file_path).promise.then(function (pdf) {
                            var total = pdf.numPages;
                            var showPages = Math.max(1, Math.floor(total * 0.1));

                            function renderPage(num) {
                                pdf.getPage(num).then(function (page) {
                                    var canvas = document.createElement('canvas');
                                    var ctx = canvas.getContext('2d');
                                    var viewport = page.getViewport({
                                        scale: 0.8
                                    });
                                    canvas.height = viewport.height;
                                    canvas.width = viewport.width;
                                    page.render({
                                        canvasContext: ctx,
                                        viewport: viewport
                                    }).promise.then(function () {
                                        container.appendChild(canvas);
                                        if (num < showPages) renderPage(num + 1);
                                    });
                                });
                            }
                            container.innerHTML = ''; // Clear existing content
                            renderPage(1);
                        });
                    }
                });
                document.querySelectorAll('[id^="video-preview-ajax-"]').forEach(function (vid) {
                    const contentId = vid.id.replace('video-preview-ajax-', '');
                    const msg = document.getElementById('video-msg-ajax-' + contentId);
                    if (!vid || !msg) return;
                    vid.addEventListener('loadedmetadata', function () {
                        var maxTime = vid.duration * 0.15;
                        vid.addEventListener('timeupdate', function () {
                            if (vid.currentTime >= maxTime) {
                                vid.pause();
                                vid.currentTime = maxTime;
                                msg.style.display = 'block';
                            }
                        });
                        vid.addEventListener('seeking', function () {
                            if (vid.currentTime > maxTime) {
                                vid.currentTime = maxTime;
                            }
                        });
                    });
                });
            }

            // Pure client-side, real-time, case-insensitive card filtering (only title, description, useful_to)
            document.addEventListener('DOMContentLoaded', function () {
                // Initialize video players
                initLocalVideoPlayers();
                
                // Initialize comment button click handlers
                document.querySelectorAll('.comment-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const contentId = this.dataset.contentId;
                        showCommentsModal(contentId);
                    });
                });

                const searchInput = document.querySelector('input[name="search"]');
                if (!searchInput) return;
                searchInput.addEventListener('input', function () {
                    const keyword = searchInput.value.trim().toLowerCase();
                    let anySectionVisible = false;
                    document.querySelectorAll('#contentSections section').forEach(section => {
                        let anyVisible = false;
                        section.querySelectorAll('.slider-card').forEach(card => {
                            // Only check title, description, and useful_to fields
                            const title = card.querySelector('h5')?.innerText.toLowerCase() || '';
                            const desc = card.querySelector('p.text-muted.mb-1')?.innerText.toLowerCase() || '';
                            let useful = '';
                            const usefulElem = Array.from(card.querySelectorAll('p')).find(p => p.innerText.trim().toLowerCase().startsWith('useful to:'));
                            if (usefulElem) useful = usefulElem.innerText.toLowerCase();
                            if (
                                title.includes(keyword) ||
                                desc.includes(keyword) ||
                                useful.includes(keyword)
                            ) {
                                card.style.display = '';
                                anyVisible = true;
                            } else {
                                card.style.display = 'none';
                            }
                        });
                        // Hide section if no visible cards
                        section.style.display = anyVisible ? '' : 'none';
                        if (anyVisible) anySectionVisible = true;
                    });
                    // If no sections visible, show message
                    let noResultMsg = document.getElementById('noResultMsg');
                    if (!anySectionVisible) {
                        if (!noResultMsg) {
                            noResultMsg = document.createElement('div');
                            noResultMsg.id = 'noResultMsg';
                            noResultMsg.className = 'text-center text-muted py-5';
                            noResultMsg.innerHTML = '<h4>No content found.</h4>';
                            document.getElementById('contentSections').appendChild(noResultMsg);
                        }
                    } else if (noResultMsg) {
                        noResultMsg.remove();
                    }
                });
            });

            // HTML escape helper
            function escapeHtml(text) {
                if (!text) return '';
                return text.replace(/[&<>"]/g, function (c) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;'
                    }[c];
                });
            }
        </script>
    </div>

    

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Payment</h5>
                </div>
                <div class="modal-body">
                    <p>Scan the QR code to pay:</p>
                    <img src="qr_code.JPG" alt="Payment QR Code" class="img-fluid">
                    <div class="timer" id="paymentTimer">Please wait: 10s</div>
                    <div class="payment-buttons">
                        <button id="paidButton" class="btn btn-success" disabled>
                            <i class="fas fa-check"></i> I have paid
                        </button>
                    </div>
                    <div class="payment-status" id="paymentStatus" style="display: none;">
                        Wait for your request to be approved
                    </div>
                </div>
                <div class="modal-footer" style="display: none;" id="modalFooter">
                    <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add PDF.js CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf_viewer.min.css" />
    <!-- Add SheetJS and Mammoth.js for Office previews -->
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/xlsx.full.min.js"></script>
    <script src="https://unpkg.com/mammoth/mammoth.browser.min.js"></script>
    <script>
        function fetchAndPreviewOfficeFile(id, fileUrl, fileType) {
            const container = document.getElementById('office-preview-' + id);
            container.innerHTML = '<div class="text-center text-muted">Loading preview...</div>';
            fetch(fileUrl)
                .then(res => res.arrayBuffer())
                .then(data => {
                    if (fileType === 'excel') {
                        const workbook = XLSX.read(new Uint8Array(data), {
                            type: "array"
                        });
                        let html = '';
                        workbook.SheetNames.forEach(sheetName => {
                            const sheet = workbook.Sheets[sheetName];
                            html += `<h5>${sheetName}</h5>`;
                            html += XLSX.utils.sheet_to_html(sheet);
                        });
                        container.innerHTML = html;
                    } else if (fileType === 'docx') {
                        mammoth.convertToHtml({
                            arrayBuffer: data
                        })
                            .then(result => {
                                container.innerHTML = result.value;
                            })
                            .catch(() => {
                                container.innerHTML = '<div class="text-danger">Failed to load DOCX preview.</div>';
                            });
                    }
                })
                .catch(() => {
                    container.innerHTML = '<div class="text-danger">Failed to load file for preview.</div>';
                });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        const contentList = document.getElementById('contentList');
        const loading = document.getElementById('loading');
        const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

        function debounce(func, wait) {
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(searchTimeout);
                    func(...args);
                };
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(later, wait);
            };
        }

        function createContentItem(item) {
            const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
            const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;

            let viewButton = item.view_restricted ?
                `<button class="btn btn-secondary" disabled>
                    <i class="fas fa-eye-slash"></i> View Restricted
                </button>` :
                `<a href="viewer.php?id=${item.id}" class="btn btn-info" target="_blank">
                    <i class="fas fa-eye"></i> View
                </a>`;

            let accessButton = isLoggedIn ?
                `<button class="btn btn-primary" onclick="showPaymentModal(${item.id}, ${item.price})">
                    <i class="fas fa-shopping-cart"></i> Request
                </button>` :
                `<button class="btn btn-secondary" onclick="window.location.href='login.html'">
                    <i class="fas fa-lock"></i> Login to Access
                </button>`;

            return `
                <div class="content-item">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4>${escapeHtml(item.title)}</h4>
                            <div class="price-tag">₹${parseFloat(item.price).toFixed(2)}</div>
                            <p class="text-muted mb-2">${escapeHtml(item.description)}</p>
                            ${item.useful_to ? `<p class="mb-2"><strong>Useful to:</strong> ${escapeHtml(item.useful_to)}</p>` : ''}
                            <p class="text-muted mb-0">
                                <small>Uploaded: ${item.upload_date}</small>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="action-buttons justify-content-end">
                                ${viewButton}
                                ${accessButton}
                            </div>
                        </div>
                    </div>
                    
                    
                        
                        ${isLoggedIn ? `
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="content_id" value="${item.id}">
                                <div class="input-group">
                                    <input type="text" name="comment" class="form-control" placeholder="Add a comment..." required>
                                        <button type="submit" name="add_comment" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Post
                                        </button>
                                </div>
                            </form>
                        ` : `
                          
                        
                    </div>
                </div>
            `;
        }

        function createCommentsSection(contentId, commentsData) {
            const comments = commentsData.comments || [];
            const total = commentsData.total || 0;

            if (comments.length === 0) {
                return '<p class="text-muted"><small>No comments yet.</small></p>';
            }

            let html = comments.map(comment => `
                <div class="comment-item mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${escapeHtml(comment.username)}</strong>
                            <small class="text-muted ms-2">
                                ${new Date(comment.created_at).toLocaleString()}
                            </small>
                        </div>
                    </div>
                    <p class="mb-0">${escapeHtml(comment.comment)}</p>
                </div>
            `).join('');

            if (total > comments.length) {
                html += `<button class="btn btn-link btn-sm mt-2 show-all-comments-btn" data-content-id="${contentId}">Show all ${total} comments</button>`;
            }

            return html;
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        const performSearch = debounce((searchTerm) => {
            loading.style.display = 'block';

            fetch(`index.php?ajax_search=1&search=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    contentList.innerHTML = data.length ?
                        data.map(item => createContentItem(item)).join('') :
                        '<div class="no-results">No content found matching your search.</div>';
                })
                .catch(error => {
                    console.error('Error:', error);
                    contentList.innerHTML = '<div class="no-results">An error occurred while searching. Please try again.</div>';
                })
                .finally(() => {
                    loading.style.display = 'none';
                });
        }, 300);

        searchInput.addEventListener('input', (e) => {
            performSearch(e.target.value);
        });

        document.getElementById('contentSections').addEventListener('click', function(e) {
            if (e.target.classList.contains('show-all-comments-btn')) {
                e.preventDefault();
                const contentId = e.target.dataset.contentId;
                if (contentId) {
                    showAllComments(contentId);
                }
            }
        });

        document.getElementById('contentSections').addEventListener('click', function(e) {
            if (e.target.classList.contains('show-all-comments-btn')) {
                e.preventDefault();
                const contentId = e.target.dataset.contentId;
                showAllComments(contentId);

            }
        });

        function showAllComments(contentId) {
            const modal = new bootstrap.Modal(document.getElementById('commentsModal'));
            const modalBody = document.getElementById('commentsModalBody');
            const modalTitle = document.getElementById('commentsModalLabel');

            modalTitle.textContent = 'All Comments';
            modalBody.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            modal.show();

            fetch(`get_comments.php?content_id=${contentId}`)
                .then(response => response.json())
                .then(data => {
                    let commentsHtml = '';
                    if (data.comments && data.comments.length > 0) {
                        data.comments.forEach(comment => {
                            commentsHtml += `
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <p class="card-text">${escapeHtml(comment.comment)}</p>
                                        <footer class="blockquote-footer">${escapeHtml(comment.username)} on ${new Date(comment.created_at).toLocaleString()}</footer>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        commentsHtml = '<p>No comments to display.</p>';
                    }
                    modalBody.innerHTML = commentsHtml;
                })
                .catch(error => {
                    console.error('Error fetching comments:', error);
                    modalBody.innerHTML = '<p class="text-danger">Could not load comments.</p>';
                });
        }

        let currentContentId = null;
        let paymentTimer = null;

        function closePaymentModal() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
            if (modal) {
                modal.hide();
                if (paymentTimer) {
                    clearInterval(paymentTimer);
                }
            }
        }

        function showPaymentModal(contentId, price) {
            currentContentId = contentId;
            const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            const paidButton = document.getElementById('paidButton');
            const timerElement = document.getElementById('paymentTimer');
            const statusElement = document.getElementById('paymentStatus');
            const modalFooter = document.getElementById('modalFooter');

            // Reset modal state
            paidButton.disabled = true;
            statusElement.style.display = 'none';
            paidButton.style.display = 'block';
            modalFooter.style.display = 'none';

            // Start timer
            let timeLeft = 10;
            paymentTimer = setInterval(() => {
                timeLeft--;
                timerElement.textContent = `Please wait: ${timeLeft}s`;

                if (timeLeft <= 0) {
                    clearInterval(paymentTimer);
                    paidButton.disabled = false;
                    timerElement.textContent = 'You can now confirm your payment';
                    modalFooter.style.display = 'flex';
                }
            }, 1000);

            modal.show();



        // Handle paid button click
            paidButton.onclick = () => {
                paidButton.style.display = 'none';
                statusElement.style.display = 'block';

                // Submit the request
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="request_file" value="1">
                    <input type="hidden" name="content_id" value="${contentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            };
        }

     
        
        function renderOfficePreview(fileUrl, fileType, containerId) {
            const container = document.getElementById(containerId);
            container.innerHTML = '<div class="text-center text-muted">Loading preview...</div>';
            if (fileType === 'excel') {
                fetch(fileUrl)
                    .then(res => res.arrayBuffer())
                    .then(data => {
                        const workbook = XLSX.read(new Uint8Array(data), {
                            type: "array"
                        });
                        let html = '';
                        workbook.SheetNames.forEach(sheetName => {
                            const sheet = workbook.Sheets[sheetName];
                            html += `<h5>${sheetName}</h5>`;
                            html += XLSX.utils.sheet_to_html(sheet);
                        });
                        container.innerHTML = html;
                    })
                    .catch(() => {
                        container.innerHTML = '<div class="text-danger">Failed to load Excel preview.</div>';
                    });
            } else if (fileType === 'docx') {
                fetch(fileUrl)
                    .then(res => res.arrayBuffer())
                    .then(data => {
                        mammoth.convertToHtml({
                                arrayBuffer: data
                            })
                            .then(result => {
                                container.innerHTML = result.value;
                            })
                            .catch(() => {
                                container.innerHTML = '<div class="text-danger">Failed to load DOCX preview.</div>';
                            });
                    });
            } else if (fileType === 'ppt' || fileType === 'pptx') {
                renderPptxPreview(fileUrl, container);
            }
        }

        // PPTX Preview Function using native browser APIs
        async function renderPptxPreview(fileUrl, container) {
            container.innerHTML = '<div class="text-center text-muted">Loading PPTX preview...</div>';
            
            try {
                const response = await fetch(fileUrl);
                const arrayBuffer = await response.arrayBuffer();
                const slides = await parsePptxFile(arrayBuffer);
                
                if (slides.length === 0) {
                    container.innerHTML = '<div class="text-danger">No slides found in presentation.</div>';
                    return;
                }
                
                // Show only 10-20% of slides (max 5 slides)
                const maxSlides = Math.min(5, Math.max(2, Math.ceil(slides.length * 0.2)));
                const previewSlides = slides.slice(0, maxSlides);
                
                container.innerHTML = createPptxViewer(previewSlides, slides.length);
                initializePptxViewer();
                
            } catch (error) {
                console.error('Error loading PPTX:', error);
                container.innerHTML = '<div class="text-danger">Failed to load PPTX preview.</div>';
            }
        }

        // Parse PPTX file using native browser APIs
        async function parsePptxFile(arrayBuffer) {
            const slides = [];
            
            try {
                // Use native browser APIs to read ZIP file
                const uint8Array = new Uint8Array(arrayBuffer);
                const zipEntries = await readZipEntries(uint8Array);
                
                // Find slide XML files
                const slideFiles = zipEntries
                    .filter(entry => entry.filename.match(/^ppt\/slides\/slide\d+\.xml$/i))
                    .sort((a, b) => {
                        const aNum = parseInt(a.filename.match(/slide(\d+)/i)[1]);
                        const bNum = parseInt(b.filename.match(/slide(\d+)/i)[1]);
                        return aNum - bNum;
                    });
                
                // Parse each slide
                for (const slideFile of slideFiles) {
                    const slideXml = new TextDecoder().decode(slideFile.data);
                    const slideContent = parseSlideXml(slideXml);
                    slides.push(slideContent);
                }
                
            } catch (error) {
                console.error('Error parsing PPTX:', error);
            }
            
            return slides;
        }

        // Simple ZIP reader using native browser APIs
        async function readZipEntries(uint8Array) {
            const entries = [];
            const view = new DataView(uint8Array.buffer);
            
            // Find central directory
            let centralDirOffset = -1;
            for (let i = uint8Array.length - 22; i >= 0; i--) {
                if (view.getUint32(i, true) === 0x06054b50) { // End of central directory signature
                    centralDirOffset = view.getUint32(i + 16, true);
                    break;
                }
            }
            
            if (centralDirOffset === -1) {
                throw new Error('Invalid ZIP file');
            }
            
            // Read central directory entries
            let offset = centralDirOffset;
            while (offset < uint8Array.length - 22) {
                if (view.getUint32(offset, true) !== 0x02014b50) break; // Central directory signature
                
                const filenameLength = view.getUint16(offset + 28, true);
                const extraFieldLength = view.getUint16(offset + 30, true);
                const commentLength = view.getUint16(offset + 32, true);
                const localHeaderOffset = view.getUint32(offset + 42, true);
                
                const filename = new TextDecoder().decode(
                    uint8Array.slice(offset + 46, offset + 46 + filenameLength)
                );
                
                // Read local file header to get actual file data
                const localView = new DataView(uint8Array.buffer, localHeaderOffset);
                if (localView.getUint32(0, true) === 0x04034b50) { // Local file header signature
                    const localFilenameLength = localView.getUint16(26, true);
                    const localExtraFieldLength = localView.getUint16(28, true);
                    const compressedSize = localView.getUint32(18, true);
                    
                    const dataOffset = localHeaderOffset + 30 + localFilenameLength + localExtraFieldLength;
                    const fileData = uint8Array.slice(dataOffset, dataOffset + compressedSize);
                    
                    entries.push({
                        filename: filename,
                        data: fileData
                    });
                }
                
                offset += 46 + filenameLength + extraFieldLength + commentLength;
            }
            
            return entries;
        }

        // Parse slide XML to extract text content
        function parseSlideXml(xmlString) {
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(xmlString, 'text/xml');
            
            // Extract text content from <a:t> elements
            const textElements = xmlDoc.querySelectorAll('t');
            const textContent = [];
            
            textElements.forEach(element => {
                const text = element.textContent.trim();
                if (text) {
                    textContent.push(text);
                }
            });
            
            return {
                title: textContent[0] || 'Untitled Slide',
                content: textContent.slice(1)
            };
        }

        // Create PPTX viewer HTML with glassmorphism design
        function createPptxViewer(slides, totalSlides) {
            return `
                            < div class="pptx-viewer-container" >
                    <div class="pptx-slide-container">
                        ${slides.map((slide, index) => `
                            <div class="pptx-slide ${index === 0 ? 'active' : ''}" data-slide="${index}">
                                <div class="pptx-slide-content">
                                    <div class="pptx-watermark">Preview Only</div>
                                    <h2 class="pptx-slide-title">${escapeHtml(slide.title)}</h2>
                                    <div class="pptx-slide-text">
                                        ${slide.content.map(text => `<p>${escapeHtml(text)}</p>`).join('')}
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    
                    <div class="pptx-controls">
                        <button class="pptx-btn pptx-prev" id="pptx-prev-btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        <span class="pptx-indicator" id="pptx-indicator">
                            Slide 1 of ${slides.length} (Preview: ${slides.length}/${totalSlides})
                        </span>
                        <button class="pptx-btn pptx-next" id="pptx-next-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    
                    <div class="pptx-unlock-overlay" id="pptx-unlock-overlay" style="display: none;">
                        <div class="pptx-unlock-content">
                            <h3>🔒 Preview Limit Reached</h3>
                            <p>Unlock full presentation to view all ${totalSlides} slides</p>
                        </div>
                    </div>
                </div >

                            <style>
                                .pptx-viewer-container {
                                    position: relative;
                                max-width: 800px;
                                margin: 0 auto;
                                background: rgba(255, 255, 255, 0.1);
                                backdrop-filter: blur(10px);
                                border-radius: 20px;
                                border: 1px solid rgba(255, 255, 255, 0.2);
                                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
                                overflow: hidden;
                    }

                                .pptx-slide-container {
                                    position: relative;
                                height: 500px;
                                overflow: hidden;
                    }

                                .pptx-slide {
                                    position: absolute;
                                top: 0;
                                left: 0;
                                width: 100%;
                                height: 100%;
                                opacity: 0;
                                transform: translateX(100%);
                                transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
                                padding: 40px;
                                box-sizing: border-box;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                    }

                                .pptx-slide.active {
                                    opacity: 1;
                                transform: translateX(0);
                    }

                                .pptx-slide.prev {
                                    transform: translateX(-100%);
                    }

                                .pptx-slide-content {
                                    position: relative;
                                width: 100%;
                                max-width: 600px;
                                text-align: center;
                                color: #333;
                    }

                                .pptx-watermark {
                                    position: absolute;
                                top: -10px;
                                right: -10px;
                                background: rgba(220, 53, 69, 0.1);
                                color: #dc3545;
                                padding: 5px 15px;
                                border-radius: 15px;
                                font-size: 0.8em;
                                font-weight: bold;
                                backdrop-filter: blur(5px);
                                border: 1px solid rgba(220, 53, 69, 0.2);
                    }

                                .pptx-slide-title {
                                    font - size: 2.5em;
                                font-weight: bold;
                                margin-bottom: 30px;
                                color: #2c3e50;
                                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                    }

                                .pptx-slide-text p {
                                    font - size: 1.2em;
                                line-height: 1.6;
                                margin-bottom: 15px;
                                color: #34495e;
                    }

                                .pptx-controls {
                                    display: flex;
                                justify-content: space-between;
                                align-items: center;
                                padding: 20px 40px;
                                background: rgba(255, 255, 255, 0.05);
                                backdrop-filter: blur(5px);
                                border-top: 1px solid rgba(255, 255, 255, 0.1);
                    }

                                .pptx-btn {
                                    background: rgba(255, 255, 255, 0.2);
                                border: 1px solid rgba(255, 255, 255, 0.3);
                                color: #333;
                                padding: 12px 24px;
                                border-radius: 25px;
                                cursor: pointer;
                                transition: all 0.3s ease;
                                backdrop-filter: blur(10px);
                                font-weight: 500;
                    }

                                .pptx-btn:hover:not(:disabled) {
                                    background: rgba(255, 255, 255, 0.3);
                                transform: translateY(-2px);
                                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                    }

                                .pptx-btn:disabled {
                                    opacity: 0.5;
                                cursor: not-allowed;
                    }

                                .pptx-indicator {
                                    font - weight: bold;
                                color: #2c3e50;
                                background: rgba(255, 255, 255, 0.1);
                                padding: 8px 16px;
                                border-radius: 15px;
                                backdrop-filter: blur(5px);
                    }

                                .pptx-unlock-overlay {
                                    position: absolute;
                                top: 0;
                                left: 0;
                                width: 100%;
                                height: 100%;
                                background: rgba(255, 255, 255, 0.9);
                                backdrop-filter: blur(10px);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                z-index: 10;
                                border-radius: 20px;
                    }

                                .pptx-unlock-content {
                                    text - align: center;
                                color: #2c3e50;
                    }

                                .pptx-unlock-content h3 {
                                    font - size: 2em;
                                margin-bottom: 15px;
                    }

                                .pptx-unlock-content p {
                                    font - size: 1.2em;
                                opacity: 0.8;
                    }
                            </style>
                        `;
        }

        // Initialize PPTX viewer functionality
        function initializePptxViewer() {
            let currentSlide = 0;
            const slides = document.querySelectorAll('.pptx-slide');
            const prevBtn = document.getElementById('pptx-prev-btn');
            const nextBtn = document.getElementById('pptx-next-btn');
            const indicator = document.getElementById('pptx-indicator');
            const unlockOverlay = document.getElementById('pptx-unlock-overlay');
            
            function updateSlide() {
                slides.forEach((slide, index) => {
                    slide.classList.remove('active', 'prev');
                    if (index === currentSlide) {
                        slide.classList.add('active');
                    } else if (index < currentSlide) {
                        slide.classList.add('prev');
                    }
                });
                
                const totalSlides = parseInt(indicator.textContent.match(/\/(\d+)\)/)[1]);
                const previewSlides = slides.length;
                indicator.textContent = `Slide ${ currentSlide + 1 } of ${ previewSlides } (Preview: ${ previewSlides }/${totalSlides})`;
                
                prevBtn.disabled = currentSlide === 0;
                nextBtn.disabled = currentSlide === slides.length - 1;
                
                // Show unlock overlay on last slide
                if (currentSlide === slides.length - 1) {
                    setTimeout(() => {
                        unlockOverlay.style.display = 'flex';
                    }, 1000);
                } else {
                    unlockOverlay.style.display = 'none';
                }
            }
            
            prevBtn.addEventListener('click', () => {
                if (currentSlide > 0) {
                    currentSlide--;
                    updateSlide();
                }
            });
            
            nextBtn.addEventListener('click', () => {
                if (currentSlide < slides.length - 1) {
                    currentSlide++;
                    updateSlide();
                }
            });
            
            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft' && currentSlide > 0) {
                    currentSlide--;
                    updateSlide();
                } else if (e.key === 'ArrowRight' && currentSlide < slides.length - 1) {
                    currentSlide++;
                    updateSlide();
                }
            });
            
            updateSlide();
        }

        // Utility function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // SPA-style content viewer
        function loadContentViewer(url, title) {
            // Create overlay
            const overlay = document.createElement('div');
            overlay.id = 'content-viewer-overlay';
            overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.9);
            z - index: 10000;
            display: flex;
            flex - direction: column;
            `;
            
            // Create header with back button
            const header = document.createElement('div');
            header.style.cssText = `
            background: #fff;
            padding: 15px 20px;
            display: flex;
            align - items: center;
            justify - content: space - between;
            box - shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            `;
            
            header.innerHTML = `
                < div style = "display: flex; align-items: center; gap: 15px;" >
                    <button onclick="closeContentViewer()" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <h5 style="margin: 0; color: #333;">${title}</h5>
                </div >
                `;
            
            // Create content iframe
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.style.cssText = `
            flex: 1;
            border: none;
            background: white;
            `;

            overlay.appendChild(header);
            overlay.appendChild(iframe);
            document.body.appendChild(overlay);

            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }

        function closeContentViewer() {
            const overlay = document.getElementById('content-viewer-overlay');
            if (overlay) {
                overlay.remove();
                document.body.style.overflow = 'auto';
            }
        }

        // Handle comment button click
        document.addEventListener('click', function(e) {
            if (e.target.closest('.comment-btn')) {
                const contentId = e.target.closest('.comment-btn').dataset.contentId;
                showCommentsModal(contentId);
            }
        });

        // Handle comment form submission
        document.addEventListener('submit', async function(e) {
            if (e.target && e.target.id === 'commentForm') {
                e.preventDefault();
                const contentId = document.getElementById('commentContentId').value;
                const commentText = document.getElementById('commentText').value.trim();
                
                if (!commentText) return;
                
                try {
                    const response = await fetch('add_comment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `content_id=${contentId}&comment=${encodeURIComponent(commentText)}`
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        // Reload comments after successful submission
                        showCommentsModal(contentId);
                        document.getElementById('commentText').value = '';
                    } else {
                        alert('Failed to add comment: ' + (result.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Failed to add comment. Please try again.');
                }
            }
        });

        // Function to show comments in modal
        async function showCommentsModal(contentId) {
            const modal = new bootstrap.Modal(document.getElementById('commentsModal'));
            const modalBody = document.getElementById('commentsModalBody');
            const commentForm = document.getElementById('commentForm');
            
            // Set content ID in the form
            if (commentForm) {
                document.getElementById('commentContentId').value = contentId;
            }
            
            // Show loading state
            modalBody.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>`;
                
            modal.show();
            
            try {
                // Fetch comments
                const response = await fetch(`get_comments.php?content_id=${contentId}`);
                const data = await response.json();
                
                if (data.comments && data.comments.length > 0) {
                    let commentsHtml = '<div class="comments-list" style="max-height: 300px; overflow-y: auto;">';
                    data.comments.forEach(comment => {
                        commentsHtml += `
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h6 class="card-subtitle mb-1 text-muted">${escapeHtml(comment.username)}</h6>
                                        <small class="text-muted">${new Date(comment.created_at).toLocaleString()}</small>
                                    </div>
                                    <p class="card-text mb-0">${escapeHtml(comment.comment)}</p>
                                </div>
                            </div>`;
                    });
                    commentsHtml += '</div>';
                    modalBody.innerHTML = commentsHtml;
                } else {
                    modalBody.innerHTML = '<p class="text-muted text-center">No comments yet. Be the first to comment!</p>';
                }
            } catch (error) {
                console.error('Error loading comments:', error);
                modalBody.innerHTML = '<p class="text-danger">Failed to load comments. Please try again later.</p>';
            }
        }
    </script>
    
    <script>
    // Global function to show comments modal
    let currentModal = null;
    
    async function showCommentsModal(contentId) {
        const modal = document.getElementById('commentsModal');
        const modalBody = document.getElementById('commentsModalBody');
        const commentForm = document.getElementById('commentForm');
        const commentContentId = document.getElementById('commentContentId');
        
        if (!modal || !modalBody) {
            console.error('Required modal elements not found');
            return;
        }
        
        // Dispose of any existing modal instance
        if (currentModal) {
            currentModal.hide();
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
        
        // Create new modal instance
        currentModal = new bootstrap.Modal(modal, {
            backdrop: true,
            keyboard: true
        });
        
        // Set the content ID in the form if it exists
        if (commentContentId) {
            commentContentId.value = contentId;
        }
        
        // Clean up on modal close
        const handleHidden = () => {
            if (currentModal) {
                currentModal.dispose();
                currentModal = null;
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
            modal.removeEventListener('hidden.bs.modal', handleHidden);
        };
        
        modal.addEventListener('hidden.bs.modal', handleHidden);
        
        // Show loading state
        modalBody.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading comments...</p>
            </div>`;
        
        // Show the modal
        currentModal.show();
        
        try {
            // Fetch comments
            const response = await fetch(`get_comments.php?content_id=${contentId}`);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.comments && data.comments.length > 0) {
                let commentsHtml = '<div class="comments-list" style="max-height: 300px; overflow-y: auto;">';
                data.comments.forEach(comment => {
                    commentsHtml += `
                        <div class="card mb-2">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="card-subtitle mb-1 text-muted">${escapeHtml(comment.username || 'Anonymous')}</h6>
                                    <small class="text-muted">${comment.created_at ? new Date(comment.created_at).toLocaleString() : ''}</small>
                                </div>
                                <p class="card-text mb-0">${escapeHtml(comment.comment || '')}</p>
                            </div>
                        </div>`;
                });
                commentsHtml += '</div>';
                modalBody.innerHTML = commentsHtml;
            } else {
                modalBody.innerHTML = '<p class="text-muted text-center">No comments yet. Be the first to comment!</p>';
            }
        } catch (error) {
            console.error('Error loading comments:', error);
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    Failed to load comments. Please try again later.
                    <div class="small text-muted mt-1">${error.message || 'Unknown error'}</div>
                </div>`;
        }
        
        // Handle comment form submission
        if (commentForm) {
            commentForm.onsubmit = async function(e) {
                e.preventDefault();
                const commentText = document.getElementById('commentText')?.value?.trim();
                if (!commentText) return;
                
                const submitBtn = commentForm.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn?.innerHTML;
                
                try {
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Posting...';
                    }
                    
                    const response = await fetch('add_comment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `content_id=${encodeURIComponent(contentId)}&comment=${encodeURIComponent(commentText)}`
                    });
                    
                    if (!response.ok) throw new Error('Network response was not ok');
                    const result = await response.json();
                    
                    if (result.success) {
                        // Reload comments after successful submission
                        const commentInput = document.getElementById('commentText');
                        if (commentInput) commentInput.value = '';
                        showCommentsModal(contentId);
                    } else {
                        alert(result.message || 'Failed to post comment');
                    }
                } catch (error) {
                    console.error('Error posting comment:', error);
                    alert(`Failed to post comment: ${error.message || 'Please try again'}`);
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        if (originalBtnText) submitBtn.innerHTML = originalBtnText;
                    }
                }
            };
        }
    }
    
    // Initialize comment button click handlers after DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Handle clicks on comment buttons
        document.addEventListener('click', function(e) {
            const commentBtn = e.target.closest('.comment-btn');
            if (commentBtn) {
                const contentId = commentBtn.dataset.contentId;
                if (contentId) {
                    showCommentsModal(contentId);
                }
            }
        });
        
        // Also initialize any existing comment buttons on page load
        document.querySelectorAll('.comment-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const contentId = this.dataset.contentId;
                if (contentId) {
                    showCommentsModal(contentId);
                }
            });
        });
    });
    </script>
    
    <!-- Comments Modal -->
    <div class="modal fade" id="commentsModal" tabindex="-1" aria-labelledby="commentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="commentsModalLabel">Comments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="commentsModalBody">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="modal-footer">
                    <form id="commentForm" class="w-100">
                        <input type="hidden" id="commentContentId" name="content_id">
                        <div class="input-group">
                            <input type="text" class="form-control" id="commentText" placeholder="Write a comment..." required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Post
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="modal-footer justify-content-center">
                    <a href="login.html" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login to Comment
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Video Purchase Modal -->
    <div class="modal fade" id="videoBuyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Full Video Access</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p>Buy to take full view of video.</p>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        function showVideoBuyModal() {
            var modal = new bootstrap.Modal(document.getElementById('videoBuyModal'));
            modal.show();
        }

        function openVideoPreview(url) {
            sessionStorage.setItem('videoPreviewed', '1');
            window.open(url, '_blank');
        }
        window.addEventListener('focus', function() {
            if (sessionStorage.getItem('videoPreviewed')) {
                showVideoBuyModal();
                sessionStorage.removeItem('videoPreviewed');
            }
        });
    </script>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- YouTube IFrame API -->
    <script src="https://www.youtube.com/iframe_api"></script>
</body>

</html>