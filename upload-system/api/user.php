<?php
require_once '../includes/Auth.php';
require_once '../includes/Session.php';

// 禁用错误显示，防止错误信息破坏 JSON 输出
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

$auth = new Auth();
$action = $_GET['action'] ?? '';

// 检查 Bearer Token
function getBearerToken() {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

// 验证API Token认证
function checkApiAuth($auth, $requireAdmin = false) {
    $token = getBearerToken();
    if (!$token) {
        return ['success' => false, 'error' => '缺少Token认证'];
    }
    
    $tokenInfo = $auth->getTokenInfo($token);
    if (!$tokenInfo) {
        return ['success' => false, 'error' => '无效的Token'];
    }
    
    if ($requireAdmin && !$tokenInfo['is_admin']) {
        return ['success' => false, 'error' => '无权限'];
    }
    
    return ['success' => true, 'username' => $tokenInfo['username'], 'is_admin' => $tokenInfo['is_admin']];
}

// 验证网页Session认证
function checkWebAuth($requireAdmin = false) {
    if (!Session::isLoggedIn()) {
        return ['success' => false, 'error' => '未登录'];
    }
    
    if ($requireAdmin && !Session::isAdmin()) {
        return ['success' => false, 'error' => '无权限'];
    }
    
    return ['success' => true, 'username' => Session::getUsername(), 'is_admin' => Session::isAdmin()];
}

switch ($action) {
    case 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['username']) || !isset($data['password'])) {
            echo json_encode(['success' => false, 'error' => '无效的请求数据']);
            break;
        }
        try {
            // API登录
            if (getBearerToken()) {
                $result = $auth->validateUser($data['username'], $data['password']);
            } 
            // 网页登录
            else {
                $result = $auth->validateUser($data['username'], $data['password']);
                if ($result['success']) {
                    Session::setUser($data['username'], $result['is_admin']);
                    unset($result['token']); // 网页登录不需要token
                }
            }
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => '登录失败：' . $e->getMessage()]);
        }
        break;

    case 'logout':
        Session::destroy();
        echo json_encode(['success' => true]);
        break;

    case 'check_login':
        // API请求：验证token
        if (getBearerToken()) {
            $authResult = checkApiAuth($auth);
        } 
        // 网页请求：验证session
        else {
            $authResult = checkWebAuth();
        }
        echo json_encode($authResult);
        break;

    case 'get_user_token':
        // 只允许已登录的网页用户查看token
        $authResult = checkWebAuth();
        if (!$authResult['success']) {
            echo json_encode($authResult);
            break;
        }
        try {
            $tokens = json_decode(file_get_contents(__DIR__ . '/../data/tokens.json'), true);
            $userToken = null;
            foreach ($tokens as $token => $info) {
                if ($info['username'] === $authResult['username']) {
                    $userToken = $token;
                    break;
                }
            }
            echo json_encode(['success' => true, 'token' => $userToken]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => '获取Token失败']);
        }
        break;

    case 'reset_user_token':
        // 只允许已登录的网页用户重置token
        $authResult = checkWebAuth();
        if (!$authResult['success']) {
            echo json_encode($authResult);
            break;
        }
        try {
            $result = $auth->refreshToken($authResult['username']);
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => '重置Token失败']);
        }
        break;

    case 'create_user':
        // API请求
        if (getBearerToken()) {
            $authResult = checkApiAuth($auth, true);
        } 
        // 网页请求
        else {
            $authResult = checkWebAuth(true);
        }
        
        if (!$authResult['success']) {
            echo json_encode($authResult);
            break;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['username']) || !isset($data['password'])) {
            echo json_encode(['success' => false, 'error' => '无效的请求数据']);
            break;
        }
        try {
            $result = $auth->createUser($data['username'], $data['password'], $data['role'] === 'admin');
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => '创建用户失败：' . $e->getMessage()]);
        }
        break;

    case 'list_users':
        // API请求
        if (getBearerToken()) {
            $authResult = checkApiAuth($auth, true);
        } 
        // 网页请求
        else {
            $authResult = checkWebAuth(true);
        }
        
        if (!$authResult['success']) {
            echo json_encode($authResult);
            break;
        }
        try {
            $users = $auth->listUsers();
            echo json_encode(['success' => true, 'users' => $users]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => '获取用户列表失败']);
        }
        break;

    case 'list_user_files':
        // API请求
        if (getBearerToken()) {
            $authResult = checkApiAuth($auth, true);
        } 
        // 网页请求
        else {
            $authResult = checkWebAuth(true);
        }
        
        if (!$authResult['success']) {
            echo json_encode($authResult);
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['username'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的请求数据']);
            break;
        }

        try {
            require_once '../includes/FileHandler.php';
            $fileHandler = new FileHandler();
            $files = $fileHandler->getFilesList($data['username']);
            echo json_encode(['success' => true, 'files' => $files]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => '获取用户文件列表失败: ' . $e->getMessage()]);
        }
        break;

    case 'delete_user':
        // API请求
        if (getBearerToken()) {
            $authResult = checkApiAuth($auth, true);
        } 
        // 网页请求
        else {
            $authResult = checkWebAuth(true);
        }
        
        if (!$authResult['success']) {
            echo json_encode($authResult);
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['username'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的请求数据']);
            break;
        }

        try {
            require_once '../includes/FileHandler.php';
            $fileHandler = new FileHandler();
            $userFiles = $fileHandler->getFilesList($data['username']);
            
            // 删除选中的文件
            $filesToDelete = $data['files_to_delete'] ?? [];
            $deletedFiles = 0;
            
            foreach ($filesToDelete as $file) {
                if ($fileHandler->deleteFile($file['filename'], $file['delete_key'])) {
                    $deletedFiles++;
                }
            }
            
            // 删除用户
            $result = $auth->deleteUser($data['username']);
            if ($result['success']) {
                $result['message'] = sprintf(
                    '用户已删除。用户共有 %d 个文件，已删除 %d 个文件，保留 %d 个文件',
                    count($userFiles),
                    $deletedFiles,
                    count($userFiles) - $deletedFiles
                );
            }
            
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => '删除用户失败: ' . $e->getMessage()]);
        }
        break;

    case 'reset_password':
        // API请求
        if (getBearerToken()) {
            $authResult = checkApiAuth($auth, true);
        } 
        // 网页请求
        else {
            $authResult = checkWebAuth(true);
        }
        
        if (!$authResult['success']) {
            echo json_encode($authResult);
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['target_username'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的请求数据']);
            break;
        }

        try {
            $result = $auth->resetPassword($data['target_username']);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => '重置密码失败: ' . $e->getMessage()]);
        }
        break;

    case 'change_password':
        // API请求
        if (getBearerToken()) {
            $authResult = checkApiAuth($auth);
        } 
        // 网页请求
        else {
            $authResult = checkWebAuth();
        }
        
        if (!$authResult['success']) {
            echo json_encode($authResult);
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['current_password']) || !isset($data['new_password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的请求数据']);
            break;
        }

        try {
            $result = $auth->changePassword($authResult['username'], $data['current_password'], $data['new_password']);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => '修改密码失败: ' . $e->getMessage()]);
        }
        break;

    case 'update_user_settings':
        // API请求
        if (getBearerToken()) {
            $authResult = checkApiAuth($auth, true);
        } 
        // 网页请求
        else {
            $authResult = checkWebAuth(true);
        }
        
        if (!$authResult['success']) {
            echo json_encode($authResult);
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['username'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的请求数据']);
            break;
        }

        try {
            $settingsFile = __DIR__ . '/../data/user_settings.json';
            $settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
            
            $settings[$data['username']] = [
                'convert_to_webp' => $data['convert_to_webp'] ?? false
            ];
            
            file_put_contents($settingsFile, json_encode($settings));
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => '更新用户设置失败: ' . $e->getMessage()]);
        }
        break;

    case 'get_user_settings':
        // API请求
        if (getBearerToken()) {
            $authResult = checkApiAuth($auth, true);
        } 
        // 网页请求
        else {
            $authResult = checkWebAuth(true);
        }
        
        if (!$authResult['success']) {
            echo json_encode($authResult);
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['username'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的请求数据']);
            break;
        }

        try {
            $settingsFile = __DIR__ . '/../data/user_settings.json';
            $settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
            $userSettings = $settings[$data['username']] ?? ['convert_to_webp' => false];
            
            echo json_encode(['success' => true, 'settings' => $userSettings]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => '获取用户设置失败: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '无效的操作']);
        break;
} 