<?php
namespace Mollie\Bundle\PaymentBundle\IntegrationServices;

use Mollie\Bundle\PaymentBundle\Entity\PaymentLinkMethod;
use Mollie\Bundle\PaymentBundle\Entity\Repository\MollieBaseEntityRepository;
use Mollie\Bundle\PaymentBundle\Entity\Repository\MollieContextAwareEntityRepository;
use Mollie\Bundle\PaymentBundle\Entity\Repository\MollieNotificationEntityRepository;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\Authorization\Interfaces\AuthorizationService;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\CustomerReference\Model\CustomerReference;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\Http\OrgToken\ProxyDataProvider;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\Integration\Interfaces\OrderLineTransitionService as OrderLineTransitionServiceInterface;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\Integration\Interfaces\OrderTransitionService as OrderTransitionServiceInterface;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\Notifications\Model\Notification;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\OrderReference\Model\OrderReference;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\PaymentMethod\Model\PaymentMethodConfig;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\VersionCheck\VersionCheckService;
use Mollie\Bundle\PaymentBundle\IntegrationCore\Infrastructure\Configuration\ConfigEntity;
use Mollie\Bundle\PaymentBundle\IntegrationCore\Infrastructure\Configuration\Configuration;
use Mollie\Bundle\PaymentBundle\IntegrationCore\Infrastructure\Http\CurlHttpClient;
use Mollie\Bundle\PaymentBundle\IntegrationCore\Infrastructure\Http\HttpClient;
use Mollie\Bundle\PaymentBundle\IntegrationCore\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use Mollie\Bundle\PaymentBundle\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Mollie\Bundle\PaymentBundle\IntegrationCore\Infrastructure\ServiceRegister;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BootstrapComponent
 *
 * @package Mollie\Bundle\PaymentBundle\IntegrationServices
 */
class BootstrapComponent extends \Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\BootstrapComponent
{
    /**
     * @var ContainerInterface
     */
    private static $container;
    /**
     * @var bool
     */
    private static $isInitialized = false;

    /**
     * @param ContainerInterface $container
     */
    public static function boot(ContainerInterface $container)
    {
        self::$container = $container;

        if (!self::$isInitialized) {
            parent::init();
            self::$isInitialized = true;
        }
    }
    /**
     * Initializes services and utilities.
     */
    protected static function initServices()
    {
        parent::initServices();

        ServiceRegister::registerService(ContainerInterface::class, function () {
            return self::$container;
        });

        ServiceRegister::registerService(
            Configuration::CLASS_NAME,
            function () {
                return static::$container->get(Configuration::class);
            }
        );

        ServiceRegister::registerService(
            ShopLoggerAdapter::CLASS_NAME,
            function () {
                return static::$container->get(ShopLoggerAdapter::class);
            }
        );

        ServiceRegister::registerService(
            HttpClient::CLASS_NAME,
            function () {
                return new CurlHttpClient();
            }
        );

        ServiceRegister::registerService(
            OrderTransitionServiceInterface::CLASS_NAME,
            function () {
                return self::$container->get(OrderTransitionService::class);
            }
        );

        ServiceRegister::registerService(
            OrderLineTransitionServiceInterface::CLASS_NAME,
            function () {
                return self::$container->get(OrderLineTransitionService::class);
            }
        );

        ServiceRegister::registerService(
            AuthorizationService::class,
            function () {
                return self::$container->get(AuthorizationService::class);
            }
        );

        ServiceRegister::registerService(
            ProxyDataProvider::class,
            function () {
                return self::$container->get(ProxyDataProvider::class);
            }
        );

        ServiceRegister::registerService(
            VersionCheckService::class,
            function () {
                return self::$container->get(VersionCheckService::class);
            }
        );
    }

    /**
     * {@inheritdoc}
     * @throws \Mollie\Bundle\PaymentBundle\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    protected static function initRepositories()
    {
        parent::initRepositories();

        RepositoryRegistry::registerRepository(ConfigEntity::getClassName(), MollieBaseEntityRepository::getClassName());
        RepositoryRegistry::registerRepository(Notification::getClassName(), MollieNotificationEntityRepository::getClassName());
        RepositoryRegistry::registerRepository(
            PaymentMethodConfig::getClassName(),
            MollieContextAwareEntityRepository::getClassName()
        );
        RepositoryRegistry::registerRepository(
            OrderReference::getClassName(),
            MollieBaseEntityRepository::getClassName()
        );
        RepositoryRegistry::registerRepository(
            PaymentLinkMethod::getClassName(),
            MollieBaseEntityRepository::getClassName()
        );
        RepositoryRegistry::registerRepository(
            CustomerReference::getClassName(),
            MollieBaseEntityRepository::getClassName()
        );
    }
}
