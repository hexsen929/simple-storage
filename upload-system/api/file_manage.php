<?php
require_once '../includes/Auth.php';
require_once '../includes/FileHandler.php';
require_once '../includes/Session.php';

header('Content-Type: application/json');

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '不支持的请求方法']);
    exit;
}

$auth = new Auth();
$tokenInfo = null;
$isAdmin = false;
$username = null;

// 获取请求头中的token
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

// 先检查Session认证
if (Session::isLoggedIn()) {
    $isAdmin = Session::isAdmin();
    $username = Session::getUsername();
}
// 如果有token，则验证token
else if ($token) {
    $tokenInfo = $auth->validateToken($token);
    if ($tokenInfo) {
        $isAdmin = $tokenInfo['is_admin'];
        $username = $tokenInfo['username'];
    }
}

// 如果既没有Session也没有有效的token
if (!Session::isLoggedIn() && !$tokenInfo) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$fileHandler = new FileHandler();
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        // 如果是管理员，可以查看指定用户的文件
        $targetUsername = null;
        if ($isAdmin) {
            // 如果提供了username参数且不为空，则使用该参数
            if (isset($_POST['username']) && !empty($_POST['username'])) {
                $targetUsername = $_POST['username'];
            }
            // 如果没有提供username参数或为空，则返回所有文件
        } else {
            // 非管理员只能查看自己的文件
            $targetUsername = $username;
        }
        
        $files = $fileHandler->getFilesList($targetUsername);
        echo json_encode(['success' => true, 'files' => $files]);
        break;
        
    case 'delete':
        if (!isset($_POST['filename']) || !isset($_POST['delete_key'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '缺少必要参数']);
            exit;
        }
        
        $fileInfo = $fileHandler->getFileInfo($_POST['filename']);
        
        // 检查文件所有权或管理员权限
        if (!$fileInfo || ($fileInfo['username'] !== $username && !$isAdmin)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => '没有权限删除此文件']);
            exit;
        }
        
        $result = $fileHandler->deleteFile($_POST['filename'], $_POST['delete_key']);
        echo json_encode($result);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '未知的操作类型']);
        break;
} 