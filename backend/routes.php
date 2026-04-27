<?php
declare(strict_types=1);

use Daems\Infrastructure\Framework\Container\Container;
use Daems\Infrastructure\Framework\Http\Request;
use Daems\Infrastructure\Framework\Http\Response;
use Daems\Infrastructure\Framework\Http\Router;
use Daems\Infrastructure\Framework\Http\Middleware\AuthMiddleware;
use Daems\Infrastructure\Framework\Http\Middleware\TenantContextMiddleware;
use DaemsModule\Insights\Controller\InsightBackstageController;
use DaemsModule\Insights\Controller\InsightController;

return function (Router $router, Container $container): void {
    // Public reads
    $router->get('/api/v1/insights', static function (Request $req) use ($container): Response {
        return $container->make(InsightController::class)->index($req);
    }, [TenantContextMiddleware::class]);

    $router->get('/api/v1/insights/{slug}', static function (Request $req, array $params) use ($container): Response {
        return $container->make(InsightController::class)->show($req, $params);
    }, [TenantContextMiddleware::class]);

    // Backstage CRUD
    $router->get('/api/v1/backstage/insights', static function (Request $req) use ($container): Response {
        return $container->make(InsightBackstageController::class)->list($req);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->get('/api/v1/backstage/insights/stats', static function (Request $req) use ($container): Response {
        return $container->make(InsightBackstageController::class)->stats($req);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/insights', static function (Request $req) use ($container): Response {
        return $container->make(InsightBackstageController::class)->create($req);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->get('/api/v1/backstage/insights/{id}', static function (Request $req, array $params) use ($container): Response {
        return $container->make(InsightBackstageController::class)->get($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/insights/{id}', static function (Request $req, array $params) use ($container): Response {
        return $container->make(InsightBackstageController::class)->update($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/insights/{id}/delete', static function (Request $req, array $params) use ($container): Response {
        return $container->make(InsightBackstageController::class)->delete($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    // i18n: backstage editor pre-fill + per-locale upsert. Mirrors
    // /backstage/projects/{id}/translations[/{locale}] pattern.
    $router->get('/api/v1/backstage/insights/{id}/translations', static function (Request $req, array $params) use ($container): Response {
        return $container->make(InsightBackstageController::class)->getWithTranslations($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/insights/{id}/translations/{locale}', static function (Request $req, array $params) use ($container): Response {
        return $container->make(InsightBackstageController::class)->updateTranslation($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);
};
