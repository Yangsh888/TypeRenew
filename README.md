# TypeRenew - 基于 Typecho 焕新的现代化 CMS 程序

[![PHP Version](https://img.shields.io/static/v1?label=PHP&message=8.0%20-%208.5&color=777BB4&style=flat-square&logo=php)](https://github.com/Yangsh888/TypeRenew)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql)](https://github.com/Yangsh888/TypeRenew)
[![License](https://img.shields.io/badge/License-GPL%20v2-green?style=flat-square)](https://github.com/Yangsh888/TypeRenew/blob/main/LICENSE)
[![Based on](https://img.shields.io/badge/Based%20on-Typecho%201.3.0-orange?style=flat-square)](https://github.com/typecho/typecho)
[![zread](https://img.shields.io/badge/Ask_Zread-_.svg?style=flat-square&color=00b0aa&labelColor=000000&logo=data%3Aimage%2Fsvg%2Bxml%3Bbase64%2CPHN2ZyB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIHZpZXdCb3g9IjAgMCAxNiAxNiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTQuOTYxNTYgMS42MDAxSDIuMjQxNTZDMS44ODgxIDEuNjAwMSAxLjYwMTU2IDEuODg2NjQgMS42MDE1NiAyLjI0MDFWNC45NjAxQzEuNjAxNTYgNS4zMTM1NiAxLjg4ODEgNS42MDAxIDIuMjQxNTYgNS42MDAxSDQuOTYxNTZDNS4zMTUwMiA1LjYwMDEgNS42MDE1NiA1LjMxMzU2IDUuNjAxNTYgNC45NjAxVjIuMjQwMUM1LjYwMTU2IDEuODg2NjQgNS4zMTUwMiAxLjYwMDEgNC45NjE1NiAxLjYwMDFaIiBmaWxsPSIjZmZmIi8%2BCjxwYXRoIGQ9Ik00Ljk2MTU2IDEwLjM5OTlIMi4yNDE1NkMxLjg4ODEgMTAuMzk5OSAxLjYwMTU2IDEwLjY4NjQgMS42MDE1NiAxMS4wMzk5VjEzLjc1OTlDMS42MDE1NiAxNC4xMTM0IDEuODg4MSAxNC4zOTk5IDIuMjQxNTYgMTQuMzk5OUg0Ljk2MTU2QzUuMzE1MDIgMTQuMzk5OSA1LjYwMTU2IDE0LjExMzQgNS42MDE1NiAxMy43NTk5VjExLjAzOTlDNS42MDE1NiAxMC42ODY0IDUuMzE1MDIgMTAuMzk5OSA0Ljk2MTU2IDEwLjM5OTlaIiBmaWxsPSIjZmZmIi8%2BCjxwYXRoIGQ9Ik0xMy43NTg0IDEuNjAwMUgxMS4wMzg0QzEwLjY4NSAxLjYwMDEgMTAuMzk4NCAxLjg4NjY0IDEwLjM5ODQgMi4yNDAxVjQuOTYwMUMxMC4zOTg0IDUuMzEzNTYgMTAuNjg1IDUuNjAwMSAxMS4wMzg0IDUuNjAwMUgxMy43NTg0QzE0LjExMTkgNS42MDAxIDE0LjM5ODQgNS4zMTM1NiAxNC4zOTg0IDQuOTYwMVYyLjI0MDFDMTQuMzk4NCAxLjg4NjY0IDE0LjExMTkgMS42MDAxIDEzLjc1ODQgMS42MDAxWiIgZmlsbD0iI2ZmZiIvPgo8cGF0aCBkPSJNNCAxMkwxMiA0TDQgMTJaIiBmaWxsPSIjZmZmIi8%2BCjxwYXRoIGQ9Ik00IDEyTDEyIDQiIHN0cm9rZT0iI2ZmZiIgc3Ryb2tlLXdpZHRoPSIxLjUiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIvPgo8L3N2Zz4K&logoColor=ffffff)](https://zread.ai/Yangsh888/TypeRenew)

TypeRenew 基于开源博客系统 Typecho，在完整兼容的前提下进行二次开发与焕新。项目全面继承其轻量、简洁、高效的内核基因，针对现代运行环境进行优化，修复兼容问题并原生集成多项实用功能，同时提供安全中心、SEO、优化加速、外链控制与 Vditor 编辑器等官方拓展插件，适合搭建个人博客或轻量内容站点，QQ 交流群：1073739854

## 开发背景

Typecho 作为知名的轻量级博客程序，以代码简洁、运行高效著称，但原版项目维护节奏较慢，存在以下问题：

- 对 PHP 8.0+ 环境的兼容性不足
- 缺少现代化的界面设计，操作体验相对陈旧
- 未内置缓存机制，高并发场景下性能受限
- 相较于其他 CMS，较多 “基础功能” 需依赖第三方插件实现
- 历经多年，三方插件开发、适配混乱，难以 “开箱即用”

TypeRenew 的开发初衷正是解决上述问题，在继承 Typecho 开发精神的前提下，提供开箱即用的现代化体验。

项目基于对 Typecho 原有代码的渐进式改造，原 Typecho 用户可以平滑迁移，开发者也能快速上手二次开发。

## 核心能力

### 现代化运行环境支持

- 最低要求 PHP 8.0，充分利用现代 PHP 特性
- 支持 MySQL 5.7.7+ / MariaDB 10.3+、PostgreSQL 10+、SQLite 3.x 等数据库
- MySQL / MariaDB 安装流程默认使用 `utf8mb4` + `InnoDB`，并根据版本自动选择合适的排序规则
- 内置缓存层，原生支持 Redis 或 APCu，显著降低数据库查询压力
- 内置邮件队列、密码重置、数据库结构升级与在线升级入口，减少对额外插件的依赖
- 内置多语言支持，提供 `usr/langs/typerenew.pot` 翻译模板与官方英文语言包，可在基本设置中切换语言

### 内置插件

TypeRenew 官方专用拓展集合，请前往官方插件仓库获取：https://github.com/Yangsh888/TypeRenew-plugins

当前已开发 `RenewBoost`、`RenewShield`、`RenewSEO`、`RenewGo`、`VditorRenew`、`RenewLocation` 六个官方插件，可按需启用，分别覆盖优化加速、安全防护、SEO、外链安全、后台编辑器增强、CZDB 纯真数据库解析等能力。

### 社区拓展

`TypeRenew Add-ons` 是由社区开发者 @abdulhalim 贡献的第三方拓展，为 TypeRenew 补充多语言本地化与界面适配能力，提供完整的英文（en_US）、波斯语（fa_IR）语言包与全自动从右到左（RTL）布局支持，覆盖前台默认主题、后台管理面板与安装引导页。

【注意】该拓展面向 v1.5.1 编写，在更高版本上使用时，请留意其核心补丁与当前代码的差异。

项目仓库：https://github.com/abdulhalim/TypeRenew-Add-ons

## 运行环境

### 系统要求

| 项目 | 最低要求 | 推荐配置 |
|------|----------|----------|
| PHP | 8.0 | 8.2+ |
| MySQL | 5.7.7 | 8.0+ |
| MariaDB | 10.3 | 10.6+ |
| PostgreSQL | 10 | 12+ |
| SQLite | 3.x | 3.x |
| Redis（可选） | 5.0 | 6.0+ |

### PHP 扩展要求

必需扩展：

- mbstring
- openssl

数据库扩展（至少安装一个）：

- mysqli 或 pdo_mysql（MySQL）
- pgsql 或 pdo_pgsql（PostgreSQL）
- sqlite3 或 pdo_sqlite（SQLite）

可选扩展：

- redis（缓存加速）
- apcu（缓存加速）

### 目录权限

安装前请确保以下目录具有写入权限：

- `/usr/uploads/` - 上传文件存储目录
- `/usr/backups/` - 后台备份文件存储目录
- `/var/Upgrade/` - 在线升级包与临时状态目录
- `/` - 根目录（安装时需要写入 config.inc.php）

## 安装部署

### 获取代码

从 Release 中下载压缩包，解压到 Web 服务器根目录。

### 执行安装程序

1. 在浏览器中访问 `http://your-domain.com/`
2. 第一步：系统自动检测 PHP 版本和扩展是否满足要求，阅读并同意许可协议
3. 第二步：填写数据库连接信息，选择数据库类型（MySQL/PostgreSQL/SQLite），设置表前缀
4. 第三步：创建管理员账号，设置用户名、邮箱和密码
5. 安装完成后自动跳转到后台登录页面

安装程序会在根目录生成 `config.inc.php` 配置文件，包含数据库连接信息和系统初始化代码。

### 从原版 Typecho 迁移

1. 在 Typecho 后台 “备份” 页备份原站点数据，系统会自动下载 .dat 格式备份文件
2. 手动备份原站点的 `usr/` 目录
3. 从 Release 中下载压缩包，解压到 Web 服务器根目录
4. 将备份的原站点 `usr/` 目录复制到新站
5. 执行 TypeRenew 全新安装程序
6. 登录管理后台，在 “备份” 页上传 .dat 格式备份文件，并按页面指引完成恢复流程
7. 重新启用插件和主题

## 增强功能

### 启用缓存

1. 后台「设置」-「缓存」
2. 开启缓存状态
3. 选择驱动类型（Redis 或 APCu）
4. 设置缓存前缀和默认过期时间
5. 保存配置

启用后，系统会自动缓存数据库查询结果和页面片段，并在数据变更时自动更新。

### 配置邮件

1. 后台「设置」-「邮件」
2. 选择发送方式（SMTP 或 PHP mail）
3. 填写 SMTP 服务器地址、端口、账号密码
4. 设置发件人名称和地址
5. 点击「发送测试邮件」验证配置

配置完成后，评论通知、密码重置等邮件将自动发送。

邮件设置页还提供队列概览、立即投递、清理已发送、重试失败与测试发信等功能，便于在生产环境中排查和维护发信状态。

### 数据备份与恢复

1. 后台「控制台」-「备份」
2. 可导出核心内容数据为 `.dat` 备份文件
3. 支持上传备份文件或从服务器已有备份进行恢复
4. 恢复完成后，页面会给出阻断项、预警项与处理摘要，便于核对恢复结果

### 升级程序

1. 后台「控制台」-「升级」
2. 支持数据库结构升级、关键结构诊断与 MySQL / MariaDB 升级风险检查
3. 若已放置升级包，可在该页面执行在线升级流程

### 多语言（i18n）

TypeRenew 使用 gettext 语言包实现界面多语言，简体中文为内置语言，无需额外文件。语言相关文件位于 `usr/langs/` 目录：

- `typerenew.pot`：翻译模板，收录核心程序、默认主题与邮件模板中的全部可翻译文本
- `en_US.po` / `en_US.mo`：官方英文语言包，`.po` 为可编辑的译文源文件，`.mo` 为程序实际读取的编译文件

切换为英文界面：

1. 确认站点的 `usr/langs/` 目录中存在 `en_US.mo`，如缺失，可从本仓库相同路径下载后放入
2. 后台「设置」-「基本」，在「语言」中选择「English」
3. 保存设置

「语言」选项仅在 `usr/langs/` 中至少存在一个 `.mo` 语言包时显示。全新安装时，若该目录中已有语言包，安装向导第一页底部会出现语言选择框，可直接以英文完成安装。

如需制作其他语言，可使用 Poedit 等工具基于 `typerenew.pot` 创建译文并以语言代码命名（如 `ja_JP.po`），编译为同名 `.mo` 文件后放入 `usr/langs/` 即可；模板中 `lang` 条目的译文即该语言在选择框中显示的名称。官方插件与单独发布的主题不在此语言包范围内。

## 常见问题

### 安装时提示「上传目录暂无写入权限」

确保 `/usr/uploads/` 目录存在且具有写入权限：

```bash
mkdir -p usr/uploads
chmod 755 usr/uploads
```

### 安装后访问首页显示空白页

1. 检查 `config.inc.php` 是否正确生成
2. 检查数据库连接信息是否正确
3. 查看 PHP 错误日志定位具体原因

### 缓存启用后页面不更新

缓存会在数据变更时自动失效，如果手动修改了数据库，可通过后台「设置」-「缓存」点击「清空缓存」强制刷新。

### 邮件发送失败

1. 检查 SMTP 服务器地址和端口是否正确
2. 确认 SMTP 服务器支持该端口（部分服务商封锁 25 端口）
3. 尝试更换加密方式（SSL/TLS）
4. 查看后台「设置」-「邮件」页面的错误日志

### 后台无法登录

1. 清除浏览器 Cookie 后重试
2. 检查 `config.inc.php` 中的站点 URL 配置
3. 确认数据库中 `typerenew_users` 表存在且管理员账号正常

### 提示「登录尝试过于频繁」

连续多次密码错误会触发登录限流。可等待提示中的锁定时间自行解除，或由其他管理员在后台「设置」-「基本」页点击「解除全部锁定」。

### 使用反向代理后访客 IP 都相同

出于防伪造考虑，转发头只在明确配置受信代理后才会被读取。请在 `config.inc.php` 中定义 `__TYPECHO_TRUST_PROXY__`，仅设置 `__TYPECHO_IP_SOURCE__` 不会生效。

## 开源许可协议

本项目基于 GNU General Public License 2.0 协议开源。

核心条款：

- 可以自由使用、修改、分发本软件
- 分发时必须保留原始版权声明和许可证
- 修改后的版本必须以相同协议开源
- 不提供任何担保，作者不承担使用本软件产生的任何责任

完整协议文本见 [LICENSE](LICENSE) 文件或访问 [GNU GPL 2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)。

## 商标与授权说明

TypeRenew 名称、Logo 商标 均为 **东莞市次元幻域网络科技有限公司** 所有，本项目已获合法授权使用，请勿将该商标用于修改后衍生版本的对外分发、自有产品推广或商标抢注，避免误导用户；如需商业场景的商标授权，可联系 support@nekoteco.com 咨询。

本项目的开源许可证仅覆盖代码授权，不包含商标权利。但对于所有遵守本项目开源协议（GPL-2.0）的用户，我们豁免其在合规开源分发场景下的商标使用限制，您可在自行部署自用、分发未修改的官方原版时，正常使用该商标，无需额外申请授权。

同时，本项目为基于 Typecho 的衍生改进版本，我们完全尊重上游项目的版权与商标权益。在此重申，本项目并非 Typecho 官方版本，原项目所有权利均归 Typecho 开发团队所有。

## 致谢

TypeRenew 的开发建立在以下开源项目的基础上：

- [Typecho](https://github.com/typecho/typecho) - 本项目的初始来源
- [Vditor](https://github.com/Vanessa219/vditor) - 广受好评的 Markdown 编辑器

感谢 Typecho 开发团队和其他所有贡献者所创造的优秀产品，也感谢开源社区的支持。

***

# TypeRenew - A Modern CMS Renewed from Typecho

[![PHP Version](https://img.shields.io/static/v1?label=PHP&message=8.0%20-%208.5&color=777BB4&style=flat-square&logo=php)](https://github.com/Yangsh888/TypeRenew)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql)](https://github.com/Yangsh888/TypeRenew)
[![License](https://img.shields.io/badge/License-GPL%20v2-green?style=flat-square)](https://github.com/Yangsh888/TypeRenew/blob/main/LICENSE)
[![Based on](https://img.shields.io/badge/Based%20on-Typecho%201.3.0-orange?style=flat-square)](https://github.com/typecho/typecho)
[![zread](https://img.shields.io/badge/Ask_Zread-_.svg?style=flat-square&color=00b0aa&labelColor=000000&logo=data%3Aimage%2Fsvg%2Bxml%3Bbase64%2CPHN2ZyB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIHZpZXdCb3g9IjAgMCAxNiAxNiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTQuOTYxNTYgMS42MDAxSDIuMjQxNTZDMS44ODgxIDEuNjAwMSAxLjYwMTU2IDEuODg2NjQgMS42MDE1NiAyLjI0MDFWNC45NjAxQzEuNjAxNTYgNS4zMTM1NiAxLjg4ODEgNS42MDAxIDIuMjQxNTYgNS42MDAxSDQuOTYxNTZDNS4zMTUwMiA1LjYwMDEgNS42MDE1NiA1LjMxMzU2IDUuNjAxNTYgNC45NjAxVjIuMjQwMUM1LjYwMTU2IDEuODg2NjQgNS4zMTUwMiAxLjYwMDEgNC45NjE1NiAxLjYwMDFaIiBmaWxsPSIjZmZmIi8%2BCjxwYXRoIGQ9Ik00Ljk2MTU2IDEwLjM5OTlIMi4yNDE1NkMxLjg4ODEgMTAuMzk5OSAxLjYwMTU2IDEwLjY4NjQgMS42MDE1NiAxMS4wMzk5VjEzLjc1OTlDMS42MDE1NiAxNC4xMTM0IDEuODg4MSAxNC4zOTk5IDIuMjQxNTYgMTQuMzk5OUg0Ljk2MTU2QzUuMzE1MDIgMTQuMzk5OSA1LjYwMTU2IDE0LjExMzQgNS42MDE1NiAxMy43NTk5VjExLjAzOTlDNS42MDE1NiAxMC42ODY0IDUuMzE1MDIgMTAuMzk5OSA0Ljk2MTU2IDEwLjM5OTlaIiBmaWxsPSIjZmZmIi8%2BCjxwYXRoIGQ9Ik0xMy43NTg0IDEuNjAwMUgxMS4wMzg0QzEwLjY4NSAxLjYwMDEgMTAuMzk4NCAxLjg4NjY0IDEwLjM5ODQgMi4yNDAxVjQuOTYwMUMxMC4zOTg0IDUuMzEzNTYgMTAuNjg1IDUuNjAwMSAxMS4wMzg0IDUuNjAwMUgxMy43NTg0QzE0LjExMTkgNS42MDAxIDE0LjM5ODQgNS4zMTM1NiAxNC4zOTg0IDQuOTYwMVYyLjI0MDFDMTQuMzk4NCAxLjg4NjY0IDE0LjExMTkgMS42MDAxIDEzLjc1ODQgMS42MDAxWiIgZmlsbD0iI2ZmZiIvPgo8cGF0aCBkPSJNNCAxMkwxMiA0TDQgMTJaIiBmaWxsPSIjZmZmIi8%2BCjxwYXRoIGQ9Ik00IDEyTDEyIDQiIHN0cm9rZT0iI2ZmZiIgc3Ryb2tlLXdpZHRoPSIxLjUiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIvPgo8L3N2Zz4K&logoColor=ffffff)](https://zread.ai/Yangsh888/TypeRenew)

TypeRenew is built on the open-source blogging system Typecho and has been further developed and renewed while remaining fully compatible with it. It fully inherits Typecho's lightweight, simple and efficient core, is optimized for modern runtime environments, fixes compatibility issues and natively integrates a number of practical features. It also offers official extension plugins such as a security center, SEO, performance optimization, outbound link control and the Vditor editor, making it well suited to personal blogs and lightweight content sites. QQ Group: 1073739854

## Background

Typecho is a well-known lightweight blogging platform, famous for its clean code and efficient performance. However, the original project is maintained at a slow pace and has the following problems:

- Insufficient compatibility with PHP 8.0+
- No modern interface design, and a rather dated user experience
- No built-in caching, which limits performance under high concurrency
- Compared with other CMSs, many "basic features" rely on third-party plugins
- After many years, third-party plugin development and compatibility have become messy, making it hard to use "out of the box"

TypeRenew was created precisely to solve these problems and, while carrying on the development spirit of Typecho, to deliver a modern, out-of-the-box experience.

The project is built by incrementally reworking the original Typecho code, so existing Typecho users can migrate smoothly and developers can quickly get started with secondary development.

## Core Features

### Modern Runtime Support

- Requires PHP 8.0 or later and makes full use of modern PHP features
- Supports MySQL 5.7.7+ / MariaDB 10.3+, PostgreSQL 10+, SQLite 3.x and other databases
- MySQL / MariaDB installations use `utf8mb4` + `InnoDB` by default, with a suitable collation chosen automatically based on the server version
- Built-in caching layer with native Redis or APCu support, significantly reducing database query load
- Built-in email queue, password reset, database schema upgrade and online upgrade entry, reducing reliance on extra plugins
- Built-in multilingual support, providing the `usr/langs/typerenew.pot` translation template and an official English language pack; the language can be switched in the general settings

### Built-in Plugins

The official extension collection dedicated to TypeRenew is available from the official plugin repository: https://github.com/Yangsh888/TypeRenew-plugins

Six official plugins have been developed so far: `RenewBoost`, `RenewShield`, `RenewSEO`, `RenewGo`, `VditorRenew` and `RenewLocation`. They can be enabled as needed and respectively cover performance optimization, security protection, SEO, outbound link security, admin editor enhancements and CZDB (Chunzhen IP database) parsing.

### Community Extensions

`TypeRenew Add-ons` is a third-party extension contributed by community developer @abdulhalim. It adds multilingual localization and interface adaptation to TypeRenew, providing complete English (en_US) and Persian (fa_IR) language packs and fully automatic right-to-left (RTL) layout support, covering the default front-end theme, the admin panel and the installer.

[Note] This extension was written for v1.5.1. When using it on later versions, please watch for differences between its core patches and the current code.

Repository: https://github.com/abdulhalim/TypeRenew-Add-ons

## Requirements

### System Requirements

| Item | Minimum | Recommended |
|------|---------|-------------|
| PHP | 8.0 | 8.2+ |
| MySQL | 5.7.7 | 8.0+ |
| MariaDB | 10.3 | 10.6+ |
| PostgreSQL | 10 | 12+ |
| SQLite | 3.x | 3.x |
| Redis (optional) | 5.0 | 6.0+ |

### PHP Extensions

Required extensions:

- mbstring
- openssl

Database extensions (install at least one):

- mysqli or pdo_mysql (MySQL)
- pgsql or pdo_pgsql (PostgreSQL)
- sqlite3 or pdo_sqlite (SQLite)

Optional extensions:

- redis (cache acceleration)
- apcu (cache acceleration)

### Directory Permissions

Before installing, make sure the following directories are writable:

- `/usr/uploads/` - storage for uploaded files
- `/usr/backups/` - storage for backup files created in the admin panel
- `/var/Upgrade/` - online upgrade packages and temporary state
- `/` - root directory (config.inc.php is written here during installation)

## Installation

### Get the Code

Download the archive from Releases and extract it to the root directory of your web server.

### Run the Installer

1. Visit `http://your-domain.com/` in your browser
2. Step 1: the system automatically checks whether the PHP version and extensions meet the requirements; read and accept the license agreement
3. Step 2: enter the database connection details, choose the database type (MySQL/PostgreSQL/SQLite) and set the table prefix
4. Step 3: create the administrator account by setting a username, email and password
5. When the installation is complete, you are automatically redirected to the admin login page

The installer generates a `config.inc.php` configuration file in the root directory, containing the database connection details and system initialization code.

### Migrate from Typecho

1. Back up the original site's data on the "Backup" page of the Typecho admin panel; a backup file in .dat format is downloaded automatically
2. Manually back up the `usr/` directory of the original site
3. Download the archive from Releases and extract it to the root directory of your web server
4. Copy the backed-up `usr/` directory of the original site to the new site
5. Run a fresh TypeRenew installation
6. Log in to the admin panel, upload the .dat backup file on the "Backup" page and follow the on-page instructions to complete the restore
7. Re-enable your plugins and theme

## Enhanced Features

### Enable Caching

1. Admin panel: "Settings" - "Cache"
2. Turn on the cache status
3. Choose the driver type (Redis or APCu)
4. Set the cache prefix and default expiration time
5. Save the settings

Once enabled, the system automatically caches database query results and page fragments, and updates them automatically when data changes.

### Configure Email

1. Admin panel: "Settings" - "Email"
2. Choose the sending method (SMTP or PHP mail)
3. Enter the SMTP server address, port, account and password
4. Set the sender name and address
5. Click "Send test email" to verify the configuration

Once configured, emails such as comment notifications and password resets are sent automatically.

The email settings page also offers a queue overview, immediate delivery, clearing sent records, retrying failed jobs and test sending, making it easier to troubleshoot and maintain email delivery in production.

### Backup and Restore

1. Admin panel: "Dashboard" - "Backup"
2. Core content data can be exported as a `.dat` backup file
3. You can restore from an uploaded backup file or from an existing backup on the server
4. After the restore, the page shows blocking issues, warnings and a processing summary to help you verify the result

### Upgrade

1. Admin panel: "Dashboard" - "Upgrade"
2. Supports database schema upgrades, critical schema diagnostics and MySQL / MariaDB upgrade risk checks
3. If an upgrade package is in place, you can run the online upgrade from this page

### Multilingual Support (i18n)

TypeRenew uses gettext language packs for its multilingual interface. Simplified Chinese is built in and needs no extra files. Language files are located in the `usr/langs/` directory:

- `typerenew.pot`: the translation template, containing all translatable text in the core program, the default theme and the email templates
- `en_US.po` / `en_US.mo`: the official English language pack; `.po` is the editable translation source and `.mo` is the compiled file the program actually reads

To switch the interface to English:

1. Make sure `en_US.mo` exists in the `usr/langs/` directory of your site. If it is missing, download it from the same path in this repository and put it there
2. Admin panel: "Settings" - "General", then choose "English" under "Language"
3. Save the settings

The "Language" option only appears when at least one `.mo` language pack exists in `usr/langs/`. For a fresh installation, if a language pack is already in that directory, a language selector appears at the bottom of the first installer page, so you can complete the installation in English.

To create another language, use a tool such as Poedit to translate `typerenew.pot` and name the file with the language code (e.g. `ja_JP.po`), then compile it into a `.mo` file with the same name and put it in `usr/langs/`. The translation of the `lang` entry in the template is the name shown for the language in the selector. Official plugins and separately released themes are not covered by this language pack.

## FAQ

### "The upload directory is not writable" during installation

Make sure the `/usr/uploads/` directory exists and is writable:

```bash
mkdir -p usr/uploads
chmod 755 usr/uploads
```

### Blank home page after installation

1. Check that `config.inc.php` was generated correctly
2. Check that the database connection details are correct
3. Check the PHP error log to find the cause

### Pages do not update after enabling the cache

The cache is invalidated automatically when data changes. If you modified the database manually, go to "Settings" - "Cache" in the admin panel and click "Clear cache" to force a refresh.

### Email sending fails

1. Check that the SMTP server address and port are correct
2. Make sure the SMTP server supports that port (some providers block port 25)
3. Try a different encryption method (SSL/TLS)
4. Check the error log on the "Settings" - "Email" page in the admin panel

### Cannot log in to the admin panel

1. Clear your browser cookies and try again
2. Check the site URL setting in `config.inc.php`
3. Make sure the `typerenew_users` table exists in the database and the administrator account is intact

### "Too many login attempts" message

Repeated wrong passwords trigger login rate limiting. Wait until the lockout time shown in the message has passed, or ask another administrator to click "Release all lockouts" on the "Settings" - "General" page in the admin panel.

### All visitors have the same IP behind a reverse proxy

To prevent spoofing, forwarded headers are only read after a trusted proxy is explicitly configured. Define `__TYPECHO_TRUST_PROXY__` in `config.inc.php`; setting only `__TYPECHO_IP_SOURCE__` has no effect.

## License

This project is open-sourced under the GNU General Public License 2.0.

Key terms:

- You may freely use, modify and distribute this software
- The original copyright notice and license must be retained when distributing
- Modified versions must be open-sourced under the same license
- No warranty is provided, and the authors accept no liability arising from the use of this software

For the full license text, see the [LICENSE](LICENSE) file or visit [GNU GPL 2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

## Trademark and Authorization

The TypeRenew name and logo trademarks are owned by **东莞市次元幻域网络科技有限公司**, and this project has been legally authorized to use them. Please do not use these trademarks for the external distribution of modified derivative versions, for promoting your own products, or for trademark squatting, so as not to mislead users. For trademark authorization in commercial scenarios, please contact support@nekoteco.com.

The open-source license of this project covers only the code and does not include trademark rights. However, for all users who comply with this project's open-source license (GPL-2.0), we waive the trademark usage restrictions in compliant open-source distribution scenarios: you may use the trademark normally, without applying for additional authorization, when deploying it for your own use or distributing the unmodified official version.

In addition, this project is a derivative, improved version based on Typecho, and we fully respect the copyright and trademark rights of the upstream project. We reiterate that this project is not an official version of Typecho, and all rights to the original project belong to the Typecho development team.

## Acknowledgements

The development of TypeRenew builds on the following open-source projects:

- [Typecho](https://github.com/typecho/typecho) - the original source of this project
- [Vditor](https://github.com/Vanessa219/vditor) - a widely acclaimed Markdown editor

Thanks to the Typecho development team and all other contributors for creating an excellent product, and to the open-source community for its support.
