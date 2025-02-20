<?php

namespace KerrialNewham\Migrator\Enum;

enum FrameworkTypeEnum: string
{
    case SYMFONY = 'symfony';
    case LARAVEL = 'laravel';
    case TEMPEST = 'tempest';
    case CODEIGNITER = 'codeigniter';
    case YII = 'yii';
    case ZEND = 'zend';
    case CAKEPHP = 'cakephp';
    case PHALCON = 'phalcon';
    case NONE = 'none';

    public static function getFrameworkOptions() :array
    {
        return [
            self::SYMFONY->name => self::SYMFONY->value,
            self::LARAVEL->name => self::LARAVEL->value,
            self::CODEIGNITER->name => self::CODEIGNITER->value,
            self::YII->name => self::YII->value,
            self::ZEND->name => self::ZEND->value,
            self::CAKEPHP->name => self::CAKEPHP->value,
            self::PHALCON->name => self::PHALCON->value,
            self::NONE->name => self::NONE->value,
        ];
    }

}
