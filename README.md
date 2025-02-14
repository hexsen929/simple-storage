# 简单储存

一个轻量级的文件储存系统，基于PHP开发，无需数据库，适合个人或小团队使用。

## 特性

- 🚀 轻量级: 无需数据库，文件式存储
- 👥 多用户: 支持多用户管理，可设置管理员
- 📁 文件管理: 自动按用户/年/月组织文件
- 🔑 API支持: 提供完整的API接口，支持Token认证
- 🖼️ 图片优化: 可选的WebP图片转换功能
- 📱 响应式: 支持移动端和桌面端访问
- 🔒 安全性: 文件删除密钥，防止未授权访问

## 系统要求

- PHP 7.4+
- Apache/Nginx
- PHP GD扩展 (可选，用于WebP转换)

## 安装说明

1. 下载源代码或克隆仓库
2. 将文件上传到Web服务器
3. 确保以下目录可写：
   - `uploads/`
   - `data/`
4. 访问网站，使用默认管理员账号登录：
   - 用户名：admin
   - 密码：admin

## 目录结构

```
upload-system/
├── api/            # API接口文件
├── includes/       # 核心类文件
├── uploads/        # 上传文件存储目录
├── data/          # 配置文件存储目录
├── index.html     # 主页面
├── api_docs.html  # API文档
└── ...
```

## 功能说明

### 用户管理
- 支持管理员和普通用户两种角色
- 管理员可以创建/删除用户
- 用户可以管理自己的文件
- 支持API Token管理

### 文件管理
- 支持拖拽上传
- 自动生成文件链接
- 文件按用户和日期自动归类
- 支持删除和批量删除
- 可选的图片WebP转换功能

### API支持
- RESTful API设计
- Token认证
- 支持文件上传和管理
- 详细的API文档

## 安全说明

- 所有密码经过加密存储
- 文件访问需要授权
- 每个文件都有唯一的删除密钥
- 支持API Token认证

## 使用建议

1. 首次安装后请立即修改管理员密码
2. 定期备份 `data` 目录下的配置文件
3. 根据需要调整文件上传大小限制
4. 建议启用HTTPS以提高安全性

## 开源协议

MIT License

## 贡献指南

欢迎提交Issue和Pull Request来帮助改进这个项目。

## 联系方式

如有问题或建议，请通过GitHub Issues联系。 