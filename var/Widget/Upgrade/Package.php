<?php

namespace Widget\Upgrade;

use Typecho\Common;
use Typecho\Upgrade\Runner;
use Typecho\Widget\Exception;
use Widget\ActionInterface;
use Widget\Base\Options as BaseOptions;
use Widget\Notice;

class Package extends BaseOptions implements ActionInterface
{
    public function action()
    {
        if (!$this->user->pass('administrator', true) || !$this->request->isPost()) {
            $this->response->setStatus(403);
            return;
        }

        $this->security->protect();
        $do = (string) $this->request->get('do');

        try {
            if ($do === 'upload') {
                $this->assertEnvironmentReady();
                $runner = new Runner();
                $this->upload($runner);
            } elseif ($do === 'apply') {
                $this->assertEnvironmentReady();
                $runner = new Runner();
                $this->apply($runner);
            } elseif ($do === 'clear') {
                $runner = new Runner();
                $removed = $runner->clear();
                Notice::alloc()->set(
                    $removed > 0 ? _t('升级包与临时目录已清理') : _t('当前没有可清理的升级包或临时目录'),
                    $removed > 0 ? 'success' : 'notice'
                );
            } else {
                Notice::alloc()->set(_t('未知升级操作'), 'error');
            }
        } catch (Exception $e) {
            Notice::alloc()->set($e->getMessage(), 'error');
        } catch (\Throwable $e) {
            Notice::alloc()->set($e->getMessage(), 'error');
        }

        $this->response->redirect(Common::url('upgrade.php', $this->options->adminUrl));
    }

    private function upload(Runner $runner): void
    {
        $file = $_FILES['package'] ?? null;
        if (!is_array($file)) {
            throw new Exception(_t('请选择升级包 ZIP 文件'), 400);
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new Exception($this->uploadErrorMessage($error), 400);
        }

        $allowInstall = !empty($this->request->get('allowInstall'));
        $state = $runner->saveUpload($file, $allowInstall);
        $manifest = $state['manifest'] ?? [];
        $from = (string) ($manifest['from'] ?? '');
        $to = (string) ($manifest['to'] ?? '');
        Notice::alloc()->set(_t('升级包已上传：%s → %s', $from, $to), 'success');
    }

    private function apply(Runner $runner): void
    {
        $package = trim((string) $this->request->get('package'));
        if ($package === '') {
            throw new \RuntimeException('缺少升级包标识');
        }

        $state = $runner->apply($package);
        $manifest = $state['manifest'] ?? [];
        $to = (string) ($manifest['to'] ?? '');
        $applied = (int) ($state['appliedFiles'] ?? 0);
        $runner->clear();
        Notice::alloc()->set(_t('在线升级已完成：目标版本 %s，已覆盖 %d 个文件，升级包已自动清理，请刷新页面后按提示完成数据库升级', $to, $applied), 'success');
    }

    private function assertEnvironmentReady(): void
    {
        $report = Runner::inspect();
        if ($report['available']) {
            return;
        }

        $messages = $report['blocking'];
        $message = '在线升级环境未就绪';
        if (!empty($messages)) {
            $message .= '：' . implode('；', $messages);
        }

        $message .= '。请开放升级目录写权限';
        if (defined('__TYPECHO_UPGRADE_DIR__')) {
            $message .= '，或调整当前升级目录配置';
        } else {
            $message .= '，或在 config.inc.php 中定义 __TYPECHO_UPGRADE_DIR__ 指向可写目录';
        }

        throw new Exception(_t($message), 500);
    }

    private function uploadErrorMessage(int $error): string
    {
        switch ($error) {
            case UPLOAD_ERR_NO_FILE:
                return _t('请选择升级包 ZIP 文件');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return _t('升级包上传失败：文件体积超过服务器限制');
            case UPLOAD_ERR_PARTIAL:
                return _t('升级包上传失败：文件仅部分上传');
            case UPLOAD_ERR_NO_TMP_DIR:
                return _t('升级包上传失败：服务器缺少临时目录');
            case UPLOAD_ERR_CANT_WRITE:
                return _t('升级包上传失败：无法写入服务器磁盘');
            case UPLOAD_ERR_EXTENSION:
                return _t('升级包上传失败：上传被扩展中止');
            default:
                return _t('升级包上传失败');
        }
    }
}
