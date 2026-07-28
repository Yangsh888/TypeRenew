<?php

namespace Widget\Options;

trait EditTrait
{
    public function execute()
    {
        $this->user->pass('administrator');
    }

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

    protected function resolveSecret(string $submitted, bool $clear, string $current): string
    {
        if ($clear) {
            return '';
        }

        $value = trim($submitted);
        return $value === '' ? $current : $value;
    }
}
