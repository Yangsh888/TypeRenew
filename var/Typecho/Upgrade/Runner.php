<?php

namespace Typecho\Upgrade;

use RuntimeException;
use Typecho\Common;
use ZipArchive;

class Runner
{
    private Store $store;
    private string $rootDir;
    private const MAX_ZIP_FILES = 20000;
    private const MAX_ZIP_UNPACKED_BYTES = 2147483648;

    public function __construct(?Store $store = null)
    {
        $this->store = $store ?? new Store();
        $this->rootDir = rtrim(str_replace('\\', '/', __TYPECHO_ROOT_DIR__), '/');
    }

    public function saveUpload(array $file, ?bool $allowInstallOverride = null): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('当前环境缺少 ZipArchive 扩展');
        }

        $name = (string) ($file['name'] ?? '');
        $tmp = (string) ($file['tmp_name'] ?? '');
        $error = (int) ($file['error'] ?? 4);
        $size = (int) ($file['size'] ?? 0);

        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            throw new RuntimeException('升级包上传失败');
        }

        if ($size <= 0 || $size > 524288000) {
            throw new RuntimeException('升级包大小超出限制');
        }

        if (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException('仅支持 zip 格式升级包');
        }

        $id = date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $packagePath = $this->store->path('Packages/' . $id . '.zip');

        if (!@move_uploaded_file($tmp, $packagePath)) {
            throw new RuntimeException('升级包保存失败');
        }

        return $this->prepare($id, $packagePath, $allowInstallOverride);
    }

    public function prepare(string $id, string $packagePath, ?bool $allowInstallOverride = null): array
    {
        $stageDir = $this->store->path('Staging/' . $id);
        $this->store->removeTree($stageDir);
        $this->ensureDir($stageDir);
        $payloadDir = $stageDir . '/payload';
        $this->ensureDir($payloadDir);

        $zip = new ZipArchive();
        if ($zip->open($packagePath) !== true) {
            throw new RuntimeException('升级包无法打开');
        }

        if ($zip->numFiles > self::MAX_ZIP_FILES) {
            $zip->close();
            throw new RuntimeException('升级包文件数量超出限制');
        }

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (is_array($stat) && isset($stat['size'])) {
                $total += (int) $stat['size'];
                if ($total > self::MAX_ZIP_UNPACKED_BYTES) {
                    $zip->close();
                    throw new RuntimeException('升级包解压后体积超出限制');
                }
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $raw = (string) $zip->getNameIndex($i);
            $entry = Manifest::normalize($raw);

            if ($entry === '' || str_ends_with($entry, '/')) {
                continue;
            }

            if (str_starts_with($entry, '__MACOSX/')) {
                continue;
            }

            if (!Manifest::validatePath($entry)) {
                $zip->close();
                throw new RuntimeException('升级包包含非法路径: ' . $entry);
            }

            $target = $payloadDir . '/' . $entry;
            $targetDir = dirname($target);
            $this->ensureDir($targetDir);

            $stream = $zip->getStream($raw);
            if (!is_resource($stream)) {
                $zip->close();
                throw new RuntimeException('升级包读取失败: ' . $entry);
            }

            $fp = @fopen($target, 'wb');
            if ($fp === false) {
                fclose($stream);
                $zip->close();
                throw new RuntimeException('升级包解压失败: ' . $entry);
            }

            stream_copy_to_stream($stream, $fp);
            fclose($stream);
            fclose($fp);
        }

        $zip->close();

        $manifestPath = $payloadDir . '/typerenew-upgrade.json';
        $packageRoot = $payloadDir;
        if (!is_file($manifestPath)) {
            $manifestList = [];
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($payloadDir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                if ($fileInfo->getFilename() === 'typerenew-upgrade.json') {
                    $manifestList[] = str_replace('\\', '/', $fileInfo->getPathname());
                }
            }

            if (count($manifestList) === 1) {
                $manifestPath = $manifestList[0];
                $packageRoot = dirname($manifestPath);
            } else {
                throw new RuntimeException('升级包缺少 typerenew-upgrade.json');
            }
        }

        $manifest = Manifest::parse((string) file_get_contents($manifestPath));
        $allowInstall = $allowInstallOverride ?? (bool) ($manifest['allowInstall'] ?? false);
        $mode = empty($manifest['files']) ? 'full' : 'patch';

        $payloadRoot = $mode === 'patch'
            ? $this->resolvePayloadRoot($payloadDir, $packageRoot, $manifest['files'])
            : $packageRoot;

        $files = $this->collectFiles($payloadRoot, $manifest['files'], $allowInstall);
        $this->validateTargets($files, $allowInstall);
        $this->requireVersionFile($payloadRoot, $files, (string) ($manifest['to'] ?? ''));

        $state = [
            'id' => $id,
            'package' => basename($packagePath),
            'packagePath' => $packagePath,
            'stageDir' => $stageDir,
            'payloadDir' => $payloadRoot,
            'status' => 'ready',
            'createdAt' => time(),
            'mode' => $mode,
            'allowInstall' => $allowInstall,
            'manifest' => $manifest,
            'files' => $files
        ];

        $this->store->writeState($state);
        return $state;
    }

    public function apply(string $id): array
    {
        $lock = $this->store->acquireLock();
        $rollback = [];
        try {
            $state = $this->store->readState();
            if (!is_array($state) || ($state['id'] ?? '') !== $id) {
                throw new RuntimeException('未找到可执行的升级包');
            }

            if (($state['status'] ?? '') !== 'ready') {
                throw new RuntimeException('升级包状态不可执行');
            }

            $manifest = $state['manifest'] ?? [];
            $files = $state['files'] ?? [];
            $payloadDir = (string) ($state['payloadDir'] ?? '');
            $allowInstall = (bool) ($state['allowInstall'] ?? false);

            if (!is_array($manifest) || !is_array($files) || !is_dir($payloadDir)) {
                throw new RuntimeException('升级包状态损坏');
            }

            $currentVersion = Common::VERSION;
            $fromVersion = (string) ($manifest['from'] ?? '');
            $toVersion = (string) ($manifest['to'] ?? '');

            if ($fromVersion !== $currentVersion) {
                throw new RuntimeException('升级包来源版本不匹配，当前版本为 ' . $currentVersion);
            }

            if (version_compare($toVersion, $currentVersion, '<=')) {
                throw new RuntimeException('升级包目标版本无效');
            }

            $this->validateTargets($files, $allowInstall);
            $this->checkWritable($files);

            $state['status'] = 'applying';
            $state['applyingAt'] = time();
            $state['progress'] = ['done' => 0, 'total' => count($files), 'file' => ''];
            $this->store->writeState($state);

            $backupRoot = $this->store->path('Backup/' . $id . '/files');
            $this->ensureDir($backupRoot);
            $done = 0;
            $total = count($files);
            foreach ($files as $relative) {
                $done++;
                $source = $payloadDir . '/' . $relative;
                $target = $this->targetPath($relative);

                if (!is_file($source)) {
                    throw new RuntimeException('升级文件缺失: ' . $relative);
                }

                $targetDir = dirname($target);
                $this->ensureDir($targetDir);

                if (is_file($target)) {
                    $backup = $backupRoot . '/' . $relative;
                    $this->ensureDir(dirname($backup));
                    if (!@copy($target, $backup)) {
                        throw new RuntimeException('升级前备份失败: ' . $relative);
                    }
                    $rollback[] = ['type' => 'replace', 'target' => $target, 'backup' => $backup];
                } else {
                    $rollback[] = ['type' => 'create', 'target' => $target];
                }

                $this->atomicReplace($source, $target);

                if ($done === 1 || $done === $total || ($done % 20) === 0) {
                    $state['progress'] = ['done' => $done, 'total' => $total, 'file' => $relative];
                    $this->store->writeState($state);
                }
            }

            $state['status'] = 'applied';
            $state['appliedAt'] = time();
            $state['appliedFiles'] = $done;
            $state['progress'] = ['done' => $done, 'total' => $total, 'file' => ''];
            $this->store->writeState($state);
            return $state;
        } catch (\Throwable $e) {
            $this->rollback($rollback);
            $state = $this->store->readState();
            if (is_array($state)) {
                $state['status'] = 'failed';
                $state['failedAt'] = time();
                $state['error'] = $e->getMessage();
                $state['progress'] = $state['progress'] ?? null;
                $this->store->writeState($state);
            }
            throw $e;
        } finally {
            $this->store->releaseLock($lock);
        }
    }

    private function atomicReplace(string $source, string $target): void
    {
        $dir = dirname($target);
        $base = basename($target);
        $token = bin2hex(random_bytes(4));
        $tmp = $dir . '/.' . $base . '.new-' . $token;
        $old = $dir . '/.' . $base . '.old-' . $token;

        if (!@copy($source, $tmp)) {
            @unlink($tmp);
            throw new RuntimeException('文件写入失败: ' . $base);
        }

        if (!is_file($tmp)) {
            throw new RuntimeException('文件写入失败: ' . $base);
        }

        if (is_file($target)) {
            if (!@rename($target, $old)) {
                @unlink($tmp);
                throw new RuntimeException('文件替换失败: ' . $base);
            }

            if (!@rename($tmp, $target)) {
                @rename($old, $target);
                @unlink($tmp);
                throw new RuntimeException('文件替换失败: ' . $base);
            }

            @unlink($old);
            return;
        }

        if (!@rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException('文件写入失败: ' . $base);
        }
    }

    public function clear(): void
    {
        $state = $this->store->readState();
        if (!is_array($state)) {
            return;
        }

        $stageDir = (string) ($state['stageDir'] ?? '');
        $packagePath = (string) ($state['packagePath'] ?? '');

        if ($stageDir !== '') {
            $this->store->removeTree($stageDir);
        }

        if ($packagePath !== '' && is_file($packagePath)) {
            @unlink($packagePath);
        }

        $this->store->clearState();
    }

    private function collectFiles(string $payloadDir, array $manifestFiles, bool $allowInstall): array
    {
        $files = [];

        if (!empty($manifestFiles)) {
            foreach ($manifestFiles as $item) {
                $relative = Manifest::normalize((string) $item);
                if ($relative === 'typerenew-upgrade.json') {
                    continue;
                }
                if (!Manifest::validatePath($relative)) {
                    throw new RuntimeException('升级文件路径无效: ' . $relative);
                }
                if (!$this->isAllowed($relative, $allowInstall)) {
                    throw new RuntimeException('升级包包含受保护路径: ' . $relative);
                }
                if (!is_file($payloadDir . '/' . $relative)) {
                    throw new RuntimeException('升级文件不存在: ' . $relative);
                }
                $files[$relative] = $relative;
            }
        } else {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($payloadDir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $full = str_replace('\\', '/', $fileInfo->getPathname());
                $relative = ltrim(substr($full, strlen(str_replace('\\', '/', $payloadDir))), '/');
                $relative = Manifest::normalize($relative);

                if ($relative === '' || $relative === 'typerenew-upgrade.json') {
                    continue;
                }

                if (!Manifest::validatePath($relative)) {
                    throw new RuntimeException('升级文件路径无效: ' . $relative);
                }

                if (!$this->isAllowed($relative, $allowInstall)) {
                    continue;
                }

                $files[$relative] = $relative;
            }
        }

        if (empty($files)) {
            throw new RuntimeException('升级包没有可用文件');
        }

        ksort($files);
        return array_values($files);
    }

    private function resolvePayloadRoot(string $extractRoot, string $manifestRoot, array $manifestFiles): string
    {
        if (empty($manifestFiles)) {
            return $manifestRoot;
        }

        $first = '';
        foreach ($manifestFiles as $item) {
            $relative = Manifest::normalize((string) $item);
            if ($relative === '' || $relative === 'typerenew-upgrade.json') {
                continue;
            }
            $first = $relative;
            break;
        }

        if ($first !== '') {
            $base = $this->findBaseBySuffix($extractRoot, $first);
            if ($base !== null && $this->hasAllFiles($base, $manifestFiles)) {
                return $base;
            }
        }

        $candidates = [];
        $current = str_replace('\\', '/', $manifestRoot);
        $extractRoot = rtrim(str_replace('\\', '/', $extractRoot), '/');

        while (true) {
            $candidates[] = $current;
            if ($current === $extractRoot) {
                break;
            }

            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = $parent;
        }

        if (!in_array($extractRoot, $candidates, true)) {
            $candidates[] = $extractRoot;
        }

        foreach ($candidates as $base) {
            if ($this->hasAllFiles($base, $manifestFiles)) {
                return $base;
            }
        }

        return $manifestRoot;
    }

    private function hasAllFiles(string $base, array $manifestFiles): bool
    {
        foreach ($manifestFiles as $item) {
            $relative = Manifest::normalize((string) $item);
            if ($relative === '' || $relative === 'typerenew-upgrade.json') {
                continue;
            }

            if (!is_file(rtrim($base, '/') . '/' . $relative)) {
                return false;
            }
        }

        return true;
    }

    private function findBaseBySuffix(string $extractRoot, string $relative): ?string
    {
        $extractRoot = rtrim(str_replace('\\', '/', $extractRoot), '/');
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $suffix = '/' . $relative;

        if (!is_dir($extractRoot)) {
            return null;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractRoot, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $full = str_replace('\\', '/', $fileInfo->getPathname());
            if (!str_ends_with($full, $suffix)) {
                continue;
            }

            $base = substr($full, 0, -strlen($suffix));
            if ($base === false || $base === '') {
                continue;
            }

            return $base;
        }

        return null;
    }

    private function validateTargets(array $files, bool $allowInstall): void
    {
        foreach ($files as $relative) {
            $relative = Manifest::normalize((string) $relative);
            if (!$this->isAllowed($relative, $allowInstall)) {
                throw new RuntimeException('升级包包含受保护路径: ' . $relative);
            }
            $this->targetPath($relative);
        }
    }

    private function checkWritable(array $files): void
    {
        foreach ($files as $relative) {
            $target = $this->targetPath((string) $relative);
            if (is_file($target)) {
                if (!is_writable($target)) {
                    throw new RuntimeException('文件不可写: ' . $relative);
                }
                continue;
            }

            $dir = dirname($target);
            while (!is_dir($dir) && $dir !== dirname($dir)) {
                $dir = dirname($dir);
            }

            if (!is_dir($dir) || !is_writable($dir)) {
                throw new RuntimeException('目录不可写: ' . $relative);
            }
        }
    }

    private function targetPath(string $relative): string
    {
        $relative = Manifest::normalize($relative);
        $target = $this->rootDir . '/' . $relative;
        $normalized = str_replace('\\', '/', $target);
        if (!str_starts_with($normalized, $this->rootDir . '/')) {
            throw new RuntimeException('升级目标路径非法: ' . $relative);
        }
        return $target;
    }

    private function isAllowed(string $relative, bool $allowInstall): bool
    {
        $relative = Manifest::normalize($relative);

        if ($relative === 'config.inc.php') {
            return false;
        }

        if (str_starts_with($relative, 'usr/uploads/')) {
            return false;
        }

        if (str_starts_with($relative, 'var/Upgrade/')) {
            return false;
        }

        if (str_starts_with($relative, 'install/') || $relative === 'install.php') {
            return $allowInstall;
        }

        if ($relative === 'index.php') {
            return true;
        }

        if (str_starts_with($relative, 'admin/')) {
            return true;
        }

        if (str_starts_with($relative, 'usr/')) {
            return true;
        }

        if (str_starts_with($relative, 'var/')) {
            return true;
        }

        return false;
    }

    private function requireVersionFile(string $payloadDir, array $files, string $toVersion): void
    {
        $target = 'var/Typecho/Common.php';
        if (!in_array($target, $files, true) || !is_file($payloadDir . '/' . $target)) {
            throw new RuntimeException('升级包缺少版本文件: ' . $target);
        }

        if ($toVersion !== '') {
            $content = (string) @file_get_contents($payloadDir . '/' . $target);
            if ($content === '') {
                throw new RuntimeException('升级包版本文件无法读取');
            }

            $pattern = "/public\\s+const\\s+VERSION\\s*=\\s*'([^']+)'/";
            if (preg_match($pattern, $content, $m) && isset($m[1])) {
                $found = (string) $m[1];
                if ($found !== $toVersion) {
                    throw new RuntimeException('升级包版本文件与目标版本不一致');
                }
            }
        }
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('目录不可写: ' . $dir);
        }
    }

    private function rollback(array $operations): void
    {
        if (empty($operations)) {
            return;
        }

        $operations = array_reverse($operations);
        foreach ($operations as $operation) {
            $type = (string) ($operation['type'] ?? '');
            $target = (string) ($operation['target'] ?? '');

            if ($type === 'replace') {
                $backup = (string) ($operation['backup'] ?? '');
                if ($backup !== '' && is_file($backup)) {
                    @copy($backup, $target);
                }
                continue;
            }

            if ($type === 'create' && $target !== '' && is_file($target)) {
                @unlink($target);
            }
        }
    }
}
