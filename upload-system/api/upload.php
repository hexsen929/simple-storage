<?php
require_once '../includes/Auth.php';
require_once '../includes/FileHandler.php';
require_once '../includes/Session.php';

header('Content-Type: application/json');

try {
    $username = null;
    
    // 先检查Session认证
    if (Session::isLoggedIn()) {
        $username = Session::getUsername();
    }
    // 如果有token，则验证token
    else {
        $auth = new Auth();
        $headers = getallheaders();
        $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
        $tokenInfo = $auth->validateToken($token);
        
        if ($tokenInfo) {
            $username = $tokenInfo['username'];
        }
    }
    
    // 如果既没有Session也没有有效的token
    if (!$username) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录']);
        exit;
    }
    
    $fileHandler = new FileHandler();
    
    // 处理单文件上传
    if (isset($_FILES['file'])) {
        $result = $fileHandler->handleUpload($_FILES['file'], $username);
        echo json_encode($result);
        exit;
    }
    
    // 处理多文件上传
    if (isset($_FILES['files'])) {
        $results = [];
        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            $file = [
                'name' => $_FILES['files']['name'][$key],
                'type' => $_FILES['files']['type'][$key],
                'tmp_name' => $tmp_name,
                'error' => $_FILES['files']['error'][$key],
                'size' => $_FILES['files']['size'][$key]
            ];
            $result = $fileHandler->handleUpload($file, $username);
            if ($result['success']) {
                $results[] = $result;
            }
        }
        echo json_encode(['success' => true, 'files' => $results]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '未找到上传的文件']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 