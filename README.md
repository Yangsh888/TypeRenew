# TypeRenew - 焕新 CMS 程序

[![PHP Version](https://img.shields.io/static/v1?label=PHP&message=8.0%20-%208.5&color=777BB4&style=flat-square&logo=php)](https://github.com/Yangsh888/TypeRenew)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql)](https://github.com/Yangsh888/TypeRenew)
[![License](https://img.shields.io/badge/License-GPL%20v2-green?style=flat-square)](https://github.com/Yangsh888/TypeRenew/blob/main/LICENSE)
[![Based on](https://img.shields.io/badge/Based%20on-Typecho%201.3.0-orange?style=flat-square)](https://github.com/typecho/typecho)

TypeRenew 是基于开源博客系统 Typecho 所开发的现代化 CMS 程序，完整继承了 Typecho 轻量、简洁、高效的内核基因，同时针对现代运行环境进行全面适配，修复遗留的兼容性问题，并原生集成了缓存、邮件等实用功能，适合搭建个人博客或轻量内容站点，项目 QQ 交流群：1073739854

## 开发背景

Typecho 作为知名的轻量级博客程序，以代码简洁、运行高效著称，但原版项目维护节奏较慢，存在以下问题：

- 对 PHP 8.0+ 环境的兼容性不足
- 缺少现代化的界面设计，操作体验相对陈旧
- 未内置缓存机制，高并发场景下性能受限
- 相较于其他 CMS，较多 “基础功能” 需要额外依赖第三方插件实现
- 历经多年，三方插件开发、适配混乱，难以 “开箱即用”

因此，TypeRenew 的开发初衷是解决上述问题，并在继承 Typecho 开发精神的前提下，提供开箱即用的现代化体验。

同时，项目的实现基于对 Typecho 原有代码的渐进式改造，这让原 Typecho 用户可以平滑迁移至 TypeRenew，开发者也能快速上手进行二次开发。

## 核心能力

### 现代化运行环境支持

- 最低要求 PHP 8.0，充分利用现代 PHP 特性
- 支持 MySQL 8.0+、PostgreSQL 12+、SQLite 3.x 三种数据库
- 内置缓存层，原生集成 Redis 或 APCu 缓存，显著降低数据库查询压力

### 内置插件

截止 2026/3/15，项目自带三个 TypeRenew 专用拓展，安装后即可启用：

| 插件 | 功能 |
|------|------|
| RenewAvatar | 头像源替换，支持多种 Gravatar 镜像源，可全局生效或仅限评论区域 |
| RenewGo | 外链安全拓展，支持安全提示页跳转或 302 跳转，可配置白名单和日志记录 |
| VditorRenew | 引入 Vditor 编辑器，支持所见即所得、即时渲染、分屏预览三种模式，兼容旧文章 |
| 敬请期待...... | 持续更新中...... |

## 运行环境

### 系统要求

| 项目 | 最低要求 | 推荐配置 |
|------|----------|----------|
| PHP | 8.0 | 8.2+ |
| MySQL | 5.7 | 8.0+ |
| PostgreSQL | 10 | 12+ |
| SQLite | 3.x | 3.x |
| Redis（可选） | 5.0 | 6.0+ |

### PHP 扩展要求

必需扩展：

- mbstring
- json
- Reflection

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
- `/` - 根目录（安装时需要写入 config.inc.php）

## 安装部署

### 获取代码

从 Release 中下载压缩包，解压到 Web 服务器根目录。

### 执行安装程序

1. 在浏览器中访问 `http://your-domain.com/`
2. 第一步：系统自动检测环境，确认 PHP 版本和扩展满足要求，阅读并同意许可协议
3. 第二步：填写数据库连接信息，选择数据库类型（MySQL/PostgreSQL/SQLite），设置表前缀
4. 第三步：创建管理员账号，设置用户名、邮箱和密码
5. 安装完成后自动跳转到后台登录页面

安装程序会在根目录生成 `config.inc.php` 配置文件，包含数据库连接信息和系统初始化代码。

### 从原版 Typecho 迁移

1. 通过 Typecho 后台 “备份” 页，对原站点数据进行备份，系统会自动下载 .dat 格式备份文件
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

启用后，系统会自动缓存数据库查询结果和页面片段，并在数据变更时自动更新缓存。

### 配置邮件

1. 后台「设置」-「邮件」
2. 选择发送方式（SMTP 或 PHP mail）
3. 填写 SMTP 服务器地址、端口、账号密码
4. 设置发件人名称和地址
5. 点击「发送测试邮件」验证配置

配置完成后，评论通知、密码重置等邮件将自动发送。

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

## 社区贡献

欢迎提交 Issue 和 Pull Request，或通过 Discussions 参与对项目开发的讨论。

### Issue 提交规范

- 使用中文或英文描述问题
- 提供复现步骤、期望结果、实际结果
- 附上运行环境信息（PHP 版本、数据库类型和版本）
- 已有 Issue 中检索避免重复提交

### Pull Request 流程

1. Fork 本仓库
2. 创建功能分支：`git checkout -b feature/your-feature`
3. 提交代码：`git commit -m 'Add some feature'`
4. 推送分支：`git push origin feature/your-feature`
5. 提交 Pull Request

### 代码规范

- 遵循 PSR-12 编码规范
- 新增代码必须包含类型声明
- 复杂逻辑添加注释说明
- 单个 PR 控制在合理范围，避免大规模重构

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

感谢 Typecho 开发团队和其他所有贡献者所创造的优秀产品，同时还要感谢开源社区的支持。
