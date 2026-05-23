# 📚 Nacos SDK PHP 文档索引

## 🎯 选择您的文档

### 🚀 新手？快速开始！
📖 **[QUICK_START_WEBMAN.md](./QUICK_START_WEBMAN.md)** - 5 分钟快速上手

### 📘 完整指南
📖 **[WEBMAN_INTEGRATION_GUIDE.md](./WEBMAN_INTEGRATION_GUIDE.md)** - 完整的 Webman 集成指南（推荐）

### 📗 SDK 基础文档
📖 **[README.md](./README.md)** - 官方 README，了解 SDK 基本功能

---

## 📄 文档说明

### 1. 快速开始 (QUICK_START_WEBMAN.md)
**适合**: 想快速体验 SDK 的用户

**内容**:
- ✅ 5 分钟安装配置
- ✅ 基础使用示例
- ✅ 控制器和服务类示例
- ✅ 路由配置
- ✅ 快速测试

**章节**:
1. 🚀 5 分钟快速上手
2. 📦 快速使用
3. 🎯 实际示例
4. ⚡ 路由配置
5. 🧪 测试

---

### 2. 完整集成指南 (WEBMAN_INTEGRATION_GUIDE.md)
**适合**: 生产环境使用，需要完整功能

**内容**:
- ✅ 完整的环境配置
- ✅ 配置管理服务封装
- ✅ 服务发现服务封装
- ✅ Feign 客户端封装
- ✅ 中间件配置
- ✅ 定时任务（心跳）
- ✅ 进程事件（自动注册/注销）
- ✅ 微服务架构完整示例
- ✅ 多环境配置
- ✅ 认证配置（用户名密码/AK/SK）
- ✅ 最佳实践
- ✅ 故障排查
- ✅ API 参考

**章节**:
1. 环境要求
2. 安装配置
3. 基础配置
4. 配置管理
5. 服务发现
6. 服务调用
7. Feign 客户端
8. 认证配置
9. 中间件示例
10. 定时任务
11. 进程事件
12. 完整示例：微服务架构
13. 最佳实践
14. 故障排查
15. API 参考

---

### 3. SDK 基础文档 (README.md)
**适合**: 了解 SDK 原始功能，不需要 webman 集成

**内容**:
- ✅ SDK 功能介绍
- ✅ 安装方法
- ✅ 基础使用示例
- ✅ 配置管理 API
- ✅ 服务发现 API
- ✅ 服务调用 API
- ✅ Feign 风格客户端
- ✅ gRPC 功能
- ✅ 系统要求
- ✅ 测试方法

---

## 🔧 其他文档

### 认证修复说明 (AUTH_FIX.md)
修复带认证 Nacos 服务器的说明文档

**包含**:
- 问题描述
- 修复内容
- 使用方法
- 测试验证

### 测试报告 (TEST_REPORT.md)
完整的接口测试报告

**包含**:
- 测试环境信息
- 26 项测试结果
- 性能测试数据
- 已知限制

---

## 🎯 使用场景推荐

### 场景 1：快速原型开发
```
1. 阅读 QUICK_START_WEBMAN.md
2. 复制示例代码
3. 运行测试
⏱️ 预计时间: 10 分钟
```

### 场景 2：生产环境项目
```
1. 阅读 WEBMAN_INTEGRATION_GUIDE.md 完整版
2. 配置环境变量和配置文件
3. 使用服务封装类
4. 配置中间件和定时任务
5. 部署测试
⏱️ 预计时间: 1-2 小时
```

### 场景 3：微服务架构
```
1. 阅读 WEBMAN_INTEGRATION_GUIDE.md 的"微服务架构完整示例"章节
2. 配置多服务注册
3. 使用 Feign 客户端进行服务间调用
4. 配置服务认证中间件
5. 部署测试
⏱️ 预计时间: 2-3 小时
```

---

## 📋 快速代码片段

### 最简使用

```php
// 1. 创建客户端
$nacos = new Nacos('http://localhost:8848', 'public', '', '', 9848, null, 'nacos', 'nacos');

// 2. 配置管理
$content = $nacos->config()->getConfig('app.json');

// 3. 服务发现
$instance = $nacos->discovery()->selectOneHealthyInstance('user-service');

// 4. 服务调用
$userClient = $nacos->feign('user-service');
$result = $userClient->get('/api/users/1');
```

### Webman 集成

```php
// 在控制器中使用
$nacos = NacosBootstrap::getClient();
$config = $nacos->config()->getConfig('database.json');
```

---

## 🔍 查找特定功能

| 功能需求 | 推荐文档 | 章节 |
|---------|---------|------|
| 如何安装 SDK | QUICK_START_WEBMAN.md | 1. 安装 SDK |
| 如何获取配置 | WEBMAN_INTEGRATION_GUIDE.md | 4. 配置管理 |
| 如何注册服务 | WEBMAN_INTEGRATION_GUIDE.md | 5. 服务发现 |
| 如何调用远程服务 | WEBMAN_INTEGRATION_GUIDE.md | 6. 服务调用 |
| 如何使用 Feign | WEBMAN_INTEGRATION_GUIDE.md | 7. Feign 客户端 |
| 如何配置认证 | WEBMAN_INTEGRATION_GUIDE.md | 8. 认证配置 |
| 如何监听配置变更 | WEBMAN_INTEGRATION_GUIDE.md | 4. 配置管理 |
| 如何发送心跳 | WEBMAN_INTEGRATION_GUIDE.md | 定时任务 |
| 如何自动注册服务 | WEBMAN_INTEGRATION_GUIDE.md | 进程事件 |
| 如何配置多环境 | WEBMAN_INTEGRATION_GUIDE.md | 认证配置 |
| 常见问题 | WEBMAN_INTEGRATION_GUIDE.md | 故障排查 |

---

## 📞 获取帮助

1. **阅读文档**: 从 QUICK_START_WEBMAN.md 开始
2. **查看示例**: 运行 `php test-all-interfaces.php` 查看测试示例
3. **检查配置**: 确保 `.env` 中的 Nacos 配置正确
4. **查看日志**: 检查 webman 日志中的 Nacos 相关错误
5. **提交 Issue**: 在 GitHub 提交问题

---

## ✅ 下一步

1. ⭐ 阅读 **[QUICK_START_WEBMAN.md](./QUICK_START_WEBMAN.md)** 快速体验
2. 📘 阅读 **[WEBMAN_INTEGRATION_GUIDE.md](./WEBMAN_INTEGRATION_GUIDE.md)** 深入学习
3. 🧪 运行 `php test-all-interfaces.php` 测试 SDK
4. 🚀 开始您的 Nacos 集成之旅！

---

**文档版本**: 1.0.0  
**更新时间**: 2026-05-23  
**SDK 版本**: ssh/nacos-sdk-php
