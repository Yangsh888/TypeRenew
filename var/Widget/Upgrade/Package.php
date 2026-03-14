<?php

namespace Widget\Upgrade;

use Typecho\Common;
use Typecho\Upgrade\Runner;
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
        $runner = new Runner();

        try {
            if ($do === 'upload') {
                $this->upload($runner);
            } elseif ($do === 'apply') {
                $this->apply($runner);
            } elseif ($do === 'clear') {
                $runner->clear();
                Notice::alloc()->set(_t('升级包已清理'), 'success');
            } else {
                Notice::alloc()->set(_t('未知升级操作'), 'error');
            }
        } catch (\Throwable $e) {
            Notice::alloc()->set($e->getMessage(), 'error');
        }

        $this->response->redirect(Common::url('upgrade.php', $this->options->adminUrl));
    }

    private function upload(Runner $runner): void
    {
        if (empty($_FILES)) {
            throw new \RuntimeException('请选择升级包');
        }

        $file = array_pop($_FILES);
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
}
