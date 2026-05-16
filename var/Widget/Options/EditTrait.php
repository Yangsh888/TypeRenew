<?php

namespace Widget\Options;

trait EditTrait
{
    /**
     * 以checkbox选项判断是否某个值被启用
     */
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
}
