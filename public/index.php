<?php
// index.php - Mini OCR app (PHP + Tesseract)
// Hỗ trợ: upload ảnh hoặc nhập URL → OCR tiếng Việt + English

// --- Cấu hình ---
$uploadDir = __DIR__ . '/../storage/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$maxSize = 25 * 1024 * 1024; // 25MB
$languages = "vie+eng"; // mặc định OCR cả tiếng Việt và Anh

$resultText = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- chọn ngôn ngữ ---
        if (!empty($_POST['langs'])) {
            $languages = implode('+', $_POST['langs']);
        }

        $filePath = "";
        // --- upload file ---
        if (!empty($_FILES['file']['tmp_name'])) {
            if ($_FILES['file']['size'] > $maxSize) {
                throw new Exception("File quá lớn (>25MB).");
            }
            $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $filePath = $uploadDir . uniqid("ocr_") . "." . $ext;
            move_uploaded_file($_FILES['file']['tmp_name'], $filePath);
        }
        // --- lấy từ URL ---
        elseif (!empty($_POST['image_url'])) {
            $url = $_POST['image_url'];
            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: "jpg";
            $filePath = $uploadDir . uniqid("ocr_") . "." . $ext;
            file_put_contents($filePath, file_get_contents($url));
        } else {
            throw new Exception("Vui lòng chọn file hoặc nhập URL.");
        }

        // --- chạy Tesseract ---
        $outputBase = $filePath . "_out";
        $cmd = "tesseract " . escapeshellarg($filePath) . " " . escapeshellarg($outputBase) . " -l " . escapeshellarg($languages) . " --psm 3";
        exec($cmd . " 2>&1", $out, $code);

        if ($code !== 0) {
            throw new Exception("Lỗi Tesseract: " . implode("\n", $out));
        }

        $txtFile = $outputBase . ".txt";
        if (file_exists($txtFile)) {
            $resultText = file_get_contents($txtFile);
        } else {
            throw new Exception("Không tìm thấy file kết quả OCR.");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Mini OCR (PHP + Tesseract)</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f6f7f9; }
    h1 { color: #333; }
    .card { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,.1); }
    input, textarea { width: 100%; padding: 10px; margin-top: 5px; border-radius: 6px; border: 1px solid #ccc; }
    .btn { padding: 10px 15px; background: #111; color: #fff; border: none; border-radius: 6px; cursor: pointer; margin-top: 10px; }
    .error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; }
    textarea { min-height: 250px; font-family: Consolas, monospace; }
  </style>
</head>
<body>
  <h1>Mini OCR (PHP + Tesseract)</h1>

  <div class="card">
    <form method="post" enctype="multipart/form-data">
      <label>Chọn file ảnh (JPG, PNG...):</label>
      <input type="file" name="file" accept="image/*"><br><br>

      <label>Hoặc nhập URL ảnh:</label>
      <input type="text" name="image_url" placeholder="https://example.com/image.jpg"><br><br>

      <label>Ngôn ngữ OCR:</label><br>
      <input type="checkbox" name="langs[]" value="vie" checked> Tiếng Việt
      <input type="checkbox" name="langs[]" value="eng" checked> English<br><br>

      <button type="submit" class="btn">Nhận dạng văn bản</button>
    </form>
  </div>

  <?php if ($error): ?>
    <div class="card error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($resultText): ?>
    <div class="card">
      <h3>Kết quả OCR:</h3>
      <textarea readonly><?= htmlspecialchars($resultText) ?></textarea>
    </div>
  <?php endif; ?>
</body>
</html>
