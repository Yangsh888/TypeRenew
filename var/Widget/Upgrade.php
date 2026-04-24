<?php

namespace Widget;

use Typecho\Common;
use Exception;
use Typecho\Upgrade\Runner as UpgradeRunner;
use Typecho\Upgrade\Store as UpgradeStore;
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
            return;
        }

        try {
            $store = new UpgradeStore();
            if ($store->readState() !== null) {
                (new UpgradeRunner($store))->clear();
            }
        } catch (\Throwable) {
        }

        Notice::alloc()->set($result['messages'], 'notice');
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
            $messages = [_t('数据库关键结构已同步')];

            if (($result['syncedComments'] ?? 0) > 0) {
                $messages[] = _t('已同步 %d 条历史评论的作者昵称', (int) $result['syncedComments']);
            }

            Notice::alloc()->set($messages, 'success');
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
