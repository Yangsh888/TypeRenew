# 安全政策

## 支持的版本

TypeRenew 仅对 **最新的正式发布版本** 提供安全更新与维护，我们强烈建议所有用户始终将程序升级至最新版本，以获得重要的安全修复和功能改进。

## 报告安全漏洞

我们高度重视 TypeRenew 的安全性，并感谢安全研究人员和用户社区为帮助我们提升安全性所做的努力。

如果您发现了一个安全漏洞，请通过以下 **私有渠道** 进行报告，切勿公开披露：

1.  **GitHub Security Advisories**：通过本项目仓库的 “Security” 选项卡，点击 “Report a vulnerability” 来创建私密的安全报告。
2.  **QQ 交流群**：添加项目 QQ 交流群（1073739854），联系群内的管理人员进行报告。

请勿在公开的 Issues 讨论区、其他论坛或社交媒体上报告安全漏洞，以免在修复完成前造成风险扩散。

## 报告内容要求

为使漏洞能被快速验证和处理，您的报告应尽可能包含以下信息：

*   **清晰的问题描述**：漏洞的性质和可能造成的影响。
*   **详细的复现步骤**：从环境配置到触发漏洞的完整操作流程。
*   **影响范围**：该漏洞影响 TypeRenew 的哪些组件或功能。
*   **版本信息**：您发现漏洞时所使用的 TypeRenew 版本、PHP 版本及数据库类型。
*   **建议修复方案（如有）**：您对如何修复此漏洞的建议或思路。

## 漏洞处理流程

1.  **确认与响应**：我们会在收到报告后的 **7 个工作日** 内进行确认，并与您取得联系。
2.  **评估与修复**：确认漏洞后，我们将评估其严重性，并着手开发修复补丁；修复时间取决于漏洞的复杂程度，我们会尽力快速处理。
3.  **发布与披露**：修复完成后，我们将：
    *   在新版本中发布安全更新。
    *   在项目的 Release Notes 或安全公告中，以适当的方式对修复的漏洞进行说明和致谢（在征得报告者同意的前提下）。

## 禁止事项

*   禁止在漏洞被修复前，在任何公共平台公开披露漏洞细节或进行概念验证（PoC）攻击演示。
*   禁止在未获得明确授权的情况下，对非您自己管理的 TypeRenew 实例进行任何安全测试或攻击。
*   禁止利用漏洞进行任何违反法律法规的行为，包括但不限于窃取、篡改、破坏数据或影响服务可用性。

## 安全建议

为确保您的 TypeRenew 站点安全，我们建议您遵循以下最佳实践：

*   **及时更新**：始终关注并尽快安装官方发布的最新版本。
*   **最小权限原则**：为数据库账户和服务器文件系统目录配置仅满足运行所需的最小权限。
*   **强化凭据**：为管理员账户使用强密码，并定期更换。
*   **定期检查**：定期检查并更新服务器操作系统、PHP 及所用插件的安全补丁。
*   **安全配置**：合理配置缓存、邮件等敏感功能，避免使用弱密码或默认配置。

***

# Security Policy

## Supported Versions

TypeRenew provides security updates and maintenance only for the **latest official release version**. We strongly advise all users to always upgrade their installation to the latest version to receive critical security fixes and improvements.

## Reporting a Vulnerability

We take the security of TypeRenew seriously and appreciate the efforts of security researchers and the user community in helping us improve it.

If you discover a security vulnerability, please report it through the following **private channels**. **DO NOT** disclose it publicly:

1.  **GitHub Security Advisories**: Use the "Security" tab in this repository and click "Report a vulnerability" to create a private security advisory.
2.  **QQ Group**: Join the project QQ group (1073739854) and contact the administrators within the group to report the issue.

Please do not report security vulnerabilities through public Issue discussions, forums, or social media to prevent the risk from spreading before a fix is available.

## Report Requirements

To help us validate and address the vulnerability quickly, your report should include as much of the following information as possible:

*   **Clear Description**: The nature of the vulnerability and its potential impact.
*   **Detailed Steps to Reproduce**: A complete sequence of actions, from environment setup to triggering the vulnerability.
*   **Scope of Impact**: Which components or features of TypeRenew are affected.
*   **Version Information**: The version of TypeRenew, PHP, and database type you were using when you found the vulnerability.
*   **Suggested Fix (If possible)**: Your suggestions or ideas on how to fix the issue.

## Vulnerability Handling Process

1.  **Acknowledgment & Response**: We will acknowledge receipt of your report and make initial contact within **7 business days**.
2.  **Assessment & Fix**: After confirmation, we will assess the severity and work on developing a patch. The time required depends on the complexity of the issue, and we will strive for a prompt resolution.
3.  **Release & Disclosure**: Once the fix is ready, we will:
    *   Release a security update in a new version.
    *   Acknowledge and describe the fixed vulnerability appropriately in the project's Release Notes or security advisories (subject to the reporter's consent).

## Prohibited Actions

*   Do **NOT** publicly disclose vulnerability details or demonstrate proof-of-concept (PoC) attacks on any public platform before a fix is released.
*   Do **NOT** conduct any security testing or attacks against TypeRenew instances that you do not own or have explicit authorization to test.
*   Do **NOT** exploit the vulnerability for any illegal purposes, including but not limited to data theft, tampering, destruction, or service disruption.

## Security Recommendations

To keep your TypeRenew installation secure, we recommend following these best practices:

*   **Update Promptly**: Always keep your installation updated to the latest official release.
*   **Principle of Least Privilege**: Configure database accounts and server filesystem directory permissions with the minimum required for operation.
*   **Use Strong Credentials**: Enforce strong passwords for administrator accounts and change them periodically.
*   **Regular Maintenance**: Regularly check for and apply security patches for your server OS, PHP, and any plugins in use.
*   **Secure Configuration**: Configure sensitive features like caching and email properly, avoiding weak passwords or default settings.
