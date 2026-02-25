# TypeRenew - 基于 Typecho 的现代化轻量博客程序

  ![PHP Version](https://img.shields.io/badge/PHP-8.0%20|%208.1%20|%208.2%20|%208.3%20|%208.4%20|%208.5-777BB4?style=flat-square&logo=php)
  ![License](https://img.shields.io/badge/License-GPL%20v2-green?style=flat-square)
  ![Based on](https://img.shields.io/badge/Based%20on-Typecho%201.3.0-orange?style=flat-square)

---

## 项目简介

**TypeRenew** 是基于经典开源博客 CMS 系统 [Typecho](https://typecho.org/) 所**二次开发**的现代化轻量博客程序。项目完整继承 Typecho 一贯的干净、克制、轻量、高效的内核基因，针对主流的现代化 Web 运行环境做额外兼容适配，修复长期遗留的痛点问题，原生集成更多高频实用特性。

### 为什么选择 TypeRenew？

| 原生 Typecho  | 二开 TypeRenew |
|:---|:---|
| PHP 8.x 兼容性问题频发 | ✅ 更好兼容 PHP 8.0 ~ 8.5 |
| MySQL 8.x 适配不完善 | ✅ 更好适配 MySQL 8.x 新特性 |
| 长期遗留 Bug 未修复 | ✅ 修复更多历史遗留问题 |
| 高频功能依赖第三方插件 | ✅ Redis 等特性原生集成 |
| 前端设计明显落后 | ✅ 现代化主题模板 |

---

## 功能特性

### 运行环境现代化适配
- **PHP 8.x 兼容** - 修复 PHP 8 系列语法兼容问题，支持 PHP 8.0/8.1/8.2/8.3/8.4/8.5
- **MySQL 8.x 适配** - 适配新版数据库语法规范，支持 utf8mb4、InnoDB 等现代特性
- **多数据库支持** - 原生支持 MySQL、PostgreSQL、SQLite

### 原生系统问题修复
- 修复更多 Typecho 原生系统长期遗留的功能 Bug
- 优化代码健壮性，解决边界场景异常问题
- 修复安全隐患，提升系统安全性

### 原生特性集成
- **Redis 原生支持** - 系统缓存、会话等核心场景的 Redis 适配
- 遵循克制、轻量原则，不偏离轻量级核心定位

### 前端模板体系
- 重新设计、优化的多套模板主题
- 响应式布局设计
- 完全兼容 Typecho 原生模板开发规范

---

## 技术架构

### 目录结构

```
TypeRenew/
├── index.php              # 前台入口文件
├── install.php            # 安装程序入口
├── config.inc.php         # 配置文件（安装后生成）
├── admin/                 # 后台管理模块
│   ├── css/js/img/        # 静态资源
│   ├── common.php         # 后台公共引导文件
│   ├── menu.php           # 后台菜单
│   └── *.php              # 各功能模块页面
├── install/               # 安装脚本和 SQL
├── usr/                   # 用户数据目录
│   ├── plugins/           # 插件目录
│   └── themes/            # 主题目录
│   └── uploads/           # 上传文件目录
└── var/                   # 核心框架目录
    ├── Typecho/           # 核心类库
    │   ├── Common.php     # 公共工具类
    │   ├── Db.php         # 数据库抽象层
    │   ├── Plugin.php     # 插件系统
    │   ├── Router.php     # 路由系统
    │   ├── Widget.php     # Widget 基类
    │   └── Db/            # 数据库适配器
    ├── Widget/            # 业务逻辑组件
    │   ├── Base/          # 基础数据模型
    │   ├── Contents/      # 内容相关组件
    │   ├── Metas/         # 元数据组件
    │   ├── Users/         # 用户组件
    │   └── Options/       # 配置组件
    ├── Utils/             # 工具类
    └── IXR/               # XML-RPC 库
```

### 核心执行流程

```
请求 → index.php → Widget\Init::alloc() → 路由初始化
                                      ↓
                          Typecho\Router::dispatch()
                                      ↓
                          匹配路由 → 加载对应 Widget
                                      ↓
                          Widget::execute() → 业务逻辑
                                      ↓
                          渲染模板 → 输出响应
```

### 数据库表结构

| 表名 | 说明 |
|:---|:---|
| `typecho_contents` | 文章/页面内容 |
| `typecho_comments` | 评论数据 |
| `typecho_metas` | 分类/标签元数据 |
| `typecho_users` | 用户数据 |
| `typecho_options` | 系统配置 |
| `typecho_relationships` | 内容与元数据关联 |
| `typecho_fields` | 自定义字段 |

---

## 环境要求

| 组件 | 最低版本 | 推荐版本 |
|:---|:---|:---|
| PHP | 8.0 | 8.2+ |
| MySQL | 5.7 | 8.0+ |

**PHP 扩展要求：**
- mbstring
- json
- Reflection
- PDO 或 MySQLi（根据选择的数据库驱动）

---

## 贡献指南

欢迎所有开发者、设计师和用户的反馈、建议与贡献，每一份力量都将帮助 TypeRenew 更好地成长。

### 如何贡献

1. Fork 本仓库
2. 创建您的特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交您的更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 创建 Pull Request

### 贡献类型

- Bug 修复
- 新功能开发
- 文档改进
- 主题/界面优化
- 性能优化

---

## 开源协议

本项目基于 [Typecho](https://typecho.org/) 进行二次开发，完整继承并严格遵守 **GPL v2** 开源许可协议。

您可以在 GPL 协议允许的范围内，自由使用、拷贝、修改和分发本程序，无论是用于商业还是非商业目的。

详见 [LICENSE](LICENSE) 文件。

---

## 联系方式

- QQ交流群：1073739854

---

## 致谢

- 感谢 [Typecho 团队](https://typecho.org/) 创造了如此优秀的博客系统
- 感谢所有为 TypeRenew 贡献代码、提出建议的开发者和用户

---

<div align="center">
  Made with by Yangsh888
</div>
