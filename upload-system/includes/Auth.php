<?php
class Auth {
    private $usersFile;
    private $tokenFile;
    
    public function __construct() {
        $this->usersFile = __DIR__ . '/../data/users.json';
        $this->tokenFile = __DIR__ . '/../data/tokens.json';
        
        // 确保目录和文件存在
        if (!file_exists(dirname($this->usersFile))) {
            mkdir(dirname($this->usersFile), 0777, true);
        }
        if (!file_exists($this->usersFile)) {
            // 创建默认管理员账户
            $defaultAdmin = [
                'admin' => [
                    'password' => password_hash('admin123', PASSWORD_DEFAULT),
                    'is_admin' => true,
                    'created_at' => time()
                ]
            ];
            file_put_contents($this->usersFile, json_encode($defaultAdmin));
            
            // 为默认管理员生成token
            $adminToken = bin2hex(random_bytes(32));
            $tokens = [
                $adminToken => [
                    'username' => 'admin',
                    'is_admin' => true,
                    'created_at' => time()
                ]
            ];
            file_put_contents($this->tokenFile, json_encode($tokens));
        }
        if (!file_exists($this->tokenFile)) {
            file_put_contents($this->tokenFile, json_encode([]));
        }
    }
    
    public function validateUser($username, $password) {
        $users = json_decode(file_get_contents($this->usersFile), true);
        
        if (!isset($users[$username])) {
            return ['success' => false, 'error' => '用户不存在'];
        }
        
        if (!password_verify($password, $users[$username]['password'])) {
            return ['success' => false, 'error' => '密码错误'];
        }
        
        // 如果是API请求，返回用户的token
        $tokens = json_decode(file_get_contents($this->tokenFile), true);
        $userToken = null;
        foreach ($tokens as $token => $info) {
            if ($info['username'] === $username) {
                $userToken = $token;
                break;
            }
        }
        
        return [
            'success' => true,
            'is_admin' => $users[$username]['is_admin'],
            'token' => $userToken
        ];
    }

    public function validateToken($token) {
        if (!$token) {
            return null;
        }
        
        $tokens = json_decode(file_get_contents($this->tokenFile), true);
        
        if (!isset($tokens[$token])) {
            return null;
        }
        
        return $tokens[$token];
    }
    
    public function refreshToken($username) {
        $users = json_decode(file_get_contents($this->usersFile), true);
        $tokens = json_decode(file_get_contents($this->tokenFile), true);
        
        if (!isset($users[$username])) {
            return ['success' => false, 'error' => '用户不存在'];
        }
        
        // 删除旧token
        foreach ($tokens as $oldToken => $info) {
            if ($info['username'] === $username) {
                unset($tokens[$oldToken]);
            }
        }
        
        // 生成新token
        $newToken = bin2hex(random_bytes(32));
        $tokens[$newToken] = [
            'username' => $username,
            'is_admin' => $users[$username]['is_admin'],
            'created_at' => time()
        ];
        file_put_contents($this->tokenFile, json_encode($tokens));
        
        return [
            'success' => true,
            'token' => $newToken,
            'is_admin' => $users[$username]['is_admin']
        ];
    }
    
    public function createUser($username, $password, $isAdmin = false) {
        $users = json_decode(file_get_contents($this->usersFile), true);
        
        if (isset($users[$username])) {
            return ['success' => false, 'error' => '用户名已存在'];
        }
        
        $users[$username] = [
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'is_admin' => $isAdmin,
            'created_at' => time()
        ];
        
        // 为新用户生成永久token
        $tokens = json_decode(file_get_contents($this->tokenFile), true);
        $newToken = bin2hex(random_bytes(32));
        $tokens[$newToken] = [
            'username' => $username,
            'is_admin' => $isAdmin,
            'created_at' => time()
        ];
        
        file_put_contents($this->usersFile, json_encode($users));
        file_put_contents($this->tokenFile, json_encode($tokens));
        
        return ['success' => true];
    }
    
    public function listUsers() {
        $users = json_decode(file_get_contents($this->usersFile), true);
        $userList = [];
        
        foreach ($users as $username => $info) {
            $userList[] = [
                'username' => $username,
                'is_admin' => $info['is_admin'],
                'created_at' => $info['created_at']
            ];
        }
        
        return $userList;
    }
    
    public function changePassword($username, $currentPassword, $newPassword) {
        $users = json_decode(file_get_contents($this->usersFile), true);
        
        if (!isset($users[$username])) {
            return ['success' => false, 'error' => '用户不存在'];
        }
        
        // 验证当前密码
        if (!password_verify($currentPassword, $users[$username]['password'])) {
            return ['success' => false, 'error' => '当前密码错误'];
        }
        
        // 更新密码
        $users[$username]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        file_put_contents($this->usersFile, json_encode($users));
        
        return ['success' => true];
    }
    
    public function resetPassword($username) {
        $users = json_decode(file_get_contents($this->usersFile), true);
        
        if (!isset($users[$username])) {
            return ['success' => false, 'error' => '用户不存在'];
        }
        
        // 生成新密码
        $newPassword = substr(md5(uniqid()), 0, 8);
        $users[$username]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        
        file_put_contents($this->usersFile, json_encode($users));
        
        return [
            'success' => true,
            'new_password' => $newPassword
        ];
    }

    public function deleteUser($username) {
        $users = json_decode(file_get_contents($this->usersFile), true);
        $tokens = json_decode(file_get_contents($this->tokenFile), true);
        
        if (!isset($users[$username])) {
            return ['success' => false, 'error' => '用户不存在'];
        }
        
        // 删除用户的所有token
        foreach ($tokens as $token => $info) {
            if ($info['username'] === $username) {
                unset($tokens[$token]);
            }
        }
        file_put_contents($this->tokenFile, json_encode($tokens));
        
        // 删除用户
        unset($users[$username]);
        file_put_contents($this->usersFile, json_encode($users));
        
        return ['success' => true];
    }

    public function getTokenInfo($token) {
        return $this->validateToken($token);
    }
} 