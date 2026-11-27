<?php

declare(strict_types=1);

use Patylook\Contracts\AdminBootstrapInterface;
use Patylook\Contracts\AuthServiceInterface;
use Patylook\Contracts\BrandRepositoryInterface;
use Patylook\Contracts\BrandServiceInterface;
use Patylook\Contracts\ClockInterface;
use Patylook\Contracts\CustomerRepositoryInterface;
use Patylook\Contracts\CustomerServiceInterface;
use Patylook\Contracts\DashboardServiceInterface;
use Patylook\Contracts\ImageProcessorInterface;
use Patylook\Contracts\ImageStorageInterface;
use Patylook\Contracts\LoggerInterface;
use Patylook\Contracts\LoginThrottleInterface;
use Patylook\Contracts\OrderRepositoryInterface;
use Patylook\Contracts\OrderServiceInterface;
use Patylook\Contracts\PasswordHasherInterface;
use Patylook\Contracts\PaymentTypeRepositoryInterface;
use Patylook\Contracts\PaymentTypeServiceInterface;
use Patylook\Contracts\PricebookRepositoryInterface;
use Patylook\Contracts\PricebookServiceInterface;
use Patylook\Contracts\ProductCategoryRepositoryInterface;
use Patylook\Contracts\ProductCategoryServiceInterface;
use Patylook\Contracts\ProductImageRepositoryInterface;
use Patylook\Contracts\ProductImageServiceInterface;
use Patylook\Contracts\ProductRepositoryInterface;
use Patylook\Contracts\ProductServiceInterface;
use Patylook\Contracts\SessionStoreInterface;
use Patylook\Contracts\MainSellerRepositoryInterface;
use Patylook\Contracts\OrderBannerRendererInterface;
use Patylook\Contracts\OrderBannerServiceInterface;
use Patylook\Contracts\ShowcaseServiceInterface;
use Patylook\Contracts\TransactionManagerInterface;
use Patylook\Contracts\UserRepositoryInterface;
use Patylook\Contracts\UserServiceInterface;
use Patylook\Core\Application;
use Patylook\Core\Config;
use Patylook\Core\Container;
use Patylook\Core\ExceptionHandlerInterface;
use Patylook\Core\Formatter;
use Patylook\Core\Pipeline;
use Patylook\Core\Router;
use Patylook\Core\View;
use Patylook\Http\ExceptionHandler;
use Patylook\Http\SecurityHeaders;
use Patylook\Http\SessionCookie;
use Patylook\Infrastructure\BcryptHasher;
use Patylook\Infrastructure\Database\Database;
use Patylook\Infrastructure\Database\PdoTransactionManager;
use Patylook\Infrastructure\FileLogger;
use Patylook\Infrastructure\FileLoginThrottle;
use Patylook\Infrastructure\FileSessionStore;
use Patylook\Infrastructure\GdImageProcessor;
use Patylook\Infrastructure\GdOrderBannerRenderer;
use Patylook\Infrastructure\LocalImageStorage;
use Patylook\Infrastructure\SystemClock;
use Patylook\Middleware\EnsureReady;
use Patylook\Middleware\RequireAuth;
use Patylook\Middleware\SameOrigin;
use Patylook\Repositories\Mysql\MysqlBrandRepository;
use Patylook\Repositories\Mysql\MysqlCustomerRepository;
use Patylook\Repositories\Mysql\MysqlOrderRepository;
use Patylook\Repositories\Mysql\MysqlPaymentTypeRepository;
use Patylook\Repositories\Mysql\MysqlPricebookRepository;
use Patylook\Repositories\Mysql\MysqlProductCategoryRepository;
use Patylook\Repositories\Mysql\MysqlProductImageRepository;
use Patylook\Repositories\Mysql\MysqlProductRepository;
use Patylook\Repositories\Mysql\MysqlUserRepository;
use Patylook\Services\AuthService;
use Patylook\Services\BrandService;
use Patylook\Services\CustomerService;
use Patylook\Services\DashboardService;
use Patylook\Services\OrderService;
use Patylook\Services\PaymentTypeService;
use Patylook\Services\PricebookService;
use Patylook\Services\ProductCategoryService;
use Patylook\Services\ProductImageService;
use Patylook\Services\ProductService;
use Patylook\Services\SessionGarbageCollector;
use Patylook\Repositories\Mysql\MysqlMainSellerRepository;
use Patylook\Services\OrderBannerService;
use Patylook\Services\ShowcaseService;
use Patylook\Services\Support\AdminRule;
use Patylook\Services\UserService;

/**
 * The composition root: says which implementation answers each interface. Everything else is
 * built by the container from constructor type hints.
 */
