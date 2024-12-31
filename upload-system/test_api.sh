#!/bin/bash

# 设置API Token
TOKEN="8799b20c4d65e7d66ef4e3bda29e7eea28ea82d9e2682d51d797b6ae25197c35"
API_BASE="https://nzdata.hexsen.com"  # 修改为你的域名

# 颜色输出
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${GREEN}开始API测试${NC}"

# 测试文件列表
echo -e "\n${GREEN}测试获取文件列表:${NC}"
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "action=list" \
  "${API_BASE}/api/file_manage.php"

# 测试文件上传
echo -e "\n\n${GREEN}测试上传文件:${NC}"
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "files[]=@test.txt" \
  "${API_BASE}/api/upload.php"

# 再次获取文件列表，查看上传结果
echo -e "\n\n${GREEN}再次获取文件列表，确认上传结果:${NC}"
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "action=list" \
  "${API_BASE}/api/file_manage.php"

# 测试删除文件
# 注意：需要替换实际的filename和delete_key
echo -e "\n\n${GREEN}测试删除文件:${NC}"
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "action=delete" \
  -F "filename=67737cea1ff46_f0c52bf44e1c5bcc.txt" \
  -F "delete_key=f97631d22d9204ee2012c47ae2ab333a" \
  "${API_BASE}/api/file_manage.php" 