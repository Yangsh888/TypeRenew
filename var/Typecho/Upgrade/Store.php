<?php

namespace Typecho\Upgrade;

use RuntimeException;

class Store
{
    private string $root;
    private string $stateFile;
    private string $lockFile;

    public function __construct(?string $root = null)
    {
        $this->root = self::resolveRoot($root);
        $this->stateFile = $this->root . '/State/latest.json';
        $this->lockFile = $this->root . '/State/upgrade.lock';
        $this->ensureDirs();
    }

    public static function resolveRoot(?string $root = null): string
    {
        $resolved = $root ?? (defined('__TYPECHO_UPGRADE_DIR__')
            ? __TYPECHO_UPGRADE_DIR__
            : __TYPECHO_ROOT_DIR__ . '/var/Upgrade');

        return rtrim(str_replace('\\', '/', $resolved), '/');
    }

    public static function inspect(?string $root = null): array
    {
        $root = self::resolveRoot($root);
        $items = [];
        $blocking = [];
        $warning = [];

        foreach ([
            '升级目录' => $root,
            '升级包目录' => $root . '/Packages',
            '临时目录' => $root . '/Staging',
            '回滚目录' => $root . '/Backup',
            '状态目录' => $root . '/State'
        ] as $label => $path) {
            $info = self::inspectDir($path);
            $items[] = [
                'label' => $label,
                'path' => $path,
                'ready' => $info['ready'],
                'status' => $info['status'],
                'detail' => $info['detail']
            ];

            if (!$info['ready']) {
                $blocking[] = $label . '不可用：' . $path . '（' . $info['detail'] . '）';
            }
        }

        $stateFile = $root . '/State/latest.json';
        $lockFile = $root . '/State/upgrade.lock';
        $state = null;

        if (is_file($stateFile)) {
            if (!is_readable($stateFile)) {
                $blocking[] = '升级状态文件不可读：' . $stateFile;
            } else {
                $content = file_get_contents($stateFile);
                if ($content === false) {
                    $blocking[] = '升级状态文件不可读：' . $stateFile;
                } elseif ($content !== '') {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded)) {
                        $state = $decoded;
                    } else {
                        $warning[] = '升级状态文件格式异常，建议先清理升级包';
                    }
                }
            }
        }

        $lockBusy = self::isLockBusy($lockFile);
        if ($lockBusy) {
            $blocking[] = '已有升级任务正在执行，请稍后重试';
        } elseif (is_file($lockFile)) {
            $warning[] = '检测到历史升级锁文件，系统会在下次升级时复用该文件';
        }

        $artifacts = self::countArtifacts($root);
        if ($artifacts > 0 && !is_array($state)) {
            $warning[] = '检测到未清理的升级临时文件，建议先清理升级包后再继续';
        }

        return [
            'root' => $root,
            'state' => $state,
            'lockBusy' => $lockBusy,
            'artifactCount' => $artifacts,
            'blocking' => array_values(array_unique($blocking)),
            'warning' => array_values(array_unique($warning)),
            'items' => $items
        ];
    }

    public function ensureDirs(): void
    {
        foreach (['', '/Packages', '/Staging', '/Backup', '/State'] as $suffix) {
            $dir = $this->root . $suffix;
            if (is_dir($dir)) {
                continue;
            }

            $parent = self::findExistingParent(dirname($dir));
            if ($parent === null || !is_dir($parent) || !is_writable($parent)) {
                throw new RuntimeException('升级目录不可写: ' . $dir);
            }

            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('升级目录不可写: ' . $dir);
            }
        }
    }

    public function path(string $relative): string
    {
        return $this->root . '/' . ltrim(str_replace('\\', '/', $relative), '/');
    }

    public function readState(): ?array
    {
        if (!is_file($this->stateFile) || !is_readable($this->stateFile)) {
            return null;
        }

        $content = file_get_contents($this->stateFile);
        if ($content === false || $content === '') {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    public function writeState(array $state): void
    {
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('升级状态写入失败');
        }

        $dir = dirname($this->stateFile);
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new RuntimeException('升级状态写入失败');
        }

        if (file_put_contents($this->stateFile, $json, LOCK_EX) === false) {
            throw new RuntimeException('升级状态写入失败');
        }
    }

    public function clearState(): void
    {
        if (is_file($this->stateFile) && is_writable($this->stateFile)) {
            unlink($this->stateFile);
        }
    }

    public function acquireLock()
    {
        $lockDir = dirname($this->lockFile);
        if (!is_dir($lockDir) || !is_writable($lockDir)) {
            throw new RuntimeException('升级锁文件创建失败');
        }

        $fp = fopen($this->lockFile, 'c+');
        if ($fp === false) {
            throw new RuntimeException('升级锁文件创建失败');
        }

        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            throw new RuntimeException('已有升级任务正在执行');
        }

        return $fp;
    }

    public function releaseLock($fp): void
    {
        if (is_resource($fp)) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function removeTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            $parent = dirname($path);
            if (is_dir($parent) && is_writable($parent)) {
                unlink($path);
            }
            return;
        }

        if (!is_readable($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->removeTree($path . '/' . $item);
        }

        if (is_writable($path) && is_writable(dirname($path))) {
            rmdir($path);
        }
    }

    private static function inspectDir(string $path): array
    {
        if (is_dir($path)) {
            if (is_writable($path)) {
                return [
                    'ready' => true,
                    'status' => '可写',
                    'detail' => '目录可直接使用'
                ];
            }

            return [
                'ready' => false,
                'status' => '不可写',
                'detail' => '请开放目录写权限'
            ];
        }

        if (file_exists($path)) {
            return [
                'ready' => false,
                'status' => '路径冲突',
                'detail' => '存在同名文件，无法创建目录'
            ];
        }

        $parent = self::findExistingParent(dirname($path));
        if ($parent === null || !is_dir($parent)) {
            return [
                'ready' => false,
                'status' => '不可创建',
                'detail' => '上级目录不存在'
            ];
        }

        if (!is_writable($parent)) {
            return [
                'ready' => false,
                'status' => '不可创建',
                'detail' => '上级目录不可写'
            ];
        }

        return [
            'ready' => true,
            'status' => '可创建',
            'detail' => '首次使用时会自动创建'
        ];
    }

    private static function findExistingParent(string $path): ?string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        while ($path !== '') {
            if (file_exists($path)) {
                return $path;
            }

            $parent = dirname($path);
            if ($parent === $path) {
                break;
            }
            $path = $parent;
        }

        return null;
    }

    private static function isLockBusy(string $lockFile): bool
    {
        if (!is_file($lockFile)) {
            return false;
        }

        $lockDir = dirname($lockFile);
        if (!is_dir($lockDir) || !is_writable($lockDir)) {
            return true;
        }

        $fp = fopen($lockFile, 'c+');
        if ($fp === false) {
            return true;
        }

        $locked = !flock($fp, LOCK_EX | LOCK_NB);
        if (!$locked) {
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        return $locked;
    }

    private static function countArtifacts(string $root): int
    {
        $count = 0;

        foreach (['Packages', 'Staging', 'Backup'] as $dir) {
            $path = $root . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }

            if (!is_readable($path)) {
                continue;
            }

            $items = scandir($path);
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $count++;
            }
        }

        return $count;
    }
}
