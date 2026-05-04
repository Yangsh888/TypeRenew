<?php

namespace Widget\XmlRpc;

use Typecho\Timezone as SiteTimezone;

class OptionsHandler extends AbstractHandler
{
    public function wpGetOptions(int $blogId, string $userName, string $password, array $options = []): array
    {
        $definitions = $this->wpOptions();
        $widgetOptions = $this->xmlRpc->optionsWidget();
        $struct = [];

        if (empty($options)) {
            $options = array_keys($definitions);
        }

        foreach ($options as $option) {
            if (isset($definitions[$option])) {
                $struct[$option] = $definitions[$option];
                if (isset($struct[$option]['option'])) {
                    $struct[$option]['value'] = $this->readOptionValue($widgetOptions, $option, $struct[$option]);
                    unset($struct[$option]['option']);
                }
            }
        }

        return $struct;
    }

    public function wpSetOptions(int $blogId, string $userName, string $password, array $options = []): array
    {
        $definitions = $this->wpOptions();
        $widgetOptions = $this->xmlRpc->optionsWidget();
        $db = $this->xmlRpc->db();
        $struct = [];

        foreach ($options as $option => $value) {
            if (!isset($definitions[$option])) {
                continue;
            }

            $struct[$option] = $definitions[$option];
            if (isset($struct[$option]['option'])) {
                $struct[$option]['value'] = $this->readOptionValue($widgetOptions, $option, $struct[$option]);
                unset($struct[$option]['option']);
            }

            if ($definitions[$option]['readonly'] || !isset($definitions[$option]['option'])) {
                continue;
            }

            if ($option === 'time_zone') {
                $name = SiteTimezone::resolve(
                    (string) $value,
                    $widgetOptions->timezone
                );
                $offset = (string) SiteTimezone::offsetFromName($name);
                $this->upsertOption('timezoneName', $name);
                $this->upsertOption('timezone', $offset);
                $struct[$option]['value'] = $name;
                continue;
            }

            if (
                $db->query($db->update('table.options')
                    ->rows(['value' => $value])
                    ->where('name = ?', $definitions[$option]['option'])) > 0
            ) {
                $struct[$option]['value'] = $value;
            }
        }

        return $struct;
    }

    private function wpOptions(): array
    {
        $options = $this->xmlRpc->optionsWidget();

        return [
            'software_name' => [
                'desc' => _t('软件名称'),
                'readonly' => true,
                'value' => $options->software
            ],
            'software_version' => [
                'desc' => _t('软件版本'),
                'readonly' => true,
                'value' => $options->version
            ],
            'blog_url' => [
                'desc' => _t('博客地址'),
                'readonly' => true,
                'option' => 'siteUrl'
            ],
            'home_url' => [
                'desc' => _t('博客首页地址'),
                'readonly' => true,
                'option' => 'siteUrl'
            ],
            'login_url' => [
                'desc' => _t('登录地址'),
                'readonly' => true,
                'value' => $options->loginUrl
            ],
            'admin_url' => [
                'desc' => _t('管理区域的地址'),
                'readonly' => true,
                'value' => $options->adminUrl
            ],
            'post_thumbnail' => [
                'desc' => _t('文章缩略图'),
                'readonly' => true,
                'value' => false
            ],
            'time_zone' => [
                'desc' => _t('时区'),
                'readonly' => false,
                'option' => 'timezoneName'
            ],
            'blog_title' => [
                'desc' => _t('博客标题'),
                'readonly' => false,
                'option' => 'title'
            ],
            'blog_tagline' => [
                'desc' => _t('博客关键字'),
                'readonly' => false,
                'option' => 'description'
            ],
            'date_format' => [
                'desc' => _t('日期格式'),
                'readonly' => false,
                'option' => 'postDateFormat'
            ],
            'time_format' => [
                'desc' => _t('时间格式'),
                'readonly' => true,
                'value' => 'H:i:s'
            ],
            'users_can_register' => [
                'desc' => _t('是否允许注册'),
                'readonly' => false,
                'option' => 'allowRegister'
            ]
        ];
    }

    private function readOptionValue($widgetOptions, string $option, array $definition)
    {
        return $option === 'time_zone'
            ? $widgetOptions->timezoneName
            : $widgetOptions->{$definition['option']};
    }

    private function upsertOption(string $name, string $value): void
    {
        if ($this->getStoredOptionValue($name) === $value) {
            return;
        }

        $updated = $this->xmlRpc->db()->query(
            $this->xmlRpc->db()->update('table.options')
                ->rows(['value' => $value])
                ->where('name = ? AND user = ?', $name, 0)
        );

        if ($updated > 0) {
            return;
        }

        $this->xmlRpc->db()->query(
            $this->xmlRpc->db()->insert('table.options')->rows([
                'name' => $name,
                'user' => 0,
                'value' => $value,
            ])
        );
    }

    private function getStoredOptionValue(string $name): ?string
    {
        $row = $this->xmlRpc->db()->fetchRow(
            $this->xmlRpc->db()->select('value')
                ->from('table.options')
                ->where('name = ? AND user = ?', $name, 0)
                ->limit(1)
        );

        return isset($row['value']) ? (string) $row['value'] : null;
    }
}
