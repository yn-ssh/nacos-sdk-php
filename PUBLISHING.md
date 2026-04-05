# 发布到 Packagist 指南

本指南将帮助您将 Nacos SDK for PHP 发布到 Packagist.org。

## 准备工作

### 1. 确保项目配置正确

- **composer.json** 文件已经配置完成，包含了正确的包名、描述、依赖等信息
- **README.md** 文件已经完善，包含了项目的使用说明和文档
- 所有测试都已通过

### 2. 创建版本标签

为了发布到 Packagist，您需要为项目创建版本标签。建议使用语义化版本号（Semantic Versioning）。

```bash
# 查看当前状态
git status

# 添加所有更改
git add .

# 提交更改
git commit -m "Prepare for release"

# 创建版本标签（例如 v1.0.0）
git tag v1.0.0

# 推送到远程仓库
git push origin v1.0.0
```

## 发布步骤

### 1. 在 GitHub 上创建仓库

1. 登录 GitHub 账号
2. 创建一个新的仓库，命名为 `nacos-sdk-php`
3. 将本地项目推送到 GitHub 仓库：

```bash
# 初始化 git（如果尚未初始化）
git init

# 添加远程仓库
git remote add origin https://github.com/your-username/nacos-sdk-php.git

# 推送到远程仓库
git push -u origin master
```

### 2. 在 Packagist 上注册账户

1. 访问 [Packagist.org](https://packagist.org/)
2. 点击 "Sign Up" 创建账户
3. 验证邮箱并登录

### 3. 提交包到 Packagist

1. 登录 Packagist 后，点击顶部导航栏的 "Submit" 按钮
2. 在 "Repository URL" 字段中输入您的 GitHub 仓库地址（例如：`https://github.com/your-username/nacos-sdk-php.git`）
3. 点击 "Check" 按钮，Packagist 会验证您的仓库
4. 验证通过后，点击 "Submit" 按钮完成提交

### 4. 配置自动更新

为了确保 Packagist 能够自动更新您的包，您需要配置 GitHub webhook：

1. 登录 GitHub，进入您的仓库
2. 点击 "Settings" → "Webhooks" → "Add webhook"
3. 在 "Payload URL" 字段中输入：`https://packagist.org/api/github`
4. 选择 "Content type" 为 "application/json"
5. 在 "Secret" 字段中输入您在 Packagist 上的 API Token（可在 Packagist 个人设置中找到）
6. 选择 "Just the push event"
7. 勾选 "Active"
8. 点击 "Add webhook" 完成配置

## 验证发布

1. 发布完成后，访问您的 Packagist 包页面（例如：`https://packagist.org/packages/your-username/nacos-sdk-php`）
2. 确认包信息正确显示
3. 尝试通过 Composer 安装您的包，验证发布是否成功：

```bash
composer require your-username/nacos-sdk-php
```

## 版本管理

### 发布新版本

当您对项目进行了重要更新后，需要发布新版本：

1. 更新代码并提交
2. 创建新的版本标签：

```bash
git tag v1.0.1
git push origin v1.0.1
```

3. Packagist 会通过 webhook 自动检测到新版本并更新

### 废弃版本

如果需要废弃某个版本，可以在 Packagist 包页面中进行设置。

## 常见问题

### 1. 包名已被占用

如果您选择的包名已被占用，您需要选择一个不同的包名。建议使用您的用户名作为前缀，例如：`your-username/nacos-sdk-php`。

### 2. 自动更新不工作

- 检查 GitHub webhook 配置是否正确
- 确保 webhook 有正确的权限
- 检查 Packagist API Token 是否正确

### 3. 版本号格式错误

Packagist 要求版本号遵循语义化版本规范（例如：v1.0.0、v1.0.1-beta.1 等）。

## 最佳实践

- **保持 README.md 最新**：确保文档与代码同步
- **使用语义化版本**：遵循 MAJOR.MINOR.PATCH 格式
- **定期更新**：及时修复 bug 和添加新功能
- **响应问题**：及时回复用户的问题和反馈
- **添加测试**：确保代码质量和稳定性

## 联系方式

如果您在发布过程中遇到问题，可以：

1. 查看 [Packagist 文档](https://packagist.org/help)
2. 提交 issue 到您的 GitHub 仓库
3. 联系 Packagist 支持

祝您发布成功！