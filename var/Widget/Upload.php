<?php

namespace Widget;

use Typecho\Common;
use Typecho\Config;
use Typecho\Date;
use Typecho\Db\Exception;
use Typecho\Plugin;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 上传组件
 *
 * @author qining
 * @package Widget
 */
class Upload extends Contents implements ActionInterface
{
    public const UPLOAD_DIR = '/usr/uploads';

    /**
     * 删除文件
     *
     * @param array $content 文件相关信息
     * @return bool
     */
    public static function deleteHandle(array $content): bool
    {
        $result = Plugin::factory(Upload::class)->trigger($hasDeleted)->call('deleteHandle', $content);
        if ($hasDeleted) {
            return $result;
        }

        $path = __TYPECHO_ROOT_DIR__ . '/' . $content['attachment']->path;
        return is_file($path) && is_writable(dirname($path)) ? unlink($path) : false;
    }

    /**
     * 获取实际文件绝对访问路径
     *
     * @param Config $attachment 文件相关信息
     * @return string
     */
    public static function attachmentHandle(Config $attachment): string
    {
        $result = Plugin::factory(Upload::class)->trigger($hasPlugged)->call('attachmentHandle', $attachment);
        if ($hasPlugged) {
            return $result;
        }

        $options = Options::alloc();
        return Common::url(
            $attachment->path,
            defined('__TYPECHO_UPLOAD_URL__') ? __TYPECHO_UPLOAD_URL__ : $options->siteUrl
        );
    }

    /**
     * 获取实际文件数据
     *
     * @param array $content
     * @return string
     */
    public static function attachmentDataHandle(array $content): string
    {
        $result = Plugin::factory(Upload::class)->trigger($hasPlugged)->call('attachmentDataHandle', $content);
        if ($hasPlugged) {
            return $result;
        }

        return file_get_contents(
            Common::url(
                $content['attachment']->path,
                defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__
            )
        );
    }

    public function action()
    {
        if ($this->user->pass('contributor', true) && $this->request->isPost()) {
            $this->security->protect();
            if ($this->request->is('do=modify&cid')) {
                $this->modify();
            } else {
                $this->upload();
            }
        } else {
            $this->response->setStatus(403)->throwContent('', 'text/plain');
        }
    }

    /**
     * @throws Exception
     */
    public function modify()
    {
        try {
            $file = $this->requireUploadFile();

            $this->db->fetchRow(
                $this->select()->where(
                    'table.contents.cid = ?',
                    $this->request->filter('int')->get('cid')
                )
                ->where('table.contents.type = ?', 'attachment'),
                [$this, 'push']
            );

            if (!$this->have()) {
                if ($this->request->isAjax()) {
                    $this->response->setStatus(404)->throwJson([
                        'success' => 0,
                        'message' => _t('附件不存在')
                    ]);
                }

                $this->response->setStatus(404)->throwContent('', 'text/plain');
            }

            if (!$this->allow('edit')) {
                if ($this->request->isAjax()) {
                    $this->response->setStatus(403)->throwJson([
                        'success' => 0,
                        'message' => _t('没有编辑该附件的权限')
                    ]);
                }

                $this->response->setStatus(403)->throwContent('', 'text/plain');
            }

            if ($this->request->isAjax()) {
                $file['name'] = urldecode($file['name']);
            }

            $result = self::modifyHandle($this->toColumn(['cid', 'attachment', 'parent']), $file);
            if (false === $result) {
                throw new \RuntimeException(_t('附件替换失败，请检查文件类型与目录权限'));
            }

            self::pluginHandle()->call('beforeModify', $result);

            $this->update([
                'text' => json_encode($result)
            ], $this->db->sql()->where('cid = ?', $this->cid));

            $this->db->fetchRow($this->select()->where('table.contents.cid = ?', $this->cid)
                ->where('table.contents.type = ?', 'attachment'), [$this, 'push']);

            self::pluginHandle()->call('modify', $this);

            $this->response->throwJson([$this->attachment->url, [
                'cid' => $this->cid,
                'title' => $this->attachment->name,
                'type' => $this->attachment->type,
                'size' => $this->attachment->size,
                'bytes' => number_format(ceil($this->attachment->size / 1024)) . ' Kb',
                'isImage' => $this->attachment->isImage,
                'url' => $this->attachment->url,
                'permalink' => $this->permalink
            ]]);
        } catch (\Throwable $e) {
            $this->throwUploadError($e->getMessage());
        }
    }

