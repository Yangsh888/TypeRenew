<?php

namespace Widget;

use Typecho\Common;
use Exception;
use Typecho\Upgrade\Runner as UpgradeRunner;
use Utils\Migration\SchemaManager;
use Widget\Base\Options as BaseOptions;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Upgrade extends BaseOptions implements ActionInterface
{
    public function upgrade()
    {
        try {
            $activated = is_array($this->options->plugins['activated'] ?? null)
                ? array_keys($this->options->plugins['activated'])
                : [];
            $result = SchemaManager::syncCurrentRelease($this->db, $activated);
        } catch (Exception $e) {
            Notice::alloc()->set($e->getMessage(), 'error');
            $this->response->goBack();
        }

        try {
            (new UpgradeRunner())->clear();
        } catch (\Throwable) {
        }

        Notice::alloc()->set(
            empty($result['messages']) ? _t("升级已经完成") : $result['messages'],
            empty($result['messages']) ? 'success' : 'notice'
        );
    }

    public function repairCriticalSchema(): void
    {
        try {
            $result = SchemaManager::repairCriticalSchema($this->db);
        } catch (Exception $e) {
            Notice::alloc()->set($e->getMessage(), 'error');
            return;
        }

        if ($result['healthy']) {
            if (!empty($result['repaired'])) {
                $names = array_map(static fn(array $item): string => (string) ($item['label'] ?? ''), $result['repaired']);
                Notice::alloc()->set(
                    _t('数据库关键结构已修复：%s', implode('、', array_filter($names))),
                    'success'
                );
            } else {
                Notice::alloc()->set(_t('数据库关键结构已是最新状态'), 'success');
            }
        } else {
            $names = array_map(
                static fn(array $item): string => (string) ($item['label'] ?? ''),
                $result['after']['missing']
            );
            Notice::alloc()->set(
                _t('仍有关键结构异常：%s', implode('、', array_filter($names))),
                'error'
            );
        }
    }

    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();
        if ($this->request->isPost()) {
            if ('repairCriticalSchema' === $this->request->get('do')) {
                $this->repairCriticalSchema();
            } else {
                $this->upgrade();
            }
        }
        $this->response->redirect(Common::url('upgrade.php', $this->options->adminUrl));
    }
}
