<?php

namespace KerrialNewham\Migrator\Data;

use Doctrine\Common\Collections\ArrayCollection;
use KerrialNewham\Migrator\DataValueObject\FrameworkPackage;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;

final readonly class Frameworks
{
    /**
     * @return ArrayCollection<int, FrameworkPackage>
     */
    public static function getFrameworks(): ArrayCollection
    {
        return new ArrayCollection([
            new FrameworkPackage(type: FrameworkTypeEnum::SYMFONY, name: 'symfony/framework-bundle', isPrimary: true, weight: 50, frameworkPackages: new ArrayCollection([
                new FrameworkPackage(type: FrameworkTypeEnum::SYMFONY, name: 'symfony/console', isPrimary: false, weight: 40, frameworkPackages: new ArrayCollection()),
                new FrameworkPackage(type: FrameworkTypeEnum::SYMFONY, name: 'symfony/http-kernel', isPrimary: false, weight: 25, frameworkPackages: new ArrayCollection()),
                new FrameworkPackage(type: FrameworkTypeEnum::SYMFONY, name: 'symfony/dependency-injection', isPrimary: false, weight: 25, frameworkPackages: new ArrayCollection()),
            ])),
            new FrameworkPackage(type: FrameworkTypeEnum::LARAVEL, name: 'laravel/framework', isPrimary: true, weight: 50, frameworkPackages: new ArrayCollection([
                new FrameworkPackage(type: FrameworkTypeEnum::LARAVEL, name: 'illuminate/database', isPrimary: false, weight: 40, frameworkPackages: new ArrayCollection()),
                new FrameworkPackage(type: FrameworkTypeEnum::LARAVEL, name: 'illuminate/support', isPrimary: false, weight: 25, frameworkPackages: new ArrayCollection()),
                new FrameworkPackage(type: FrameworkTypeEnum::LARAVEL, name: 'illuminate/routing', isPrimary: false, weight: 25, frameworkPackages: new ArrayCollection()),
            ])),
            new FrameworkPackage(type: FrameworkTypeEnum::CODEIGNITER, name: 'codeigniter/framework', isPrimary: true, weight: 50, frameworkPackages: new ArrayCollection([
                new FrameworkPackage(type: FrameworkTypeEnum::CODEIGNITER, name: 'codeigniter4/framework', isPrimary: false, weight: 40, frameworkPackages: new ArrayCollection()),
            ])),
            new FrameworkPackage(type: FrameworkTypeEnum::YII, name: 'yiisoft/yii2', isPrimary: true, weight: 50, frameworkPackages: new ArrayCollection([
                new FrameworkPackage(type: FrameworkTypeEnum::YII, name: 'yiisoft/yii2-bootstrap4', isPrimary: false, weight: 40, frameworkPackages: new ArrayCollection()),
            ])),
            new FrameworkPackage(type: FrameworkTypeEnum::ZEND, name: 'zendframework/zend-mvc', isPrimary: true, weight: 50, frameworkPackages: new ArrayCollection()),
            new FrameworkPackage(type: FrameworkTypeEnum::CAKEPHP, name: 'cakephp/cakephp', isPrimary: true, weight: 50, frameworkPackages: new ArrayCollection([
                new FrameworkPackage(type: FrameworkTypeEnum::CAKEPHP, name: 'cakephp/orm', isPrimary: false, weight: 40, frameworkPackages: new ArrayCollection()),
                new FrameworkPackage(type: FrameworkTypeEnum::CAKEPHP, name: 'cakephp/bake', isPrimary: false, weight: 25, frameworkPackages: new ArrayCollection()),
            ])),
            new FrameworkPackage(type: FrameworkTypeEnum::PHALCON, name: 'phalcon/phalcon', isPrimary: true, weight: 50, frameworkPackages: new ArrayCollection([
                new FrameworkPackage(type: FrameworkTypeEnum::PHALCON, name: 'phalcon/devtools', isPrimary: false, weight: 40, frameworkPackages: new ArrayCollection()),
            ])),
        ]);
    }

}
