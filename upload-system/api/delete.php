<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/FileHandler.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '方法不允许']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['filename']) || !isset($data['delete_key'])) {
    http_response_code(400);
    echo json_encode(['error' => '参数错误']);
    exit;
}

$fileHandler = new FileHandler();
$response = $fileHandler->deleteFile($data['filename'], $data['delete_key']);

echo json_encode($response); 