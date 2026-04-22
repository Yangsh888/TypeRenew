<?php

namespace Widget;

use Typecho\Common;
use Exception;
use Typecho\Upgrade\Runner as UpgradeRunner;
use Widget\Base\Options as BaseOptions;
use Utils\Upgrade as UpgradeAction;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 升级组件
 *
 * @author qining
 * @category typecho
 * @package Widget
 */
class Upgrade extends BaseOptions implements ActionInterface
{
    /**
     * minimum supported version
     */
    public const MIN_VERSION = '1.1.0';

    /**
     * 执行升级程序
     *
     * @throws \Typecho\Db\Exception
     */
    public function upgrade()
    {
        $currentVersion = $this->options->version;

        if (version_compare($currentVersion, self::MIN_VERSION, '<')) {
            Notice::alloc()->set(
                _t('请先升级至版本 %s', self::MIN_VERSION),
                'error'
            );

            $this->response->goBack();
        }

        try {
            $result = UpgradeAction::runPendingMigrations($this->db, $currentVersion);
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
            $result = UpgradeAction::repairCriticalSchema($this->db, $this->options);
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
            $names = array_map(static function (array $item): string {
                $label = (string) ($item['label'] ?? '');
                if (($item['status'] ?? '') === 'missing_columns' && !empty($item['missingColumns'])) {
                    return $label . '（缺字段：' . implode(', ', (array) $item['missingColumns']) . '）';
                }

                if (($item['status'] ?? '') === 'missing_table') {
                    return $label . '（缺表）';
                }

                if (($item['status'] ?? '') === 'schema_mismatch') {
                    $parts = [];
                    if (!empty($item['missingIndexes'])) {
                        $parts[] = '缺索引：' . implode(', ', (array) $item['missingIndexes']);
                    }
                    if (!empty($item['typeMismatches'])) {
                        $parts[] = '类型不符：' . implode(', ', (array) $item['typeMismatches']);
                    }
                    if (isset($item['collationOk']) && !$item['collationOk']) {
                        $parts[] = '排序规则：' . (string) ($item['tableCollation'] ?? '');
                    }

                    if (!empty($parts)) {
                        return $label . '（' . implode('；', $parts) . '）';
                    }
                }

                return $label;
            }, $result['after']['missing']);
            Notice::alloc()->set(
                _t('仍有关键结构异常：%s', implode('、', array_filter($names))),
                'error'
            );
        }
    }

    /**
     * 处理升级请求
     *
     * @throws \Typecho\Db\Exception
     * @throws \Typecho\Widget\Exception
     */
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
