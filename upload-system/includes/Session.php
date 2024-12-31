<?php
// 设置会话cookie参数
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// 本地开发环境使用 HTTP，关闭 secure 选项
ini_set('session.cookie_secure', 0);

class Session {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_lifetime' => 86400, // 24小时
                'cookie_httponly' => true,
                'cookie_secure' => false,  // 允许 HTTP
                'use_strict_mode' => true
            ]);
        }
    }

    public static function setUser($username, $isAdmin = false) {
        self::start();
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['is_admin'] = $isAdmin;
        $_SESSION['last_activity'] = time();
    }

    public static function isLoggedIn() {
        self::start();
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            return false;
        }
        
        // 检查会话是否过期（24小时）
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 86400)) {
            self::destroy();
            return false;
        }
        
        // 更新最后活动时间
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function isAdmin() {
        self::start();
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }

    public static function getUsername() {
        self::start();
        return $_SESSION['username'] ?? null;
    }

    public static function destroy() {
        self::start();
        $_SESSION = array();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }
} 