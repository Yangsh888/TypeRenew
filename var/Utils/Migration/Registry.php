<?php

namespace Utils\Migration;

use Utils\Migration\Steps\CoreIndexStep;
use Utils\Migration\Steps\InstallMailAndResetInfrastructureStep;
use Utils\Migration\Steps\NormalizeLegacyStorageStep;
use Utils\Migration\Steps\PasswordStorageStep;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Registry
{
    public static function all(): array
    {
        $steps = [
            new NormalizeLegacyStorageStep(),
            new InstallMailAndResetInfrastructureStep(),
            new CoreIndexStep(),
            new PasswordStorageStep()
        ];

        usort($steps, static function (StepInterface $left, StepInterface $right): int {
            return version_compare($left->version(), $right->version());
        });

        return $steps;
    }
}
