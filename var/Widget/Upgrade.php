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
    private function blockingMysqlRisks(): array
    {
        $status = SchemaManager::inspectMysqlUpgradeRisks($this->db);
        if (empty($status['supported'])) {
            return [];
        }

        return array_values(array_filter(
            (array) ($status['items'] ?? []),
            static function (array $item): bool {
                if ((string) ($item['status'] ?? 'ok') === 'ok') {
                    return false;
                }

                return in_array((string) ($item['key'] ?? ''), ['mysql_version', 'legacy_index_limit', 'mail_unsub_duplicates'], true);
            }
        ));
    }

    private function assertMysqlSchemaActionAllowed(string $actionLabel): void
    {
        $blocking = $this->blockingMysqlRisks();
        if ($blocking === []) {
            return;
        }

        $labels = array_values(array_filter(array_map(
            static fn(array $item): string => (string) ($item['label'] ?? ''),
            $blocking
        )));

        throw new \RuntimeException(
            _t(
                '当前数据库环境仍有未处理风险，暂不能执行%s：%s。请先在升级页查看数据库诊断并处理后重试。',
                $actionLabel,
                implode('、', $labels)
            )
        );
    }

    public function upgrade()
    {
        try {
            $this->assertMysqlSchemaActionAllowed(_t('数据库升级'));
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
        try {
            $this->assertMysqlSchemaActionAllowed(_t('关键数据库结构修复'));
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
        $this->security->protect();
        if ($this->request->isPost()) {
            $action = (string) $this->request->get('do');

            if ($action === '' || $action === 'upgrade') {
                $this->upgrade();
            } elseif ($action === 'repairCriticalSchema') {
                $this->repairCriticalSchema();
            } else {
                Notice::alloc()->set(_t('未知升级操作'), 'error');
            }
        }
        $this->response->redirect(Common::url('upgrade.php', $this->options->adminUrl));
    }
}
