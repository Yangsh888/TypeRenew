<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php echo htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        :root {
            --tr-bg: #f3f6fb;
            --tr-fg: #111827;
            --tr-muted: #6b7280;
            --tr-card: #ffffff;
            --tr-border: #e5e7eb;
            --tr-accent: #2563eb;
            --tr-accent-2: #1d4ed8;
            --tr-radius: 14px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --tr-bg: #0f172a;
                --tr-fg: #f8fafc;
                --tr-muted: #94a3b8;
                --tr-card: #111827;
                --tr-border: #1f2937;
                --tr-accent: #60a5fa;
                --tr-accent-2: #3b82f6;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
            background: radial-gradient(1200px 600px at 10% -10%, rgba(37,99,235,.1), transparent 55%), var(--tr-bg);
            color: var(--tr-fg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .box {
            width: min(720px, 100%);
            background: var(--tr-card);
            border: 1px solid var(--tr-border);
            border-radius: var(--tr-radius);
            box-shadow: 0 20px 60px rgba(0,0,0,.12);
            overflow: hidden;
        }
        .head {
            padding: 22px 24px;
            border-bottom: 1px solid var(--tr-border);
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .dot {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--tr-accent), var(--tr-accent-2));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
        }
        .title {
            font-size: 20px;
            line-height: 1.2;
            margin: 0;
            font-weight: 700;
        }
        .body {
            padding: 22px 24px;
        }
        .host {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 10px 0;
            word-break: break-all;
        }
        .url {
            margin: 0;
            color: var(--tr-muted);
            line-height: 1.65;
            word-break: break-all;
        }
        .tip {
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--tr-border);
            background: rgba(37,99,235,.06);
            color: var(--tr-muted);
        }
        .foot {
            padding: 18px 24px 24px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            height: 40px;
            border-radius: 10px;
            border: 1px solid transparent;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-main {
            background: linear-gradient(90deg, var(--tr-accent), var(--tr-accent-2));
            color: #fff;
        }
        .btn-sub {
            border-color: var(--tr-border);
            color: var(--tr-fg);
            background: transparent;
        }
        .meta {
            margin-left: auto;
            color: var(--tr-muted);
            display: inline-flex;
            align-items: center;
        }
    </style>
</head>
<body>
<main class="box">
    <header class="head">
        <span class="dot">R</span>
        <h1 class="title"><?php echo htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8'); ?></h1>
    </header>
    <section class="body">
        <p class="host"><?php echo htmlspecialchars($host !== '' ? $host : $display, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="url"><?php echo htmlspecialchars((string) $display, ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="tip"><?php _e('你即将离开当前站点并访问外部链接，请注意识别目标站点安全性。'); ?></div>
    </section>
    <footer class="foot">
        <a class="btn btn-main" href="<?php echo htmlspecialchars((string) $jumpUrl, ENT_QUOTES, 'UTF-8'); ?>"<?php if (!empty($newTab)): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>><?php _e('继续访问'); ?></a>
        <a class="btn btn-sub" href="<?php echo htmlspecialchars((string) $home, ENT_QUOTES, 'UTF-8'); ?>"><?php _e('返回首页'); ?></a>
        <?php if ((int) $staySeconds > 0): ?>
            <span class="meta"><?php _e('%d 秒后自动跳转', (int) $staySeconds); ?></span>
        <?php endif; ?>
    </footer>
</main>
<?php if ((int) $staySeconds > 0): ?>
<script>
    (function () {
        var left = <?php echo (int) $staySeconds; ?>;
        var node = document.querySelector('.meta');
        var jump = <?php echo json_encode((string) $jumpUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var timer = setInterval(function () {
            left -= 1;
            if (node && left >= 0) {
                node.textContent = left + ' 秒后自动跳转';
            }
            if (left <= 0) {
                clearInterval(timer);
                window.location.href = jump;
            }
        }, 1000);
    })();
</script>
<?php endif; ?>
</body>
</html>