    /**
     * 修改文件处理函数,如果需要实现自己的文件哈希或者特殊的文件系统,请在options表里把modifyHandle改成自己的函数
     *
     * @param array $content 老文件
     * @param array $file 新上传的文件
     * @return mixed
     */
    public static function modifyHandle(array $content, array $file)
    {
        if (empty($file['name'])) {
            throw new \RuntimeException(_t('没有选择任何附件文件'));
        }

        $result = self::pluginHandle()->trigger($hasModified)->call('modifyHandle', $content, $file);
        if ($hasModified) {
            return $result;
        }

        $ext = self::getSafeName($file['name']);

        if ($content['attachment']->type != $ext) {
            throw new \RuntimeException(_t('上传文件类型与原附件不一致'));
        }

        $path = Common::url(
            $content['attachment']->path,
            defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__
        );
        self::replaceUploadedFile($file, $path);

        if (!isset($file['size'])) {
            $file['size'] = filesize($path);
        }

        return [
            'name' => $content['attachment']->name,
            'path' => $content['attachment']->path,
            'size' => $file['size'],
            'type' => $content['attachment']->type,
            'mime' => $content['attachment']->mime
        ];
    }

    private static function getSafeName(string &$name): string
    {
        $name = str_replace(['"', '<', '>'], '', $name);
        $name = str_replace('\\', '/', $name);
        $name = false === strpos($name, '/') ? ('a' . $name) : str_replace('/', '/a', $name);
        $info = pathinfo($name);
        $name = substr($info['basename'], 1);

        return isset($info['extension']) ? strtolower($info['extension']) : '';
    }

    private static function makeUploadDir(string $path): bool
    {
        $path = preg_replace("/\\\+/", '/', $path);
        $current = rtrim($path, '/');
        $last = $current;

        while (!is_dir($current) && false !== strpos($path, '/')) {
            $last = $current;
            $current = dirname($current);
        }

        if ($last == $current) {
            return true;
        }

        if (!is_dir($current) || !is_writable($current)) {
            return false;
        }

        if (!mkdir($last, 0755) && !is_dir($last)) {
            return false;
        }

        return self::makeUploadDir($path);
    }

    public static function replaceUploadedFile(array $file, string $target): void
    {
        $dir = dirname($target);
        if (!is_dir($dir)) {
            if (!self::makeUploadDir($dir)) {
                throw new \RuntimeException(_t('附件目录不可写：%s', $dir));
            }
        }

        if (is_file($target) && !is_writable($dir)) {
            throw new \RuntimeException(_t('附件写入失败，请检查上传目录权限'));
        }

        $temp = tempnam($dir, 'upl');
        if ($temp === false) {
            throw new \RuntimeException(_t('附件写入失败，请检查上传目录权限'));
        }

        try {
            if (isset($file['tmp_name'])) {
                if (!move_uploaded_file((string) $file['tmp_name'], $temp)) {
                    throw new \RuntimeException(_t('附件写入失败，请检查上传目录权限'));
                }
            } elseif (isset($file['bytes']) && is_string($file['bytes'])) {
                if (file_put_contents($temp, (string) $file['bytes']) === false) {
                    throw new \RuntimeException(_t('附件写入失败，请检查上传目录权限'));
                }
            } elseif (isset($file['bits']) && is_string($file['bits'])) {
                if (file_put_contents($temp, (string) $file['bits']) === false) {
                    throw new \RuntimeException(_t('附件写入失败，请检查上传目录权限'));
                }
            } else {
                throw new \RuntimeException(_t('附件上传数据无效'));
            }

            self::swapUploadedFile($temp, $target);
        } catch (\Throwable $e) {
            if (is_file($temp)) {
                unlink($temp);
            }

            throw $e;
        }
    }

