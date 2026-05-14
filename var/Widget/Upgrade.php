<?php

namespace Widget;

use Typecho\Common;
use Typecho\Upgrade\Runner as UpgradeRunner;
use Typecho\Upgrade\Store as UpgradeStore;
use Utils\Migration\SchemaManager;
use Widget\Base\Options as BaseOptions;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Upgrade extends BaseOptions implements ActionInterface
{
    private function guardMysqlUpgradeRisks(bool $forRepair = false): bool
    {
        $status = SchemaManager::inspectMysqlUpgradeRisks($this->db);
        if (!(bool) ($status['supported'] ?? false) || (bool) ($status['healthy'] ?? true)) {
            return true;
        }

        $count = count(array_filter(
            (array) ($status['items'] ?? []),
            static function (array $item) use ($forRepair): bool {
                if ((string) ($item['status'] ?? 'ok') !== 'blocking') {
                    return false;
                }

                if (!$forRepair) {
                    return true;
                }

                return (string) ($item['key'] ?? '') !== 'users_mail_duplicates';
            }
        ));
        if ($count === 0) {
            return true;
        }
        Notice::alloc()->set(
            _t('检测到 %d 项 MySQL 风险，请先在升级页查看数据库诊断并处理后再继续。', $count),
            'error'
        );
        return false;
    }

    public function upgrade()
    {
        if (!$this->guardMysqlUpgradeRisks()) {
            return;
        }

        try {
            $activated = is_array($this->options->plugins['activated'] ?? null)
                ? array_keys($this->options->plugins['activated'])
                : [];
            $result = SchemaManager::syncCurrentRelease($this->db, $activated);
        } catch (\Throwable $e) {
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
        if (!$this->guardMysqlUpgradeRisks(true)) {
            return;
        }

        try {
            $result = SchemaManager::repairCriticalSchema($this->db);
        } catch (\Throwable $e) {
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
        if (!$this->request->isPost()) {
            $this->response->setStatus(405)->throwContent(_t('Method Not Allowed'), 'text/plain');
            return;
        }
        $this->security->protect();
        $action = (string) $this->request->getAction();
        if ('upgrade' === $action) {
            $this->upgrade();
        } elseif ('repairCriticalSchema' === $action) {
            $this->repairCriticalSchema();
        } else {
            Notice::alloc()->set(_t('未知升级操作'), 'error');
        }
        $this->response->redirect(Common::url('upgrade.php', $this->options->adminUrl));
    }
}
