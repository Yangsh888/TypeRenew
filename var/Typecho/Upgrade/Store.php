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
        $this->root = $root ?? (defined('__TYPECHO_UPGRADE_DIR__')
            ? __TYPECHO_UPGRADE_DIR__
            : __TYPECHO_ROOT_DIR__ . '/var/Upgrade');
        $this->stateFile = $this->root . '/State/latest.json';
        $this->lockFile = $this->root . '/State/upgrade.lock';
        $this->ensureDirs();
    }

    public function ensureDirs(): void
    {
        foreach (['', '/Packages', '/Staging', '/Backup', '/State'] as $suffix) {
            $dir = $this->root . $suffix;
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
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
        if (!is_file($this->stateFile)) {
            return null;
        }

        $content = @file_get_contents($this->stateFile);
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

        if (@file_put_contents($this->stateFile, $json, LOCK_EX) === false) {
            throw new RuntimeException('升级状态写入失败');
        }
    }

    public function clearState(): void
    {
        if (is_file($this->stateFile)) {
            @unlink($this->stateFile);
        }
    }

    public function acquireLock()
    {
        $fp = @fopen($this->lockFile, 'c+');
        if ($fp === false) {
            throw new RuntimeException('升级锁文件创建失败');
        }

        if (!@flock($fp, LOCK_EX | LOCK_NB)) {
            @fclose($fp);
            throw new RuntimeException('已有升级任务正在执行');
        }

        return $fp;
    }

    public function releaseLock($fp): void
    {
        if (is_resource($fp)) {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    public function removeTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = @scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->removeTree($path . '/' . $item);
        }

        @rmdir($path);
    }
}