    private static function swapUploadedFile(string $temp, string $target): void
    {
        if (!is_file($target)) {
            if (!rename($temp, $target)) {
                throw new \RuntimeException(_t('附件写入失败，请检查上传目录权限'));
            }

            return;
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            if (!rename($temp, $target)) {
                throw new \RuntimeException(_t('附件写入失败，请检查上传目录权限'));
            }

            return;
        }

        $backup = $target . '.' . uniqid('bak', true);
        if (!rename($target, $backup)) {
            throw new \RuntimeException(_t('附件写入失败，请检查上传目录权限'));
        }

        try {
            if (!rename($temp, $target)) {
                throw new \RuntimeException(_t('附件写入失败，请检查上传目录权限'));
            }

            if (is_file($backup)) {
                unlink($backup);
            }
        } catch (\Throwable $e) {
            if (is_file($backup) && !is_file($target)) {
                rename($backup, $target);
            }

            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function upload()
    {
        try {
            $file = $this->requireUploadFile();

            if ($this->request->isAjax()) {
                $file['name'] = urldecode($file['name']);
            }
            $result = self::uploadHandle($file);

            if (false === $result) {
                throw new \RuntimeException(_t('附件上传失败，请检查文件类型与目录权限'));
            }

            self::pluginHandle()->call('beforeUpload', $result);

            $struct = [
                'title' => $result['name'],
                'slug' => $result['name'],
                'type' => 'attachment',
                'status' => 'publish',
                'text' => json_encode($result),
                'allowComment' => 1,
                'allowPing' => 0,
                'allowFeed' => 1
            ];

            if (isset($this->request->cid)) {
                $cid = $this->request->filter('int')->get('cid');

                if ($this->isWriteable($this->db->sql()->where('cid = ?', $cid))) {
                    $struct['parent'] = $cid;
                }
            }

            $insertId = $this->insert($struct);

            $this->db->fetchRow($this->select()->where('table.contents.cid = ?', $insertId)
                ->where('table.contents.type = ?', 'attachment'), [$this, 'push']);

            self::pluginHandle()->call('upload', $this);

            $this->response->throwJson([$this->attachment->url, [
                'cid' => $insertId,
                'title' => $this->attachment->name,
                'type' => $this->attachment->type,
                'size' => $this->attachment->size,
                'bytes' => number_format(ceil($this->attachment->size / 1024)) . ' Kb',
                'isImage' => $this->attachment->isImage,
                'url' => $this->attachment->url,
                'permalink' => $this->permalink
            ]]);
        } catch (\Throwable $e) {
            $this->throwUploadError($e->getMessage());
        }
    }

    /**
     * 上传文件处理函数,如果需要实现自己的文件哈希或者特殊的文件系统,请在options表里把uploadHandle改成自己的函数
     *
     * @param array $file 上传的文件
     * @return mixed
     */
    public static function uploadHandle(array $file)
    {
        if (empty($file['name'])) {
            throw new \RuntimeException(_t('没有选择任何附件文件'));
        }

        $result = self::pluginHandle()->trigger($hasUploaded)->call('uploadHandle', $file);
        if ($hasUploaded) {
            return $result;
        }

        $ext = self::getSafeName($file['name']);

        if (!self::checkFileType($ext)) {
            throw new \RuntimeException(_t('文件扩展名不被支持'));
        }

        $date = new Date();
        $path = Common::url(
            defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : self::UPLOAD_DIR,
            defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__
        ) . '/' . $date->year . '/' . $date->month;

        if (!is_dir($path)) {
            if (!self::makeUploadDir($path)) {
                throw new \RuntimeException(_t('上传目录不可写：%s', $path));
            }
        }

        $fileName = sprintf('%u', crc32(uniqid())) . '.' . $ext;
        $path = $path . '/' . $fileName;

        if (isset($file['tmp_name'])) {
            if (!move_uploaded_file($file['tmp_name'], $path)) {
                throw new \RuntimeException(_t('附件写入失败，请检查上传目录权限'));
            }
        } elseif (isset($file['bytes'])) {
            if (file_put_contents($path, $file['bytes']) === false) {
                throw new \RuntimeException(_t('附件写入失败，请检查上传目录权限'));
            }
        } elseif (isset($file['bits'])) {
            if (file_put_contents($path, $file['bits']) === false) {
                throw new \RuntimeException(_t('附件写入失败，请检查上传目录权限'));
            }
        } else {
            throw new \RuntimeException(_t('附件上传数据无效'));
        }

        if (!isset($file['size'])) {
            $file['size'] = filesize($path);
        }

        return [
            'name' => $file['name'],
            'path' => (defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : self::UPLOAD_DIR)
                . '/' . $date->year . '/' . $date->month . '/' . $fileName,
            'size' => $file['size'],
            'type' => $ext,
            'mime' => Common::mimeContentType($path)
        ];
    }

    public static function checkFileType(string $ext): bool
    {
        if ($ext === '' || !preg_match('/^[a-z0-9]+$/i', $ext)) {
            return false;
        }
        if (preg_match("/^(php|php3|php4|php5|php7|php8|phtml|pht|phar|cgi|shtml|asp|aspx|jsp|rb|py|pl|dll|exe|bat|cmd|com)$/i", $ext)) {
            return false;
        }
        $options = Options::alloc();
        return in_array($ext, $options->allowedAttachmentTypes);
    }

    private function requireUploadFile(): array
    {
        $file = $_FILES['file'] ?? reset($_FILES);
        if (!is_array($file)) {
            throw new \RuntimeException(_t('没有选择任何附件文件'));
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException(_t('没有选择任何附件文件'));
        }

        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new \RuntimeException(Common::uploadErrorMessage($error, '附件上传', '没有选择任何附件文件'));
        }

        return $file;
    }

    private function throwUploadError(string $message): void
    {
        $this->response->setStatus(400);
        $this->response->throwJson([
            'success' => false,
            'message' => $message === '' ? _t('附件上传失败') : $message
        ]);
    }
}
