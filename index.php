<?php
session_start();
require_once 'config.php';

// If this is an AJAX request for search
if(isset($_GET['ajax_search'])) {
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
        $content[] = array(
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'useful_to' => $row['useful_to'],
            'upload_date' => date('F j, Y', strtotime($row['upload_date'])),
            'file_path' => $row['file_path']
        );
    }
    
    header('Content-Type: application/json');
    echo json_encode($content);
    exit;
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

// Initial content load
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Viewer</title>
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
    </style>
</head>
<body>
    <div class="container">
        <!-- Auth Buttons -->
        <div class="auth-buttons">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-info">
                    <span class="text-muted">
                        <i class="fas fa-user"></i> 
                        <?php echo htmlspecialchars($userInfo['username']); ?>
                        <?php if ($userInfo['role'] === 'admin'): ?>
                            <span class="badge bg-primary">Admin</span>
                        <?php endif; ?>
                    </span>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            <?php else: ?>
                <a href="login.html" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
                <a href="register.html" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Register
                </a>
            <?php endif; ?>
        </div>

        <!-- Search Bar -->
        <div class="search-container">
            <div class="d-flex justify-content-center align-items-center">
                <div class="input-group w-50">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search content...">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                </div>
                <div id="loading">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
            </div>
        </div>

        <!-- Content List -->
        <div id="contentList" class="content-list">
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <div class="content-item">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($row['description']); ?></p>
                            <?php if (!empty($row['useful_to'])): ?>
                                <p class="mb-2"><strong>Useful to:</strong> <?php echo htmlspecialchars($row['useful_to']); ?></p>
                            <?php endif; ?>
                            <p class="text-muted mb-0">
                                <small>Uploaded: <?php echo date('F j, Y', strtotime($row['upload_date'])); ?></small>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="action-buttons justify-content-end">
                                <a href="viewer.php?id=<?php echo $row['id']; ?>" class="btn btn-info" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <a href="<?php echo htmlspecialchars($row['file_path']); ?>" class="btn btn-primary" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary" onclick="alert('Please login to download content')">
                                        <i class="fas fa-download"></i> Download
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

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
            return `
                <div class="content-item">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4>${escapeHtml(item.title)}</h4>
                            <p class="text-muted mb-2">${escapeHtml(item.description)}</p>
                            ${item.useful_to ? `<p class="mb-2"><strong>Useful to:</strong> ${escapeHtml(item.useful_to)}</p>` : ''}
                            <p class="text-muted mb-0">
                                <small>Uploaded: ${item.upload_date}</small>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="action-buttons justify-content-end">
                                <a href="viewer.php?id=${item.id}" class="btn btn-info" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                ${isLoggedIn ? 
                                    `<a href="${escapeHtml(item.file_path)}" class="btn btn-primary" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>` :
                                    `<button class="btn btn-secondary" onclick="alert('Please login to download content')">
                                        <i class="fas fa-download"></i> Download
                                    </button>`
                                }
                            </div>
                        </div>
                    </div>
                </div>
            `;
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
    </script>
</body>
</html> 
