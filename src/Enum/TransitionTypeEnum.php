<?php
declare(strict_types=1);

namespace KerrialNewham\Migrator\Enum;

enum TransitionTypeEnum: string
{
    case MIGRATION = 'migration';
    case UPGRADE = 'upgrade';
}
