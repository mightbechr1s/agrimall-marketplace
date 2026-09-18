<?php
require __DIR__ . '/../src/bootstrap.php';

// Require login
require_login();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Check CSRF token
csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

// Check if file was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit('No file uploaded or upload error');
}

$file = $_FILES['image'];

// Validate file type
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimeTypes)) {
    http_response_code(400);
    exit('Invalid file type. Only JPEG, PNG, and WebP images are allowed.');
}

// Validate file size (5MB max)
$maxFileSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxFileSize) {
    http_response_code(400);
    exit('File too large. Maximum size is 5MB.');
}

// Generate unique filename
$fileExtension = match($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    default => 'bin'
};
$filename = uniqid('listing_', true) . '.' . $fileExtension;
$uploadPath = __DIR__ . '/uploads/listings/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    exit('Failed to upload file');
}

// Return success response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'filename' => $filename,
    'path' => '/uploads/listings/' . $filename
]);
exit;