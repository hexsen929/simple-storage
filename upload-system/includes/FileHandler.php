<?php
class FileHandler {
    private $uploadsDir;
    private $filesInfoFile;
    private $userSettingsFile;
    
    public function __construct() {
        $this->uploadsDir = __DIR__ . '/../uploads';
        $this->filesInfoFile = __DIR__ . '/../data/files.json';
        $this->userSettingsFile = __DIR__ . '/../data/user_settings.json';
        
        // 确保目录和文件存在
        if (!file_exists($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0777, true);
        }
        if (!file_exists(dirname($this->filesInfoFile))) {
            mkdir(dirname($this->filesInfoFile), 0777, true);
        }
        if (!file_exists($this->filesInfoFile)) {
            file_put_contents($this->filesInfoFile, json_encode([]));
        }
        if (!file_exists($this->userSettingsFile)) {
            file_put_contents($this->userSettingsFile, json_encode([]));
        }
    }
    
    private function createUserDirectory($username) {
        $year = date('Y');
        $month = date('m');
        $userDir = $this->uploadsDir . '/' . $username;
        $yearDir = $userDir . '/' . $year;
        $monthDir = $yearDir . '/' . $month;
        
        // 创建用户目录结构
        if (!file_exists($userDir)) {
            mkdir($userDir, 0777, true);
        }
        if (!file_exists($yearDir)) {
            mkdir($yearDir, 0777, true);
        }
        if (!file_exists($monthDir)) {
            mkdir($monthDir, 0777, true);
        }
        
        return $monthDir;
    }
    
    // 获取用户设置
    private function getUserSettings($username) {
        $settings = json_decode(file_get_contents($this->userSettingsFile), true);
        return $settings[$username] ?? [
            'convert_to_webp' => false
        ];
    }
    
    // 转换图片为WebP格式
    private function convertToWebP($sourcePath, $targetPath) {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $image = null;

        // 根据原始图片格式加载图片
        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case 'png':
                $image = imagecreatefrompng($sourcePath);
                // 保持PNG的透明度
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
            case 'gif':
                $image = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }

        if (!$image) {
            return false;
        }

        // 转换为WebP
        $result = imagewebp($image, $targetPath, 80); // 80是质量参数
        imagedestroy($image);

