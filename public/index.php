<?php
// Cấu hình thư mục upload
$uploadDir = __DIR__ . '/../storage/';
// đảm bảo thư mục storage tồn tại
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// lấy đường dẫn tuyệt đối
$uploadDirAbs = realpath($uploadDir);
if ($uploadDirAbs === false) die("Không tìm thấy thư mục storage");
$uploadDirAbs = str_replace('/', '\\', $uploadDirAbs) . '\\';

$resultText = "";
$error = "";

// ---- Đường dẫn magick.exe (cập nhật theo máy) ----
$magickPath = 'C:\\Program Files\\ImageMagick-7.1.2-Q16-HDRI\\magick.exe';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lang = $_POST['lang'] ?? 'vie+eng';

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp  = $_FILES['image']['tmp_name'];
        $fileName = uniqid('ocr_') . '.png';
        $targetFile = $uploadDirAbs . $fileName;

        // move file upload
        move_uploaded_file($fileTmp, $targetFile);

        $processedFile = $uploadDirAbs . 'processed_' . $fileName;

        // --- Tiền xử lý ảnh bằng ImageMagick CLI ---
        if (!file_exists($targetFile)) die("File upload không tồn tại: $targetFile");

        $cmdPre = '"' . $magickPath . '" ' . escapeshellarg($targetFile) .
                  ' -colorspace Gray -contrast -sharpen 0x1 -resize 2000 ' .
                  escapeshellarg($processedFile);

        exec($cmdPre . " 2>&1", $outputPre, $retPre);

        if ($retPre !== 0) {
            $error = "Lỗi xử lý ảnh bằng ImageMagick.<br>" .
                     "Lệnh: " . htmlspecialchars($cmdPre) . "<br>" .
                     "Output:<pre>" . implode("\n", $outputPre) . "</pre>";
        } else {
            // --- OCR bằng Tesseract ---
            $outputFile = $uploadDirAbs . 'out_' . uniqid();
            $cmdOCR = "tesseract " . escapeshellarg($processedFile) . " " . escapeshellarg($outputFile) .
                      " -l " . escapeshellarg($lang);
            exec($cmdOCR . " 2>&1", $outputOCR, $retOCR);

            if ($retOCR !== 0) {
                $error = "Lỗi OCR bằng Tesseract.<br>Output:<pre>" . implode("\n", $outputOCR) . "</pre>";
            } else {
                $resultText = file_get_contents($outputFile . ".txt");
                unlink($outputFile . ".txt");
            }
        }

        // Xóa file tạm
        if (file_exists($targetFile)) unlink($targetFile);
        if (file_exists($processedFile)) unlink($processedFile);

    } else {
        $error = "Vui lòng chọn file ảnh hợp lệ.";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>OCR Windows-ready</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 30px; background: #f9f9f9; }
    h1 { color: #333; }
    form { margin-bottom: 20px; padding: 20px; background: #fff; border-radius: 8px; }
    textarea { width: 100%; height: 300px; padding: 10px; }
    .error { color: red; margin-top: 10px; }
    .result { margin-top: 20px; }
    button { padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; }
    button:hover { background: #45a049; }
  </style>
</head>
<body>
  <h1>OCR Tiếng Việt + English (Windows-ready)</h1>
  <form method="POST" enctype="multipart/form-data">
    <label>Chọn ảnh:</label><br>
    <input type="file" name="image" required><br><br>

    <label>Ngôn ngữ OCR:</label>
    <select name="lang">
      <option value="vie+eng" selected>Vietnamese + English</option>
      <option value="vie">Vietnamese</option>
      <option value="eng">English</option>
    </select><br><br>

    <button type="submit">Nhận dạng OCR</button>
  </form>

  <?php if ($error): ?>
    <div class="error"><?= $error ?></div>
  <?php endif; ?>

  <?php if ($resultText): ?>
    <div class="result">
      <h2>Kết quả OCR:</h2>
      <textarea readonly><?= htmlspecialchars($resultText) ?></textarea>
      <br><br>
      <a href="data:text/plain;charset=utf-8,<?= urlencode($resultText) ?>" download="ocr_result.txt">
        <button>Tải kết quả</button>
      </a>
    </div>
  <?php endif; ?>
</body>
</html>
