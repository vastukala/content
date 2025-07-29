<?php
// PPTX Preview - Load and preview PPTX files from server
$filePath = isset($_GET['file']) ? $_GET['file'] : '';
$fileName = $filePath ? basename($filePath) : 'Unknown File';
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<title><?php echo htmlspecialchars($fileName); ?> - PPTX Preview</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pptxgenjs/3.12.0/pptxgen.bundle.min.js"></script>
	<style>
		body {
			background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
			min-height: 100vh;
			margin: 0;
			font-family: 'Segoe UI', Arial, sans-serif;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.glass {
			background: rgba(255, 255, 255, 0.95);
			box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.18);
			backdrop-filter: blur(8px);
			-webkit-backdrop-filter: blur(8px);
			border-radius: 16px;
			border: 1px solid rgba(255, 255, 255, 0.18);
			padding: 40px;
			max-width: 900px;
			width: 100%;
			min-height: 600px;
			display: flex;
			flex-direction: column;
			align-items: center;
			transition: box-shadow 0.4s;
		}

		.slide-container {
			width: 100%;
			max-width: 800px;
			background: white;
			border-radius: 8px;
			box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
			padding: 40px;
			margin: 20px 0;
			min-height: 450px;
			position: relative;
			overflow: hidden;
		}

		.slide-text {
			font-size: 1.2em;
			line-height: 1.8;
			color: #333;
			text-align: left;
			word-wrap: break-word;
			font-family: 'Calibri', 'Segoe UI', Arial, sans-serif;
		}

		.slide-content {
			display: flex;
			flex-direction: column;
			gap: 16px;
		}

		.slide-title {
			font-size: 2.5em;
			font-weight: 600;
			color: #1f4e79;
			margin-bottom: 30px;
			text-align: center;
			font-family: 'Calibri Light', 'Segoe UI Light', Arial, sans-serif;
			letter-spacing: -0.5px;
			line-height: 1.2;
		}

		.slide-subtitle {
			font-size: 1.5em;
			font-weight: 400;
			color: #666;
			margin-bottom: 25px;
			text-align: center;
			font-family: 'Calibri', 'Segoe UI', Arial, sans-serif;
			font-style: italic;
		}

		.bullet-list {
			list-style: none;
			padding-left: 0;
			margin: 20px 0;
			font-size: 1.1em;
		}

		.bullet-item {
			position: relative;
			padding-left: 24px;
			margin-bottom: 8px;
			line-height: 1.5;
		}

		.bullet-item:before {
			content: "•";
			position: absolute;
			left: 8px;
			color: #2956b2;
			font-weight: bold;
			font-size: 1.2em;
		}

		.bullet-item.level-2 {
			padding-left: 48px;
			font-size: 0.95em;
		}

		.bullet-item.level-2:before {
			content: "◦";
			left: 32px;
		}

		.bullet-item.level-3 {
			padding-left: 72px;
			font-size: 0.9em;
		}

		.bullet-item.level-3:before {
			content: "▪";
			left: 56px;
		}

		.pptx-table {
			width: 100%;
			border-collapse: collapse;
			margin: 16px 0;
			background: rgba(255, 255, 255, 0.8);
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		}

		.pptx-table th,
		.pptx-table td {
			padding: 12px 16px;
			text-align: left;
			border-bottom: 1px solid rgba(0, 0, 0, 0.1);
		}

		.pptx-table th {
			background: rgba(41, 86, 178, 0.1);
			font-weight: 600;
			color: #2956b2;
		}

		.pptx-table tr:hover {
			background: rgba(41, 86, 178, 0.05);
		}

		.image-placeholder {
			background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
			border: 2px dashed #cbd5e0;
			border-radius: 8px;
			padding: 24px;
			text-align: center;
			margin: 16px 0;
			color: #718096;
			font-style: italic;
		}

		.image-placeholder i {
			font-size: 2em;
			margin-bottom: 8px;
			display: block;
			color: #a0aec0;
		}

		.chart-placeholder {
			background: linear-gradient(135deg, #fef5e7 0%, #fed7aa 100%);
			border: 2px dashed #f6ad55;
			border-radius: 8px;
			padding: 24px;
			text-align: center;
			margin: 16px 0;
			color: #c05621;
			font-style: italic;
		}

		.chart-placeholder i {
			font-size: 2em;
			margin-bottom: 8px;
			display: block;
			color: #f6ad55;
		}

		.nav-btn {
			background: rgba(255, 255, 255, 0.35);
			border: none;
			border-radius: 12px;
			padding: 10px 28px;
			margin: 0 12px;
			font-size: 1.1em;
			color: #2956b2;
			font-weight: 600;
			box-shadow: 0 2px 8px rgba(31, 38, 135, 0.08);
			cursor: pointer;
			transition: background 0.2s, transform 0.2s;
		}

		.nav-btn:active {
			background: rgba(41, 86, 178, 0.12);
			transform: scale(0.97);
		}

		.indicator {
			font-size: 1em;
			color: #444;
			margin: 12px 0 0 0;
			font-weight: 500;
		}

		.error {
			color: #dc3545;
			font-size: 1.1em;
			margin-top: 24px;
			text-align: center;
		}

		.file-input {
			margin-bottom: 24px;
			text-align: center;
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

		@media (max-width: 600px) {
			.glass {
				max-width: 98vw;
				padding: 18px 6vw;
			}
		}
	</style>
</head>

<body>
	<button class="back-btn" onclick="window.history.back()">
		<i class="fas fa-arrow-left"></i> Back
	</button>
	<div class="glass" id="pptx-glass">
		<div class="file-info" style="margin-bottom: 24px; text-align: center;">
			<h2 style="color: #2956b2; margin-bottom: 8px;"><?php echo htmlspecialchars($fileName); ?></h2>
			<div style="font-size:0.95em;color:#666;">PPTX Preview - Loading presentation...</div>
		</div>
		<div id="pptx-preview-area"></div>
	</div>
	<script>
		// Enhanced PPTX Preview with PptxGenJS for better formatting
		const previewArea = document.getElementById('pptx-preview-area');
		let slides = [];
		let current = 0;
		let presentation = null;

		// Auto-load PPTX file from server
		<?php if ($filePath): ?>
			document.addEventListener('DOMContentLoaded', async function() {
				previewArea.innerHTML = '<div class="slide-text" style="opacity:0.5;">Loading presentation...</div>';
				slides = [];
				current = 0;
				try {
					const response = await fetch('<?php echo addslashes($filePath); ?>');
					if (!response.ok) throw new Error('Failed to load file');
					const arrayBuffer = await response.arrayBuffer();

					// Use JSZip for better parsing
					const zip = new JSZip();
					const zipData = await zip.loadAsync(arrayBuffer);

					// Extract slides with enhanced formatting
					await extractSlidesWithFormatting(zipData);

					if (slides.length > 0) {
						renderSlide(0);
					} else {
						previewArea.innerHTML = '<div class="error">No slides found in presentation.</div>';
					}
				} catch (err) {
					console.error('PPTX parsing error:', err);
					previewArea.innerHTML = `<div class="error">${err.message || 'Failed to parse .pptx file.'}</div>`;
				}
			});
		<?php else: ?>
			document.addEventListener('DOMContentLoaded', function() {
				previewArea.innerHTML = '<div class="error">No file specified for preview.</div>';
			});
		<?php endif; ?>

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

			// Add keyboard navigation
			document.addEventListener('keydown', handleKeyNavigation);

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
		}

		function handleKeyNavigation(e) {
			if (e.key === 'ArrowLeft' && current > 0) {
				current--;
				renderSlide(current);
			} else if (e.key === 'ArrowRight' && current < slides.length - 1) {
				current++;
				renderSlide(current);
			}
		}

		// Enhanced slide extraction with better formatting
		async function extractSlidesWithFormatting(zipData) {
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

			// Load slide layouts and master slides for better formatting
			const slideLayouts = await loadSlideLayouts(zipData);
			const slideMasters = await loadSlideMasters(zipData);

			for (const slideFile of slidesToProcess) {
				try {
					const slideXml = await zipData.files[slideFile].async('text');
					const slideData = await parseSlideWithEnhancedFormatting(slideXml, slideLayouts, slideMasters);
					slides.push(slideData);
				} catch (error) {
					console.warn(`Failed to parse ${slideFile}:`, error);
					slides.push(`<div class="error">Failed to parse slide</div>`);
				}
			}
		}

		async function loadSlideLayouts(zipData) {
			const layouts = {};
			Object.keys(zipData.files).forEach(async filename => {
				if (filename.match(/^ppt\/slideLayouts\/slideLayout\d+\.xml$/)) {
					try {
						const layoutXml = await zipData.files[filename].async('text');
						const layoutId = filename.match(/slideLayout(\d+)\.xml$/)[1];
						layouts[layoutId] = layoutXml;
					} catch (error) {
						console.warn(`Failed to load layout ${filename}:`, error);
					}
				}
			});
			return layouts;
		}

		async function loadSlideMasters(zipData) {
			const masters = {};
			Object.keys(zipData.files).forEach(async filename => {
				if (filename.match(/^ppt\/slideMasters\/slideMaster\d+\.xml$/)) {
					try {
						const masterXml = await zipData.files[filename].async('text');
						const masterId = filename.match(/slideMaster(\d+)\.xml$/)[1];
						masters[masterId] = masterXml;
					} catch (error) {
						console.warn(`Failed to load master ${filename}:`, error);
					}
				}
			});
			return masters;
		}

		// Enhanced slide parsing with better formatting support
		async function parseSlideWithEnhancedFormatting(slideXml, layouts, masters) {
			const slideData = {
				title: '',
				subtitle: '',
				content: [],
				images: [],
				tables: [],
				charts: [],
				backgroundColor: '',
				textColor: '#333333'
			};

			// Parse slide background and theme
			parseSlideBackground(slideXml, slideData);

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
					slideData.charts.push({
						type: 'chart'
					});
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

		function parseSlideBackground(slideXml, slideData) {
			// Extract background color or theme information
			const bgMatch = slideXml.match(/<p:bg[^>]*>.*?<\/p:bg>/s);
			if (bgMatch) {
				const colorMatch = bgMatch[0].match(/val="([^"]*)"/);
				if (colorMatch) {
					slideData.backgroundColor = colorMatch[1];
				}
			}
		}

		function extractFormattedText(element) {
			// Enhanced text extraction with formatting preservation
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
			const shapes = xml.match(/<p:sp[^>]*>.*?<\/p:sp>/gs) || [];

			shapes.forEach(shape => {
				// Skip title shapes
				if (shape.includes('type="title"')) return;

				// Check for images
				if (shape.includes('<a:blip') || shape.includes('<pic:pic')) {
					slideData.images.push(extractImageInfo(shape));
					return;
				}

				// Check for tables
				if (shape.includes('<a:tbl') || shape.includes('<a:gridCol')) {
					slideData.tables.push(extractTableData(shape));
					return;
				}

				// Check for charts
				if (shape.includes('<c:chart') || shape.includes('chart')) {
					slideData.charts.push('Chart placeholder');
					return;
				}

				// Extract text content with bullet points
				const textContent = extractFormattedText(shape);
				if (textContent && textContent.length > 0) {
					slideData.content.push(textContent);
				}
			});

			return formatSlideContent(slideData);
		}

		function extractTextFromElement(element) {
			const textMatches = element.match(/<a:t>(.*?)<\/a:t>/gs) || [];
			return textMatches.map(match => {
				const text = match.replace(/<a:t>(.*?)<\/a:t>/s, '$1');
				return text.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
			}).join(' ');
		}

		function extractFormattedText(shape) {
			const paragraphs = shape.match(/<a:p[^>]*>.*?<\/a:p>/gs) || [];
			const formattedLines = [];

			paragraphs.forEach(para => {
				// Check for bullet points
				const isBullet = para.includes('<a:buChar') || para.includes('<a:buNone') || para.includes('<a:buAutoNum');
				const level = getBulletLevel(para);

				const text = extractTextFromElement(para);
				if (text.trim()) {
					if (isBullet) {
						formattedLines.push({
							type: 'bullet',
							level: level,
							text: text.trim()
						});
					} else {
						formattedLines.push({
							type: 'text',
							text: text.trim()
						});
					}
				}
			});

			return formattedLines;
		}

		function getBulletLevel(paragraph) {
			const levelMatch = paragraph.match(/lvl="(\d+)"/);
			return levelMatch ? parseInt(levelMatch[1]) : 0;
		}

		function extractImageInfo(shape) {
			// Extract image name or description
			const nameMatch = shape.match(/name="([^"]*)"/);
			const descMatch = shape.match(/descr="([^"]*)"/);
			return {
				name: nameMatch ? nameMatch[1] : 'Image',
				description: descMatch ? descMatch[1] : 'Image placeholder'
			};
		}

		function extractTableData(shape) {
			// Basic table structure extraction
			const rows = shape.match(/<a:tr[^>]*>.*?<\/a:tr>/gs) || [];
			const tableData = [];

			rows.forEach(row => {
				const cells = row.match(/<a:tc[^>]*>.*?<\/a:tc>/gs) || [];
				const rowData = cells.map(cell => extractTextFromElement(cell).trim());
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
					html += `<div class="slide-text">${escapeHtml(contentBlock)}</div>`;
				}
			});

			// Add images
			slideData.images.forEach(img => {
				html += `<div class="image-placeholder">
                    <i class="fas fa-image"></i>
                    <div>${escapeHtml(img.name)}</div>
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
                    <div>Chart</div>
                    <small>Chart content not available in preview</small>
                </div>`;
			});

			html += '</div>';
			return html || '<div class="slide-text">[No content found]</div>';
		}

		function escapeHtml(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}
	</script>
</body>

</html>