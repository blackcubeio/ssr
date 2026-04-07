<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Tests\Support;

use Blackcube\Injector\Injector as BlackcubeInjector;
use Psr\SimpleCache\CacheInterface;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\Migrator;
use Yiisoft\Db\Migration\Service\MigrationService;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Injector\Injector;

/**
 * Trait for Cest classes that need database setup.
 * Uses dcore migrations to create the schema.
 */
trait DatabaseCestTrait
{
    private const NAMESPACE = 'Blackcube\\Dcore\\Migrations';

    protected ConnectionInterface $db;
    protected Migrator $migrator;
    protected MigrationService $service;

    private static array $setupDone = [];

    public function _before(IntegrationTester $I): void
    {
        $this->initializeDatabase();

        $className = static::class;
        if (!isset(self::$setupDone[$className])) {
            $this->migrateDown();
            $this->migrateUp();
            self::$setupDone[$className] = true;
        }
    }

    private function initializeDatabase(): void
    {
        $helper = new MysqlHelper();
        $this->db = $helper->createConnection();

        ConnectionProvider::set($this->db);

        $containerConfig = ContainerConfig::create()
            ->withDefinitions([
                ConnectionInterface::class => $this->db,
                CacheInterface::class => new ArrayCache(),
                \Yiisoft\Cache\CacheInterface::class => new Cache(new ArrayCache()),
            ]);
        $container = new Container($containerConfig);

        BlackcubeInjector::init($container);

        $injector = new Injector($container);

        $this->migrator = new Migrator($this->db, new NullMigrationInformer());
        $this->service = new MigrationService($this->db, $injector, $this->migrator);
        $this->service->setSourceNamespaces([self::NAMESPACE]);
    }

    private function migrateDown(): void
    {
        $applied = array_keys($this->migrator->getHistory());
        if (empty($applied)) {
            return;
        }

        $this->db->createCommand('SET FOREIGN_KEY_CHECKS = 0')->execute();
        try {
            foreach ($applied as $class) {
                $this->migrator->down($this->service->makeMigration($class));
            }
        } finally {
            $this->db->createCommand('SET FOREIGN_KEY_CHECKS = 1')->execute();
        }
    }

    private function migrateUp(): void
    {
        $migrations = $this->service->getNewMigrations();
        foreach ($migrations as $class) {
            $this->migrator->up($this->service->makeMigration($class));
        }
    }
}
