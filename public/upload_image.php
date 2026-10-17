<?php
require __DIR__ . '/../src/bootstrap.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit('No file uploaded or upload error');
}

$file = $_FILES['image'];

// NFR-SEC-009 in one gate: content type, decoded-image validity, and a
// filename extension derived from the decoded type rather than the upload's.
$imageCheck = validate_uploaded_image($file);
if (!$imageCheck['ok']) {
    http_response_code(400);
    exit('Invalid or corrupt image file. Only JPEG, PNG, and WebP images are allowed.');
}
$mimeType = $imageCheck['mime'];

$maxFileSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxFileSize) {
    http_response_code(400);
    exit('File too large. Maximum size is 5MB.');
}

$fileExtension = match($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    default => 'bin'
};
$filename = uniqid('listing_', true) . '.' . $fileExtension;
$uploadPath = listing_upload_dir() . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    exit('Failed to upload file');
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'filename' => $filename,
    'path' => '/uploads/listings/' . $filename
]);
exit;