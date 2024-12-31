    case 'download':
        // API请求
        if (getBearerToken()) {
            $authResult = checkApiAuth($auth);
        } 
        // 网页请求
        else {
            $authResult = checkWebAuth();
        }
        
        if (!$authResult['success']) {
            header('Content-Type: application/json');
            echo json_encode($authResult);
            break;
        }

        $filename = $_GET['filename'] ?? '';
        
        if (!$filename) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的文件名']);
            break;
        }

        try {
            require_once '../includes/FileHandler.php';
            $fileHandler = new FileHandler();
            
            // 检查文件是否存在并获取文件信息
            $fileInfo = $fileHandler->getFileInfo($filename);
            if (!$fileInfo) {
                throw new Exception('文件不存在');
            }
            
            // 检查用户是否有权限下载此文件
            if ($fileInfo['username'] !== $authResult['username'] && !$authResult['is_admin']) {
                throw new Exception('无权访问此文件');
            }
            
            $filePath = $fileHandler->getFilePath($filename);
            if (!file_exists($filePath)) {
                throw new Exception('文件不存在');
            }

            // 设置响应头
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . rawurlencode($fileInfo['original_name']) . '"');
            header('Content-Length: ' . filesize($filePath));
            
            // 输出文件内容
            readfile($filePath);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => '下载文件失败: ' . $e->getMessage()]);
        }
        break; 