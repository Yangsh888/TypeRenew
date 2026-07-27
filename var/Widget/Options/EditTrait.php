<?php

namespace Widget\Options;

trait EditTrait
{
    protected function isEnableByCheckbox($settings, string $name): int
    {
        return is_array($settings) && in_array($name, $settings) ? 1 : 0;
    }

    protected function collectEnabledKeys(object $options, array $keys): array
    {
        $enabled = [];

        foreach ($keys as $key) {
            if (!empty($options->{$key})) {
                $enabled[] = $key;
            }
        }

        return $enabled;
    }

    /**
     * 密码类字段三态解析: 勾选清空 > 填了新值 > 留空沿用旧值
     *
     * 密码框永远以空值渲染(不回显密文), 所以"留空"天然无法区分"不改"和"清空",
     * 必须由配套的清空复选框表达清空意图, 否则密码将永远无法删除
     *
     * @param string $submitted 表单提交的新密码
     * @param bool   $clear     是否勾选了清空
     * @param string $current   当前已存储的明文
     * @return string 最终的明文值, 调用方负责加密
     */
    protected function resolveSecret(string $submitted, bool $clear, string $current): string
    {
        if ($clear) {
            return '';
        }

        $value = trim($submitted);
        return $value === '' ? $current : $value;
    }
}
