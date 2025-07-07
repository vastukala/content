<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

require_once "config.php";

// Handle file upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["file"])) {
    $title = $_POST["title"];
    $description = $_POST["description"];
    $fileType = $_POST["file_type"];
    $usefulTo = isset($_POST["useful_to"]) ? implode(",", $_POST["useful_to"]) : '';
    $tags = $_POST["tags"];
    $adminId = $_SESSION["user_id"];
    $username = $_SESSION["username"];
    
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
    
    $uploadOk = true;
    $errorMessage = "";
    
    // Validate file type
    if (!isset($allowedTypes[$_POST["file_type"]]) || !in_array($fileExtension, $allowedTypes[$_POST["file_type"]])) {
        $errorMessage = "Sorry, only " . implode(", ", $allowedTypes[$_POST["file_type"]]) . " files are allowed for " . $_POST["file_type"] . " type.";
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
    
    if ($uploadOk) {
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
            // Insert file information into database
            $sql = "INSERT INTO content (title, description, file_type, file_path, useful_to, tags, admin_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssssssi", $title, $description, $_POST["file_type"], $targetFilePath, $usefulTo, $tags, $adminId);
                
                if (mysqli_stmt_execute($stmt)) {
                    $successMessage = "The file has been uploaded successfully.";
                } else {
                    $errorMessage = "Sorry, there was an error uploading your file to database.";
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $errorMessage = "Sorry, there was an error uploading your file.";
        }
    }
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            max-width: 90%;
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
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h2>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>

        <!-- Add Content Button -->
        <button class="btn btn-primary btn-add-content" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="fas fa-plus"></i> Add New Content
        </button>

        <!-- Content List -->
        <div class="content-list">
            <h3 class="mb-4">Your Uploaded Content</h3>
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                    <?php 
                        echo $_SESSION['message'];
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
                                
                                <!-- Preview Container -->
                                <div class="preview-container">
                                    <?php
                                    $filePath = htmlspecialchars($item['file_path']);
                                    switch($item['file_type']) {
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
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-primary" onclick="showPreview('<?php echo $filePath; ?>', '<?php echo $item['file_type']; ?>')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="<?php echo $filePath; ?>" class="btn btn-sm btn-success" download>
                                    <i class="fas fa-download"></i>
                                </a>
                                <a href="edit_content.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete_content.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload New Content</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (isset($errorMessage) && !empty($errorMessage)): ?>
                        <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>
                    <?php if (isset($successMessage) && !empty($successMessage)): ?>
                        <div class="alert alert-success"><?php echo $successMessage; ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="file_type" class="form-label">File Type</label>
                                <select class="form-select" id="file_type" name="file_type" required>
                                    <option value="">Select Type</option>
                                    <option value="pdf">PDF</option>
                                    <option value="video">Video</option>
                                    <option value="audio">Audio</option>
                                    <option value="ppt">PPT</option>
                                    <option value="doc">DOC</option>
                                    <option value="excel">Excel</option>
                                    <option value="image">Image</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Useful To</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="useful_to[]" value="student" id="useful_student">
                                <label class="form-check-label" for="useful_student">Student</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="useful_to[]" value="teacher" id="useful_teacher">
                                <label class="form-check-label" for="useful_teacher">Teacher</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="useful_to[]" value="public" id="useful_public">
                                <label class="form-check-label" for="useful_public">Public</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="useful_to[]" value="others" id="useful_others">
                                <label class="form-check-label" for="useful_others">Others</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags (comma-separated)</label>
                            <input type="text" class="form-control" id="tags" name="tags" placeholder="e.g., education, math, science">
                        </div>

                        <div class="mb-3">
                            <label for="file" class="form-label">File</label>
                            <input type="file" class="form-control" id="file" name="file" required>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show success/error messages in modal if they exist
        window.addEventListener('load', function() {
            if (document.querySelector('.alert')) {
                new bootstrap.Modal(document.getElementById('uploadModal')).show();
            }
        });

        function submitForm() {
            document.querySelector('#uploadModal form').submit();
        }

        function showPreview(filePath, fileType) {
            const previewContent = document.getElementById('previewContent');
            let content = '';

            switch(fileType) {
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
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }
    </script>
</body>
</html> 