return static function (Container $c, Config $config): void {
    $c->instance(Config::class, $config);
    $c->instance(DateTimeZone::class, $config->timezone);

    // Infrastructure
    $c->singleton(ClockInterface::class, SystemClock::class);
    $c->singleton(LoggerInterface::class, fn (): LoggerInterface => new FileLogger(
        $config->storagePath('logs') . DIRECTORY_SEPARATOR . 'app.log',
        $config->isProduction(),
        $config->timezone,
        $config->isProduction() ? 'info' : 'debug',
    ));
    $c->singleton(Database::class, fn (): Database => new Database(
        $config->dbHost,
        $config->dbPort,
        $config->dbUser,
        $config->dbPassword,
        $config->dbName,
        $config->timezone,
        $config->dbTls,
    ));
    $c->singleton(TransactionManagerInterface::class, PdoTransactionManager::class);
    $c->singleton(PasswordHasherInterface::class, BcryptHasher::class);
    $c->singleton(SessionStoreInterface::class, fn (): SessionStoreInterface => new FileSessionStore(
        $config->storagePath('sessions'),
        $config->timezone,
    ));
    $c->singleton(LoginThrottleInterface::class, fn (): LoginThrottleInterface => new FileLoginThrottle(
        $config->storagePath('throttle'),
        5,
        15 * 60,
    ));
    $c->singleton(ImageStorageInterface::class, fn (): ImageStorageInterface => new LocalImageStorage($config->imagesDir));
    $c->singleton(ImageProcessorInterface::class, GdImageProcessor::class);

    // Repositories
    $c->singleton(UserRepositoryInterface::class, MysqlUserRepository::class);
    $c->singleton(ProductCategoryRepositoryInterface::class, MysqlProductCategoryRepository::class);
    $c->singleton(BrandRepositoryInterface::class, MysqlBrandRepository::class);
    $c->singleton(ProductRepositoryInterface::class, MysqlProductRepository::class);
    $c->singleton(PricebookRepositoryInterface::class, MysqlPricebookRepository::class);
    $c->singleton(CustomerRepositoryInterface::class, MysqlCustomerRepository::class);
    $c->singleton(PaymentTypeRepositoryInterface::class, MysqlPaymentTypeRepository::class);
    $c->singleton(OrderRepositoryInterface::class, MysqlOrderRepository::class);
    $c->singleton(ProductImageRepositoryInterface::class, MysqlProductImageRepository::class);

    // Services
    $c->instance(AdminRule::class, new AdminRule($config->adminCategory));
    $c->singleton(AuthServiceInterface::class, fn (Container $c): AuthServiceInterface => new AuthService(
        $c->get(UserRepositoryInterface::class),
        $c->get(SessionStoreInterface::class),
        $c->get(PasswordHasherInterface::class),
        $c->get(LoginThrottleInterface::class),
        $c->get(AdminRule::class),
        $c->get(ClockInterface::class),
        $config->sessionTtl,
    ));
    $c->singleton(UserService::class);
    $c->singleton(UserServiceInterface::class, fn (Container $c): UserServiceInterface => $c->get(UserService::class));
    $c->singleton(AdminBootstrapInterface::class, fn (Container $c): AdminBootstrapInterface => $c->get(UserService::class));
    $c->singleton(ProductCategoryServiceInterface::class, ProductCategoryService::class);
    $c->singleton(BrandServiceInterface::class, BrandService::class);
    $c->singleton(ProductServiceInterface::class, ProductService::class);
    $c->singleton(PricebookServiceInterface::class, PricebookService::class);
    $c->singleton(ProductImageServiceInterface::class, ProductImageService::class);
    $c->singleton(MainSellerRepositoryInterface::class, MysqlMainSellerRepository::class);
    $c->singleton(OrderBannerRendererInterface::class, fn (): OrderBannerRendererInterface => new GdOrderBannerRenderer(
        $config->basePath . '/resources/fonts',
        $config->basePath . '/public/static/img/logo.png',
    ));
    $c->singleton(OrderBannerServiceInterface::class, OrderBannerService::class);
    $c->singleton(ShowcaseServiceInterface::class, ShowcaseService::class);
    $c->singleton(CustomerServiceInterface::class, CustomerService::class);
    $c->singleton(PaymentTypeServiceInterface::class, PaymentTypeService::class);
    $c->singleton(OrderServiceInterface::class, OrderService::class);
    $c->singleton(DashboardServiceInterface::class, DashboardService::class);
    $c->singleton(SessionGarbageCollector::class);

    // Web
    $c->singleton(Formatter::class);
    $c->singleton(View::class, fn (Container $c): View => new View(
        $config->basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views',
        $c->get(Formatter::class),
    ));
    $c->singleton(SessionCookie::class, fn (): SessionCookie => new SessionCookie($config->cookieSecure));
    $c->singleton(SecurityHeaders::class, fn (): SecurityHeaders => new SecurityHeaders($config->cookieSecure));
    $c->singleton(ExceptionHandlerInterface::class, ExceptionHandler::class);

    // The front controller's kernel
    $c->singleton(Router::class, function () use ($config): Router {
        $router = new Router();
        (require $config->basePath . '/config/routes.php')($router);
        return $router;
    });
    $c->singleton(Application::class, fn (Container $c): Application => new Application(
        $c,
        $c->get(Router::class),
        new Pipeline($c),
        $c->get(ExceptionHandlerInterface::class),
        $c->get(LoggerInterface::class),
        [SameOrigin::class, EnsureReady::class],
        [$c->get(SecurityHeaders::class)],
        [$c->get(SessionGarbageCollector::class)],
        [RequireAuth::class],
    ));
};
