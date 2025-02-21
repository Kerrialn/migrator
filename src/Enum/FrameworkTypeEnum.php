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

    public static function getFrameworkOptions(): array
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


    public static function getTargetFrameworkVersion(FrameworkTypeEnum $frameworkTypeEnum, PhpVersionEnum $targetPhpVersionEnum): null|string
    {
        $compatibilityMap = [
            FrameworkTypeEnum::SYMFONY->value => [
                PhpVersionEnum::PHP_7_0->value => '3.*',
                PhpVersionEnum::PHP_7_1->value => '4.*',
                PhpVersionEnum::PHP_7_2->value => '4.*',
                PhpVersionEnum::PHP_7_3->value => '5.*',
                PhpVersionEnum::PHP_7_4->value => '5.*',
                PhpVersionEnum::PHP_8_0->value => '5.*',
                PhpVersionEnum::PHP_8_1->value => '6.*',
                PhpVersionEnum::PHP_8_2->value => '>=6.*',
                PhpVersionEnum::PHP_8_3->value => '>=7.*',
                PhpVersionEnum::PHP_8_4->value => '>=7.*',
            ],
            FrameworkTypeEnum::LARAVEL->value => [
                PhpVersionEnum::PHP_7_0->value => '5.4',
                PhpVersionEnum::PHP_7_1->value => '5.5',
                PhpVersionEnum::PHP_7_2->value => '5.6',
                PhpVersionEnum::PHP_7_3->value => '6.*',
                PhpVersionEnum::PHP_7_4->value => '7.*',
                PhpVersionEnum::PHP_8_0->value => '8.*',
                PhpVersionEnum::PHP_8_1->value => '9.*',
                PhpVersionEnum::PHP_8_2->value => '10.*',
                PhpVersionEnum::PHP_8_3->value => '10.*',
                PhpVersionEnum::PHP_8_4->value => '10.*',
            ],
            FrameworkTypeEnum::CODEIGNITER->value => [
                PhpVersionEnum::PHP_7_0->value => '3.*',
                PhpVersionEnum::PHP_7_1->value => '3.*',
                PhpVersionEnum::PHP_7_2->value => '3.*',
                PhpVersionEnum::PHP_7_3->value => '3.*',
                PhpVersionEnum::PHP_7_4->value => '3.*',
                PhpVersionEnum::PHP_8_0->value => '4.*',
                PhpVersionEnum::PHP_8_1->value => '4.*',
                PhpVersionEnum::PHP_8_2->value => '4.*',
                PhpVersionEnum::PHP_8_3->value => '4.*',
                PhpVersionEnum::PHP_8_4->value => '4.*',
            ],
            FrameworkTypeEnum::YII->value => [
                PhpVersionEnum::PHP_7_0->value => '2.0',
                PhpVersionEnum::PHP_7_1->value => '2.0',
                PhpVersionEnum::PHP_7_2->value => '2.0',
                PhpVersionEnum::PHP_7_3->value => '2.0',
                PhpVersionEnum::PHP_7_4->value => '2.0',
                PhpVersionEnum::PHP_8_0->value => '2.0',
                PhpVersionEnum::PHP_8_1->value => '2.0',
                PhpVersionEnum::PHP_8_2->value => '2.0',
                PhpVersionEnum::PHP_8_3->value => '2.0',
                PhpVersionEnum::PHP_8_4->value => '2.0',
            ],
            FrameworkTypeEnum::ZEND->value => [
                PhpVersionEnum::PHP_7_0->value => '3.*',
                PhpVersionEnum::PHP_7_1->value => '3.*',
                PhpVersionEnum::PHP_7_2->value => '3.*',
                PhpVersionEnum::PHP_7_3->value => '3.*',
                PhpVersionEnum::PHP_7_4->value => '3.*',
                PhpVersionEnum::PHP_8_0->value => '3.*',
                PhpVersionEnum::PHP_8_1->value => '3.*',
                PhpVersionEnum::PHP_8_2->value => '3.*',
                PhpVersionEnum::PHP_8_3->value => '3.*',
                PhpVersionEnum::PHP_8_4->value => '3.*',
            ],
            FrameworkTypeEnum::CAKEPHP->value => [
                PhpVersionEnum::PHP_7_0->value => '3.*',
                PhpVersionEnum::PHP_7_1->value => '3.*',
                PhpVersionEnum::PHP_7_2->value => '3.*',
                PhpVersionEnum::PHP_7_3->value => '3.*',
                PhpVersionEnum::PHP_7_4->value => '4.*',
                PhpVersionEnum::PHP_8_0->value => '4.*',
                PhpVersionEnum::PHP_8_1->value => '4.*',
                PhpVersionEnum::PHP_8_2->value => '4.*',
                PhpVersionEnum::PHP_8_3->value => '4.*',
                PhpVersionEnum::PHP_8_4->value => '4.*',
            ],
            FrameworkTypeEnum::PHALCON->value => [
                PhpVersionEnum::PHP_7_0->value => '3.*',
                PhpVersionEnum::PHP_7_1->value => '3.*',
                PhpVersionEnum::PHP_7_2->value => '3.*',
                PhpVersionEnum::PHP_7_3->value => '3.*',
                PhpVersionEnum::PHP_7_4->value => '4.*',
                PhpVersionEnum::PHP_8_0->value => '4.*',
                PhpVersionEnum::PHP_8_1->value => '5.*',
                PhpVersionEnum::PHP_8_2->value => '5.*',
                PhpVersionEnum::PHP_8_3->value => '5.*',
                PhpVersionEnum::PHP_8_4->value => '5.*',
            ],
        ];

        return $compatibilityMap[$frameworkTypeEnum->value][$targetPhpVersionEnum->value];
    }


}
