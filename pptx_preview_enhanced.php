<?php
// Enhanced PPTX Preview with PptxGenJS - Load and preview PPTX files from server
$filePath = isset($_GET['file']) ? $_GET['file'] : '';
$fileName = $filePath ? basename($filePath) : 'Unknown File';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($fileName); ?> - Enhanced PPTX Preview</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Calibri', 'Segoe UI', Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.18);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 40px;
            max-width: 1000px;
            width: 100%;
            min-height: 700px;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: box-shadow 0.4s;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 12px;
            padding: 12px 20px;
            font-size: 1em;
            color: #2956b2;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(31, 38, 135, 0.15);
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            z-index: 1000;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 1);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(31, 38, 135, 0.25);
        }

        .slide-container {
            width: 100%;
            max-width: 900px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 50px;
            margin: 20px 0;
            min-height: 500px;
            position: relative;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }

        .slide-title {
            font-size: 2.8em;
            font-weight: 300;
            color: #1f4e79;
            margin-bottom: 30px;
            text-align: center;
            font-family: 'Calibri Light', 'Segoe UI Light', Arial, sans-serif;
            letter-spacing: -0.8px;
            line-height: 1.1;
        }

        .slide-subtitle {
            font-size: 1.6em;
            font-weight: 400;
            color: #666;
            margin-bottom: 35px;
            text-align: center;
            font-family: 'Calibri', 'Segoe UI', Arial, sans-serif;
            font-style: italic;
        }

        .slide-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
            font-size: 1.3em;
            line-height: 1.6;
            color: #333;
        }

        .bullet-list {
            list-style: none;
            padding-left: 0;
            margin: 25px 0;
        }

        .bullet-item {
            position: relative;
            padding-left: 30px;
            margin-bottom: 12px;
            line-height: 1.6;
            font-size: 1.2em;
        }

        .bullet-item:before {
            content: "•";
            position: absolute;
            left: 12px;
            color: #1f4e79;
            font-weight: bold;
            font-size: 1.3em;
        }

        .bullet-item.level-2 {
            padding-left: 60px;
            font-size: 1.1em;
        }

        .bullet-item.level-2:before {
            content: "◦";
            left: 42px;
            color: #4a90e2;
        }

        .bullet-item.level-3 {
            padding-left: 90px;
            font-size: 1.05em;
        }

        .bullet-item.level-3:before {
            content: "▪";
            left: 72px;
            color: #7bb3f0;
        }

        .pptx-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            font-size: 1.1em;
        }

        .pptx-table th,
        .pptx-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        .pptx-table th {
            background: #1f4e79;
            color: white;
            font-weight: 600;
        }

        .pptx-table tr:hover {
            background: rgba(31, 78, 121, 0.05);
        }

        .image-placeholder {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 2px dashed #cbd5e0;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            margin: 25px 0;
            color: #718096;
            font-style: italic;
            font-size: 1.1em;
        }

        .image-placeholder i {
            font-size: 3em;
            margin-bottom: 15px;
            display: block;
            color: #a0aec0;
        }

        .chart-placeholder {
            background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
            border: 2px dashed #fc8181;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            margin: 25px 0;
            color: #c05621;
            font-style: italic;
            font-size: 1.1em;
        }

        .chart-placeholder i {
            font-size: 3em;
            margin-bottom: 15px;
            display: block;
            color: #f6ad55;
        }

        .navigation-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin: 30px 0;
        }

        .nav-btn {
            background: rgba(31, 78, 121, 0.9);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            font-size: 1.1em;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-btn:hover:not(:disabled) {
            background: #1f4e79;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(31, 78, 121, 0.3);
        }

        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .indicator {
            text-align: center;
            color: #666;
            font-size: 1em;
            margin-top: 15px;
            font-weight: 500;
        }

        .loading {
            text-align: center;
            color: #666;
            font-size: 1.2em;
            padding: 50px;
        }

        .error {
            background: #fed7d7;
            color: #c53030;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }

        @media (max-width: 768px) {
            .container {
                max-width: 95vw;
                padding: 20px;
                margin: 10px;
            }
            
            .slide-container {
                padding: 30px 20px;
            }
            
            .slide-title {
                font-size: 2.2em;
            }
            
            .navigation-controls {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>

<body>
    <button class="back-btn" onclick="window.history.back()">
        <i class="fas fa-arrow-left"></i> Back
    </button>
    
    <div class="container">
        <div class="file-info" style="margin-bottom: 30px; text-align: center;">
            <h2 style="color: #1f4e79; margin-bottom: 10px;">
                <i class="fas fa-file-powerpoint" style="color: #d04423; margin-right: 10px;"></i>
                <?php echo htmlspecialchars($fileName); ?>
            </h2>
            <div style="font-size: 1em; color: #666;">Enhanced PPTX Preview - Loading presentation...</div>
        </div>
        <div id="pptx-preview-area"></div>
    </div>

    <script>
        // Enhanced PPTX Preview with better formatting and visual fidelity
        const previewArea = document.getElementById('pptx-preview-area');
        let slides = [];
        let current = 0;

        // Auto-load PPTX file from server
        <?php if ($filePath): ?>
        document.addEventListener('DOMContentLoaded', async function() {
            previewArea.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading presentation...</div>';
            slides = [];
            current = 0;
            
            try {
                const response = await fetch('<?php echo addslashes($filePath); ?>');
                if (!response.ok) throw new Error('Failed to load file');
                const arrayBuffer = await response.arrayBuffer();
                
                // Use JSZip for parsing
                const zip = new JSZip();
                const zipData = await zip.loadAsync(arrayBuffer);
                
                // Extract slides with enhanced formatting
                await extractSlidesWithEnhancedFormatting(zipData);
                
                if (slides.length > 0) {
                    renderSlide(0);
                } else {
                    previewArea.innerHTML = '<div class="error">No slides found in presentation.</div>';
                }
            } catch (err) {
                console.error('PPTX parsing error:', err);
                previewArea.innerHTML = `<div class="error">Failed to parse PPTX file: ${err.message}</div>`;
            }
        });
        <?php else: ?>
        document.addEventListener('DOMContentLoaded', function() {
            previewArea.innerHTML = '<div class="error">No file specified for preview.</div>';
        });
        <?php endif; ?>

        // Enhanced slide extraction with better formatting
        async function extractSlidesWithEnhancedFormatting(zipData) {
            const slideFiles = [];
            
            // Find all slide files
            Object.keys(zipData.files).forEach(filename => {
                if (filename.match(/^ppt\/slides\/slide\d+\.xml$/)) {
                    slideFiles.push(filename);
                }
            });
            
            if (slideFiles.length === 0) {
                throw new Error('No slides found in presentation');
            }
            
            // Sort slides by number
            slideFiles.sort((a, b) => {
                const numA = parseInt(a.match(/slide(\d+)\.xml$/)[1]);
                const numB = parseInt(b.match(/slide(\d+)\.xml$/)[1]);
                return numA - numB;
            });
            
            // Extract first 3-5 slides (10-20% preview)
            const maxSlides = Math.min(5, Math.max(3, Math.ceil(slideFiles.length * 0.2)));
            const slidesToProcess = slideFiles.slice(0, maxSlides);
            
            for (const slideFile of slidesToProcess) {
                try {
                    const slideXml = await zipData.files[slideFile].async('text');
                    const slideData = parseSlideWithEnhancedFormatting(slideXml);
                    slides.push(slideData);
                } catch (error) {
                    console.warn(`Failed to parse ${slideFile}:`, error);
                    slides.push('<div class="error">Failed to parse slide</div>');
                }
            }
        }

        // Enhanced slide parsing with better formatting support
        function parseSlideWithEnhancedFormatting(slideXml) {
            const slideData = {
                title: '',
                subtitle: '',
                content: [],
                images: [],
                tables: [],
                charts: []
            };
            
            // Extract title from title placeholder
            const titlePlaceholder = slideXml.match(/<p:sp[^>]*>.*?<p:nvSpPr>.*?<p:ph[^>]*type="title"[^>]*>.*?<\/p:sp>/s);
            if (titlePlaceholder) {
                const titleText = extractFormattedText(titlePlaceholder[0]);
                if (titleText.trim()) slideData.title = titleText.trim();
            }
            
            // Extract subtitle
            const subtitlePlaceholder = slideXml.match(/<p:sp[^>]*>.*?<p:nvSpPr>.*?<p:ph[^>]*type="subTitle"[^>]*>.*?<\/p:sp>/s);
            if (subtitlePlaceholder) {
                const subtitleText = extractFormattedText(subtitlePlaceholder[0]);
                if (subtitleText.trim()) slideData.subtitle = subtitleText.trim();
            }
            
            // Extract all shape elements for content
            const shapes = slideXml.match(/<p:sp[^>]*>.*?<\/p:sp>/gs) || [];
            
            shapes.forEach(shape => {
                // Skip title and subtitle placeholders
                if (shape.includes('type="title"') || shape.includes('type="subTitle"')) return;
                
                // Check for different content types
                if (shape.includes('<a:tbl>')) {
                    // Table content
                    const tableData = extractTableData(shape);
                    if (tableData.length > 0) slideData.tables.push(tableData);
                } else if (shape.includes('<p:pic>') || shape.includes('<a:blip>')) {
                    // Image content
                    const imageInfo = extractImageInfo(shape);
                    slideData.images.push(imageInfo);
                } else if (shape.includes('<c:chart>') || shape.includes('chart')) {
                    // Chart content
                    slideData.charts.push({ type: 'chart' });
                } else {
                    // Text content
                    const textContent = extractFormattedText(shape);
                    if (textContent.trim()) {
                        const bulletItems = parseBulletPoints(shape);
                        if (bulletItems.length > 0) {
                            slideData.content.push(bulletItems);
                        } else {
                            slideData.content.push(textContent.trim());
                        }
                    }
                }
            });
            
            return formatSlideContent(slideData);
        }

        // Helper functions for enhanced parsing
        function extractFormattedText(element) {
            const textElements = element.match(/<a:t[^>]*>([^<]*)<\/a:t>/g) || [];
            return textElements.map(t => {
                const match = t.match(/<a:t[^>]*>([^<]*)<\/a:t>/);
                return match ? match[1] : '';
            }).join(' ');
        }
        
        function parseBulletPoints(shape) {
            const bulletItems = [];
            const paragraphs = shape.match(/<a:p[^>]*>.*?<\/a:p>/gs) || [];
            
            paragraphs.forEach(paragraph => {
                const text = extractFormattedText(paragraph);
                if (text.trim()) {
                    const level = getBulletLevel(paragraph);
                    bulletItems.push({
                        text: text.trim(),
                        level: level,
                        type: 'bullet'
                    });
                }
            });
            
            return bulletItems;
        }
        
        function getBulletLevel(paragraph) {
            const levelMatch = paragraph.match(/lvl="(\d+)"/); 
            return levelMatch ? parseInt(levelMatch[1]) : 0;
        }
        
        function extractImageInfo(shape) {
            const nameMatch = shape.match(/name="([^"]*)"/); 
            const descMatch = shape.match(/descr="([^"]*)"/); 
            return {
                name: nameMatch ? nameMatch[1] : 'Image',
                description: descMatch ? descMatch[1] : 'Image placeholder'
            };
        }
        
        function extractTableData(shape) {
            const rows = shape.match(/<a:tr[^>]*>.*?<\/a:tr>/gs) || [];
            const tableData = [];
            
            rows.forEach(row => {
                const cells = row.match(/<a:tc[^>]*>.*?<\/a:tc>/gs) || [];
                const rowData = cells.map(cell => extractFormattedText(cell).trim());
                if (rowData.some(cell => cell)) tableData.push(rowData);
            });
            
            return tableData;
        }
        
        function formatSlideContent(slideData) {
            let html = '<div class="slide-content">';
            
            // Add title
            if (slideData.title) {
                html += `<div class="slide-title">${escapeHtml(slideData.title)}</div>`;
            }
            
            // Add subtitle
            if (slideData.subtitle) {
                html += `<div class="slide-subtitle">${escapeHtml(slideData.subtitle)}</div>`;
            }
            
            // Add text content with bullets
            slideData.content.forEach(contentBlock => {
                if (Array.isArray(contentBlock)) {
                    html += '<div class="bullet-list">';
                    contentBlock.forEach(item => {
                        if (item.type === 'bullet') {
                            const levelClass = item.level > 0 ? ` level-${Math.min(item.level + 1, 3)}` : '';
                            html += `<div class="bullet-item${levelClass}">${escapeHtml(item.text)}</div>`;
                        } else {
                            html += `<div class="slide-text">${escapeHtml(item.text)}</div>`;
                        }
                    });
                    html += '</div>';
                } else if (typeof contentBlock === 'string') {
                    html += `<div style="font-size: 1.2em; margin: 15px 0; line-height: 1.6;">${escapeHtml(contentBlock)}</div>`;
                }
            });
            
            // Add images
            slideData.images.forEach(img => {
                html += `<div class="image-placeholder">
                    <i class="fas fa-image"></i>
                    <div><strong>${escapeHtml(img.name)}</strong></div>
                    <small>${escapeHtml(img.description)}</small>
                </div>`;
            });
            
            // Add tables
            slideData.tables.forEach(table => {
                if (table.length > 0) {
                    html += '<table class="pptx-table">';
                    table.forEach((row, index) => {
                        const tag = index === 0 ? 'th' : 'td';
                        html += '<tr>';
                        row.forEach(cell => {
                            html += `<${tag}>${escapeHtml(cell || '')}</${tag}>`;
                        });
                        html += '</tr>';
                    });
                    html += '</table>';
                }
            });
            
            // Add charts
            slideData.charts.forEach(chart => {
                html += `<div class="chart-placeholder">
                    <i class="fas fa-chart-bar"></i>
                    <div><strong>Chart</strong></div>
                    <small>Chart content not available in preview</small>
                </div>`;
            });
            
            html += '</div>';
            return html || '<div style="text-align: center; color: #666; font-style: italic;">No content found</div>';
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function renderSlide(idx) {
            previewArea.innerHTML = `
                <div class="slide-container" style="opacity:0;transform:translateY(20px);">
                    ${slides[idx]}
                </div>
                <div class="navigation-controls">
                    <button class="nav-btn" id="pptx-prev-btn" ${idx===0?'disabled':''}>
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button class="nav-btn" id="pptx-next-btn" ${idx===slides.length-1?'disabled':''}>
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="indicator">Slide ${idx+1} of ${slides.length} (Preview)</div>
            `;
            
            setTimeout(() => {
                const slideContainer = previewArea.querySelector('.slide-container');
                if (slideContainer) {
                    slideContainer.style.opacity = '1';
                    slideContainer.style.transform = 'translateY(0)';
                    slideContainer.style.transition = 'all 0.4s ease';
                }
            }, 50);
            
            // Add event listeners
            document.getElementById('pptx-prev-btn').onclick = function() {
                if (current > 0) {
                    current--;
                    renderSlide(current);
                }
            };
            
            document.getElementById('pptx-next-btn').onclick = function() {
                if (current < slides.length - 1) {
                    current++;
                    renderSlide(current);
                }
            };
            
            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowLeft' && current > 0) {
                    current--;
                    renderSlide(current);
                } else if (e.key === 'ArrowRight' && current < slides.length - 1) {
                    current++;
                    renderSlide(current);
                }
            });
        }
    </script>
</body>

</html>