        return $result;
    }
    
    public function handleUpload($file, $username) {
        // 验证文件上传是否成功
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => '文件上传失败'];
        }
        
        // 获取用户设置
        $userSettings = $this->getUserSettings($username);
        
        // 生成更有意义的文件名
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $timestamp = time();
        $randomStr = substr(md5(uniqid()), 0, 8);
        $safeUsername = preg_replace('/[^a-zA-Z0-9]/', '_', $username);
        
        // 检查是否支持WebP转换
        $canConvertToWebP = extension_loaded('gd') && function_exists('imagewebp');
        
        // 如果需要转换为WebP且是支持的图片格式，并且GD扩展可用
        $shouldConvertToWebP = $canConvertToWebP && 
            $userSettings['convert_to_webp'] && 
            in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif']) &&
            strpos($file['type'], 'image/') === 0;
        
        $filename = sprintf('%s_%s_%s%s',
            $timestamp,
            $randomStr,
            $safeUsername,
            $shouldConvertToWebP ? '.webp' : ($extension ? '.' . strtolower($extension) : '')
        );
        
        // 创建用户的年月目录结构
        $targetDir = $this->createUserDirectory($username);
        
        // 移动文件到目标位置
        $filePath = $targetDir . '/' . $filename;
        $tempPath = $file['tmp_name'];
        
        if ($shouldConvertToWebP) {
            // 尝试转换为WebP
            if (!$this->convertToWebP($tempPath, $filePath)) {
                // 如果转换失败，直接保存原始文件
                if (!move_uploaded_file($tempPath, $filePath)) {
                    return ['success' => false, 'error' => '文件保存失败'];
                }
                $shouldConvertToWebP = false; // 重置转换标志
            }
        } else {
            // 直接移动文件
            if (!move_uploaded_file($tempPath, $filePath)) {
                return ['success' => false, 'error' => '文件保存失败'];
            }
        }
        
        // 生成删除密钥
        $deleteKey = bin2hex(random_bytes(16));
        
        // 计算文件哈希
        $md5 = md5_file($filePath);
        
        // 保存更详细的文件信息
        $filesInfo = json_decode(file_get_contents($this->filesInfoFile), true);
        $filesInfo[$filename] = [
            'original_name' => $file['name'],
            'stored_path' => $filePath,
            'relative_path' => $username . '/' . date('Y') . '/' . date('m') . '/' . $filename,
            'size' => filesize($filePath),
            'mime_type' => $shouldConvertToWebP ? 'image/webp' : $file['type'],
            'username' => $username,
            'upload_time' => $timestamp,
            'delete_key' => $deleteKey,
            'md5' => $md5,
            'extension' => $shouldConvertToWebP ? 'webp' : strtolower($extension),
            'converted_from' => $shouldConvertToWebP ? strtolower($extension) : null,
            'downloads' => 0,
            'last_access' => $timestamp
        ];
        file_put_contents($this->filesInfoFile, json_encode($filesInfo));
        
        return [
            'success' => true,
            'filename' => $filename,
            'original_name' => $file['name'],
            'size' => filesize($filePath),
            'download_url' => $this->getDownloadUrl($filename),
            'delete_key' => $deleteKey,
            'md5' => $md5,
            'converted_to_webp' => $shouldConvertToWebP
        ];
    }
    
    public function deleteFile($filename, $deleteKey) {
        $filesInfo = json_decode(file_get_contents($this->filesInfoFile), true);
        
        if (!isset($filesInfo[$filename])) {
            return ['success' => false, 'error' => '文件不存在'];
        }
        
        if ($filesInfo[$filename]['delete_key'] !== $deleteKey) {
            return ['success' => false, 'error' => '删除密钥错误'];
        }
        
        // 删除文件
        if (file_exists($filesInfo[$filename]['stored_path'])) {
            unlink($filesInfo[$filename]['stored_path']);
        }
        
        // 删除文件信息
        unset($filesInfo[$filename]);
        file_put_contents($this->filesInfoFile, json_encode($filesInfo));
        
        return ['success' => true];
    }
    
    public function getFilesList($username = null) {
        $filesInfo = json_decode(file_get_contents($this->filesInfoFile), true);
        $files = [];
        
        foreach ($filesInfo as $filename => $info) {
            // 如果指定了用户名，只返回该用户的文件
            if ($username && $info['username'] !== $username) {
                continue;
            }
            
            $files[] = [
                'filename' => $filename,
                'original_name' => $info['original_name'],
                'size' => $info['size'],
                'mime_type' => $info['mime_type'],
                'username' => $info['username'],
                'upload_time' => $info['upload_time'],
                'download_url' => $this->getDownloadUrl($filename),
                'delete_key' => $info['delete_key'],
                'md5' => $info['md5'],
                'downloads' => $info['downloads'] ?? 0,
                'last_access' => $info['last_access'] ?? $info['upload_time']
            ];
        }
        
        // 按上传时间降序排序
        usort($files, function($a, $b) {
            return $b['upload_time'] - $a['upload_time'];
        });
        
        return $files;
    }
    
    public function getFileInfo($filename) {
        $filesInfo = json_decode(file_get_contents($this->filesInfoFile), true);
        return $filesInfo[$filename] ?? null;
    }
    
    private function getDownloadUrl($filename) {
        $filesInfo = json_decode(file_get_contents($this->filesInfoFile), true);
        if (!isset($filesInfo[$filename])) {
            return null;
        }
        return '/uploads/' . $filesInfo[$filename]['relative_path'];
    }
    
    // 更新文件访问信息
    public function updateFileAccess($filename) {
        $filesInfo = json_decode(file_get_contents($this->filesInfoFile), true);
        if (isset($filesInfo[$filename])) {
            $filesInfo[$filename]['downloads'] = ($filesInfo[$filename]['downloads'] ?? 0) + 1;
            $filesInfo[$filename]['last_access'] = time();
            file_put_contents($this->filesInfoFile, json_encode($filesInfo));
        }
    }
} 