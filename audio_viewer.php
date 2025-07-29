<?php
// Standalone audio preview script
$file = isset($_GET['file']) ? $_GET['file'] : '';
if (!$file || !file_exists($file)) {
    echo '<div style="text-align:center;margin-top:40px;">Audio file not found.</div>';
    exit;
}
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if (!in_array($ext, ['mp3', 'wav', 'aac', 'ogg', 'flac'])) {
    echo '<div style="text-align:center;margin-top:40px;">Unsupported audio format.</div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Audio Preview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }

        .audio-container {
            max-width: 500px;
            margin: 60px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            padding: 32px;
            text-align: center;
        }

        #custom-audio-player {
            user-select: none;
        }
    </style>
</head>

<body>
    <div class="audio-container">
        <div id="custom-audio-player">
            <div style="height: 38px; margin-bottom: 8px;"> <!-- Fixed height container -->
                <button id="audio-start-btn" class="btn btn-success" style="width:120px;">Start</button>
                <button id="audio-stop-btn" class="btn btn-danger" style="width:120px;visibility:hidden;">Stop</button>
            </div>
            <div id="audio-status" class="text-muted mb-2">Preview: First 30 seconds only</div>
            <audio id="audio-preview" src="<?php echo htmlspecialchars($file); ?>" preload="metadata" style="display:none;"></audio>
        </div>
        <div id="audio-msg" class="text-danger small mt-2" style="display:none;text-align:center;">Preview ended. Please purchase to access full audio.</div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const audio = document.getElementById('audio-preview');
            const startBtn = document.getElementById('audio-start-btn');
            const stopBtn = document.getElementById('audio-stop-btn');
            const status = document.getElementById('audio-status');
            const msg = document.getElementById('audio-msg');
            let previewEnded = false;
            let previewTimer = null;
            startBtn.onclick = function() {
                if (previewEnded) return;
                audio.currentTime = 0;
                audio.play();
                startBtn.style.visibility = 'hidden';
                stopBtn.style.visibility = 'visible';
                status.style.display = 'block';
                msg.style.display = 'none';
                if (previewTimer) clearTimeout(previewTimer);
                previewTimer = setTimeout(function() {
                    audio.pause();
                    audio.currentTime = 30;
                    startBtn.style.visibility = 'hidden';
                    stopBtn.style.visibility = 'hidden';
                    status.style.display = 'none';
                    msg.style.display = 'block';
                    previewEnded = true;
                }, 30000);
            };
            stopBtn.onclick = function() {
                audio.pause();
                audio.currentTime = 0;
                startBtn.style.visibility = 'visible';
                stopBtn.style.visibility = 'hidden';
                status.style.display = 'block';
                msg.style.display = 'none';
                if (previewTimer) clearTimeout(previewTimer);
            };
            audio.ontimeupdate = function() {
                if (audio.currentTime >= 30 && !previewEnded) {
                    audio.pause();
                    audio.currentTime = 30;
                    startBtn.style.visibility = 'hidden';
                    stopBtn.style.visibility = 'hidden';
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
        });
    </script>
</body>

</html>
