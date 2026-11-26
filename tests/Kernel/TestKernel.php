<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:25:10 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Kernel;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Dmstr\ApiPlatformUtils\ApiPlatformUtilsBundle;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/**
 * Minimal kernel booting API Platform together with this bundle, so tests
 * can request real endpoints such as `/api/docs.jsonld`.
 *
 * API resources are read from {@see self::FIXTURES_DIR} only; add new
 * fixture resources there. There is no DoctrineBundle: the bundle's
 * services that need `doctrine.orm.entity_manager` get a plain ORM
 * EntityManager on an in-memory SQLite connection that is never opened.
 * Cache and logs live under sys_get_temp_dir(), never in the repository.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string FIXTURES_DIR = __DIR__ . '/../Fixtures/CustomAction';

    /**
     * Environment that sets `custom_action: true` in
     * `api_platform.defaults.extra_properties`, marking every operation
     * that does not override it. Boot it with
     * `bootKernel(['environment' => TestKernel::ENV_MARKER_DEFAULTS])`;
     * every other environment leaves the defaults alone.
     */
    public const string ENV_MARKER_DEFAULTS = 'marker_defaults';

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new ApiPlatformBundle();
        yield new ApiPlatformUtilsBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->getTempDir() . '/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getTempDir() . '/log';
    }

    /**
     * Factory for the `doctrine.orm.entity_manager` service. The connection
     * is lazy and no test is expected to open it; metadata lookups for
     * non-entity classes throw, which the bundle's decorators treat as
     * "not a Doctrine entity".
     */
    public static function createEntityManager(string $cacheDir): EntityManagerInterface
    {
        $paths = [self::FIXTURES_DIR];
        $config = method_exists(ORMSetup::class, 'createAttributeMetadataConfig')
            ? ORMSetup::createAttributeMetadataConfig($paths, true)
            : ORMSetup::createAttributeMetadataConfiguration($paths, true, $cacheDir . '/doctrine/proxies');
        // ORM >= 3.5 on PHP 8.4: without native lazy objects the ProxyFactory
        // needs LazyGhost from symfony/var-exporter < 8
        if (method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);

        return new EntityManager($connection, $config);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => '%env(APP_SECRET)%',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'serializer' => ['enabled' => true],
            'property_info' => ['enabled' => true],
            'validation' => ['enabled' => true],
        ]);

        $apiPlatform = [
            'title' => 'Test API',
            'version' => '1.0.0',
            'enable_swagger_ui' => false,
            'enable_re_doc' => false,
            'enable_docs' => true,
            'serializer' => ['hydra_prefix' => true],
            'mapping' => ['paths' => [self::FIXTURES_DIR]],
        ];
        if (self::ENV_MARKER_DEFAULTS === $this->environment) {
            $apiPlatform['defaults'] = ['extra_properties' => ['custom_action' => true]];
        }
        $container->extension('api_platform', $apiPlatform);

        $container->extension('dmstr_api_platform_utils', [
            'credential_encryption' => [
                // 32 bytes, test value only
                'key' => str_repeat('k', 32),
            ],
        ]);

        $container->services()
            ->set('doctrine.orm.entity_manager', EntityManagerInterface::class)
                ->factory([self::class, 'createEntityManager'])
                ->args([param('kernel.cache_dir')]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'api_platform')->prefix('/api');
    }

    /**
     * Overrides MicroKernelTrait::getConfigDir(): in debug mode FrameworkBundle
     * dumps `reference.php` into the config dir, which would otherwise be the
     * bundle's own `config/`. All configuration is inline above.
     */
    private function getConfigDir(): string
    {
        return $this->getTempDir() . '/config';
    }

    /**
     * One directory per checkout, so parallel worktrees do not share a
     * compiled container.
     */
    private function getTempDir(): string
    {
        return sys_get_temp_dir() . '/dmstr-api-platform-utils-bundle-' . md5($this->getProjectDir());
    }
}
