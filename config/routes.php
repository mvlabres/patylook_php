<?php

declare(strict_types=1);

use Patylook\Controllers\AuthController;
use Patylook\Controllers\BrandController;
use Patylook\Controllers\CustomerController;
use Patylook\Controllers\HealthController;
use Patylook\Controllers\OrderController;
use Patylook\Controllers\PaymentTypeController;
use Patylook\Controllers\PricebookController;
use Patylook\Controllers\ProductCategoryController;
use Patylook\Controllers\ProductController;
use Patylook\Controllers\ProductImageController;
use Patylook\Controllers\UserController;
use Patylook\Core\Router;
use Patylook\Middleware\LimitBody;
use Patylook\Middleware\RequireAdmin;
use Patylook\Middleware\RequireAuth;

/**
 * The URL map. Each route names the controller method that answers it; the groups say which
 * middleware wraps them. Routes not listed here answer 404.
 */
return static function (Router $router): void {
    // Public
    $router->get('/healthz', [HealthController::class, 'check']);
    $router->get('/login', [AuthController::class, 'loginForm']);
    $router->post('/login', [AuthController::class, 'loginSubmit']);

    // Uploads: the body size is capped before anything reads it.
    $router->group([LimitBody::class, RequireAuth::class], static function (Router $r): void {
        $r->post('/products', [ProductController::class, 'store']);
        $r->post('/products/{id}/images', [ProductImageController::class, 'upload']);
    });

    // Authenticated
    $router->group([RequireAuth::class], static function (Router $r): void {
        $r->post('/logout', [AuthController::class, 'logout']);
        $r->get('/', [AuthController::class, 'dashboard']);

        $r->get('/products', [ProductController::class, 'index']);
        $r->get('/products/new', [ProductController::class, 'create']);
        $r->get('/products/{id}/edit', [ProductController::class, 'edit']);
        $r->post('/products/{id}', [ProductController::class, 'update']);
        $r->post('/products/{id}/active', [ProductController::class, 'setActive']);
        $r->get('/products/{id}/prices', [PricebookController::class, 'index']);
        $r->post('/products/{id}/prices', [PricebookController::class, 'store']);
        $r->post('/products/{id}/images/{imageId}/delete', [ProductImageController::class, 'delete']);
        $r->get('/images/products/{folder}/{file}', [ProductImageController::class, 'serve']);
        $r->get('/showcase', [ProductImageController::class, 'showcase']);

        $r->get('/categories', [ProductCategoryController::class, 'index']);
        $r->get('/categories/new', [ProductCategoryController::class, 'create']);
        $r->post('/categories', [ProductCategoryController::class, 'store']);
        $r->get('/categories/{id}/edit', [ProductCategoryController::class, 'edit']);
        $r->post('/categories/{id}', [ProductCategoryController::class, 'update']);
        $r->post('/categories/{id}/delete', [ProductCategoryController::class, 'delete']);

        $r->get('/brands', [BrandController::class, 'index']);
        $r->get('/brands/new', [BrandController::class, 'create']);
        $r->post('/brands', [BrandController::class, 'store']);
        $r->get('/brands/{id}/edit', [BrandController::class, 'edit']);
        $r->post('/brands/{id}', [BrandController::class, 'update']);
        $r->post('/brands/{id}/active', [BrandController::class, 'setActive']);

        $r->get('/customers', [CustomerController::class, 'index']);
        $r->get('/customers/new', [CustomerController::class, 'create']);
        $r->post('/customers', [CustomerController::class, 'store']);
        $r->get('/customers/{id}/edit', [CustomerController::class, 'edit']);
        $r->post('/customers/{id}', [CustomerController::class, 'update']);
        $r->post('/customers/{id}/active', [CustomerController::class, 'setActive']);

        $r->get('/payment-types', [PaymentTypeController::class, 'index']);
        $r->get('/payment-types/new', [PaymentTypeController::class, 'create']);
        $r->post('/payment-types', [PaymentTypeController::class, 'store']);
        $r->get('/payment-types/{id}/edit', [PaymentTypeController::class, 'edit']);
        $r->post('/payment-types/{id}', [PaymentTypeController::class, 'update']);
        $r->post('/payment-types/{id}/delete', [PaymentTypeController::class, 'delete']);

        $r->get('/orders', [OrderController::class, 'index']);
        $r->get('/orders/new', [OrderController::class, 'create']);
        $r->post('/orders', [OrderController::class, 'store']);
        $r->get('/orders/{id}', [OrderController::class, 'show']);
        $r->post('/orders/{id}/status', [OrderController::class, 'changeStatus']);

        $r->get('/profile', [AuthController::class, 'profile']);
        $r->post('/profile/password', [AuthController::class, 'changePassword']);
    });

    // Administrators only
    $router->group([RequireAuth::class, RequireAdmin::class], static function (Router $r): void {
        $r->get('/users', [UserController::class, 'index']);
        $r->get('/users/new', [UserController::class, 'create']);
        $r->post('/users', [UserController::class, 'store']);
        $r->get('/users/{id}/edit', [UserController::class, 'edit']);
        $r->post('/users/{id}', [UserController::class, 'update']);
        $r->post('/users/{id}/active', [UserController::class, 'setActive']);
        $r->post('/users/{id}/password', [UserController::class, 'resetPassword']);
    });
};
