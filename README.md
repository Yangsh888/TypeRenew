# TypeRenew - 基于 Typecho 的现代化博客程序

![PHP Version](https://img.shields.io/badge/PHP-8.0%20|%208.1%20|%208.2%20|%208.3%20|%208.4%20|%208.5-777BB4?style=flat-square&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql)
![License](https://img.shields.io/badge/License-GPL%20v2-green?style=flat-square)
![Based on](https://img.shields.io/badge/Based%20on-Typecho%201.3.0-orange?style=flat-square)

---

## 项目简介

**TypeRenew** 是基于经典开源博客 CMS 系统 [Typecho](https://typecho.org/) 所**二次开发**的现代化博客程序。在继承 Typecho 轻量、高效内核的基础上，引入了现代化的前端交互体验、后端架构以及 RESTful API 能力，使其能够胜任从个人博客到多端内容分发的各种场景。

### 核心进化

| 特性维度 | 原生 Typecho | TypeRenew（本项目）|
|:---|:---|:---|
| **编辑器** | 传统 Markdown 编辑器 | **Vditor 编辑器**（支持拖拽上传/粘贴上传）|
| **交互体验** | 传统表单提交刷新 | **SPA 级体验**（AJAX 无刷新保存、Toast 通知、全局命令面板等）|
| **视觉设计** | 仅支持浅色模式 | **原生深色模式** |
| **性能架构** | 仅支持基础数据库 | **Redis 对象缓存**、PHP 8.x/MySQL 8.x 深度优化 |
| **扩展能力** | 基础 XML-RPC | **RESTful API v1**（支持 Headless 模式开发）|

---

## ✨ 新增特性详解

### 1. 现代化创作流
- **Vditor 集成**：内置业界领先的 Markdown 编辑器 Vditor，支持分屏预览、即时渲染模式。
- **沉浸式上传**：支持直接将图片拖入编辑器或粘贴剪贴板截图，自动调用系统接口上传并插入 Markdown 语法。
- **字段增强**：自定义字段输入框新增“上传/插入图片”按钮。

### 2. 现代化使用体验
- **全局命令面板**：按下 `Ctrl + K`（或 `Cmd + K`）唤起全局搜索框，快速跳转设置、撰写文章或切换主题。
- **AJAX 无刷新配置**：修改系统设置（常规、评论、阅读等）时，表单通过 AJAX 异步提交，配合右下角 Toast 通知，无需刷新页面。
- **原生深色模式**：基于 CSS 变量实现的深色模式。

### 3. 现代化后端架构
- **RESTful API v1**：新增标准 API 接口，支持获取文章详情、页面、评论列表等，完美支持前后端分离开发（React/Vue/Flutter）。
  - `GET /api/v1/posts/{cid}`
  - `GET /api/v1/pages`
  - `GET /api/v1/comments`
- **Redis 缓存驱动**：重构缓存层，原生支持 Redis 对象缓存。启用后可将 `Options`、`User` 等高频读取数据常驻内存，大幅降低数据库压力。
- **兼容性修复**：全面修复 PHP 8.0+ 的废弃函数调用及 MySQL 8.0+ 的保留字冲突问题。

---

## 🛠️ 安装与配置

### 环境要求
- **PHP**: 8.0 - 8.5 (推荐 8.2+)
- **MySQL**: 5.7 - 8.0+ (推荐 8.0+)
- **Redis**: 7.0+ (可选，推荐启用)

### PHP 扩展要求：
- mbstring
- json
- Reflection
- PDO 或 MySQLi（根据选择的数据库驱动）

---

## 📂 目录结构

```
TypeRenew/
├── index.php              # 前台入口
├── admin/                 # 后台管理
│   ├── css/               # 现代化 CSS
│   ├── js/                # 核心交互逻辑
│   │   ├── vditor/        # 编辑器核心
│   │   ├── modern.js      # 现代化交互脚本
│   │   └── command-palette.js # 命令面板逻辑
│   └── ...
├── var/                   # 核心框架
│   ├── Typecho/
│   │   ├── Router.php     # 路由系统
│   │   ├── Db.php         # 数据库抽象层
│   │   └── Cache/         # 缓存驱动
│   ├── Widget/
│   │   ├── Api/           # RESTful API 实现
│   │   └── Options/       # 配置业务逻辑
└── usr/                   # 用户数据
```

---

## 贡献与反馈

欢迎所有开发者、设计师和用户的反馈、建议与贡献，每一份力量都将帮助 TypeRenew 更好地成长。

如果您在使用过程中遇到任何问题，或有更好的建议：

1.  欢迎提交 [Issues](https://github.com/Yangsh888/TypeRenew/issues) 反馈 BUG。
2.  欢迎提交 Pull Requests 贡献代码。
3.  如果喜欢 TypeRenew，请点亮右上角的 ⭐ **Star** 支持作者！
- QQ交流群：1073739854

---

## 开源协议

本项目基于 [Typecho](https://typecho.org/) 进行二次开发，完整继承并严格遵守 **GPL v2** 开源许可协议。

您可以在 GPL 协议允许的范围内，自由使用、拷贝、修改和分发本程序，无论是用于商业还是非商业目的。

详见 [LICENSE](LICENSE) 文件。

---

## 致谢

- 感谢 [Typecho 团队](https://typecho.org/) 创造了如此优秀的博客系统
- 感谢所有为 TypeRenew 贡献代码、提出建议的开发者和用户

---

<div align="center">
  Made with by Yangsh888
</div>
