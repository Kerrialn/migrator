<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Data;

use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;

/**
 * Per-framework patterns that indicate tight coupling to that framework.
 * Used to score how hard a codebase is to migrate away from its current framework.
 */
final readonly class FrameworkCouplingSignatures
{
    /**
     * @return array<string, array{namespaces: string[], helpers: string[], facades: string[], baseClasses: string[], specificPackagePrefixes: string[]}>
     */
    public static function getSignatures(): array
    {
        return [
            FrameworkTypeEnum::LARAVEL->value => [
                'namespaces' => ['Illuminate\\', 'Laravel\\'],
                'helpers' => [
                    'app(', 'request(', 'response(', 'auth(', 'session(', 'view(',
                    'config(', 'route(', 'url(', 'abort(', 'back(', 'redirect(',
                    'dispatch(', 'cache(', 'event(', 'resolve(',
                ],
                'facades' => [
                    'Auth::', 'Cache::', 'Config::', 'DB::', 'Event::', 'File::', 'Hash::',
                    'Log::', 'Mail::', 'Notification::', 'Queue::', 'Route::', 'Schema::',
                    'Session::', 'Storage::', 'URL::', 'Validator::', 'View::',
                ],
                'baseClasses' => [
                    'extends Model', 'extends Controller', 'extends FormRequest',
                    'extends Middleware', 'extends Job', 'extends Mailable',
                    'extends Notification', 'extends Resource', 'extends Seeder',
                    'extends Migration',
                ],
                'specificPackagePrefixes' => [
                    'laravel/', 'illuminate/', 'spatie/laravel-', 'livewire/',
                    'barryvdh/laravel-', 'tymon/jwt-auth', 'filament/',
                ],
            ],
            FrameworkTypeEnum::SYMFONY->value => [
                'namespaces' => ['Symfony\\', 'Doctrine\\Bundle\\'],
                'helpers' => [],
                'facades' => [],
                'baseClasses' => [
                    'extends AbstractController', 'extends Controller',
                    'extends Command', 'extends AbstractType',
                    'extends WebTestCase', 'extends KernelTestCase',
                ],
                'specificPackagePrefixes' => [
                    'symfony/', 'doctrine/doctrine-bundle', 'doctrine/doctrine-migrations-bundle',
                    'easycorp/easyadmin-bundle', 'knplabs/', 'stof/',
                ],
            ],
            FrameworkTypeEnum::CODEIGNITER->value => [
                'namespaces' => ['CodeIgniter\\'],
                'helpers' => [
                    // CI3 & CI4 global helpers
                    'base_url(', 'site_url(', 'show_error(', 'show_404(', 'redirect(',
                    // CI3 $this-> patterns ubiquitous in controllers/models
                    '$this->load->', '$this->db->', '$this->input->',
                    '$this->session->', '$this->uri->', '$this->config->item(',
                    '$this->lang->', '$this->output->', '$this->cache->',
                    '$this->CI->', 'get_instance()',
                    // CI3 security header present in virtually every CI3 file
                    "defined('BASEPATH')", 'defined("BASEPATH")',
                ],
                'facades' => [],
                'baseClasses' => [
                    'extends BaseController', 'extends CI_Controller', 'extends CI_Model',
                    'extends CI_Migration', 'extends MY_Controller', 'extends MY_Model',
                ],
                'specificPackagePrefixes' => ['codeigniter/', 'codeigniter4/'],
            ],
            FrameworkTypeEnum::YII->value => [
                'namespaces' => ['yii\\'],
                'helpers' => ['Yii::$app', 'Yii::t(', 'Yii::getAlias('],
                'facades' => [],
                'baseClasses' => [
                    'extends Controller', 'extends ActiveRecord', 'extends Model',
                    'extends Widget', 'extends Component',
                ],
                'specificPackagePrefixes' => ['yiisoft/', 'yii2tech/'],
            ],
            FrameworkTypeEnum::CAKEPHP->value => [
                'namespaces' => ['Cake\\'],
                'helpers' => [],
                'facades' => [],
                'baseClasses' => [
                    'extends AppController', 'extends AppTable', 'extends AppEntity',
                    'extends Table', 'extends Entity',
                ],
                'specificPackagePrefixes' => ['cakephp/'],
            ],
            FrameworkTypeEnum::ZEND->value => [
                'namespaces' => ['Zend\\', 'Laminas\\'],
                'helpers' => [],
                'facades' => [],
                'baseClasses' => ['extends AbstractActionController', 'extends AbstractRestfulController'],
                'specificPackagePrefixes' => ['zendframework/', 'laminas/'],
            ],
            FrameworkTypeEnum::PHALCON->value => [
                'namespaces' => ['Phalcon\\'],
                'helpers' => ['$this->view->', '$this->request->', '$this->response->', '$this->router->', '$this->url->'],
                'facades' => [],
                'baseClasses' => [
                    'extends Controller',
                    'extends Model',
                    'extends Injectable',
                    'extends BaseModel',
                    'extends ResourceModel',
                    'extends BaseController',
                    'extends SecureController',
                    'extends Task',
                ],
                'specificPackagePrefixes' => ['phalcon/'],
            ],
            FrameworkTypeEnum::TEMPEST->value => [
                'namespaces' => ['Tempest\\'],
                'helpers' => [],
                'facades' => [],
                'baseClasses' => [],
                'specificPackagePrefixes' => ['tempest/'],
            ],
        ];
    }
}
