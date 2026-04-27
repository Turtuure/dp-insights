<?php
declare(strict_types=1);

use Daems\Infrastructure\Framework\Container\Container;
use Daems\Infrastructure\Framework\Database\Connection;
use DaemsModule\Insights\Application\Backstage\GetInsightWithAllTranslations\GetInsightWithAllTranslations;
use DaemsModule\Insights\Application\Backstage\UpdateInsightTranslation\UpdateInsightTranslation;
use DaemsModule\Insights\Application\CreateInsight\CreateInsight;
use DaemsModule\Insights\Application\DeleteInsight\DeleteInsight;
use DaemsModule\Insights\Application\GetInsight\GetInsight;
use DaemsModule\Insights\Application\ListInsights\ListInsights;
use DaemsModule\Insights\Application\ListInsightStats\ListInsightStats;
use DaemsModule\Insights\Application\UpdateInsight\UpdateInsight;
use DaemsModule\Insights\Controller\InsightBackstageController;
use DaemsModule\Insights\Controller\InsightController;
use DaemsModule\Insights\Domain\InsightRepositoryInterface;
use DaemsModule\Insights\Infrastructure\SqlInsightRepository;

return function (Container $container): void {
    $container->singleton(InsightRepositoryInterface::class,
        static fn(Container $c) => new SqlInsightRepository($c->make(Connection::class)),
    );
    $container->bind(ListInsights::class,
        static fn(Container $c) => new ListInsights($c->make(InsightRepositoryInterface::class)),
    );
    $container->bind(GetInsight::class,
        static fn(Container $c) => new GetInsight($c->make(InsightRepositoryInterface::class)),
    );
    $container->bind(CreateInsight::class,
        static fn(Container $c) => new CreateInsight($c->make(InsightRepositoryInterface::class)),
    );
    $container->bind(UpdateInsight::class,
        static fn(Container $c) => new UpdateInsight($c->make(InsightRepositoryInterface::class)),
    );
    $container->bind(DeleteInsight::class,
        static fn(Container $c) => new DeleteInsight($c->make(InsightRepositoryInterface::class)),
    );
    $container->bind(ListInsightStats::class,
        static fn(Container $c) => new ListInsightStats($c->make(InsightRepositoryInterface::class)),
    );
    $container->bind(GetInsightWithAllTranslations::class,
        static fn(Container $c) => new GetInsightWithAllTranslations($c->make(InsightRepositoryInterface::class)),
    );
    $container->bind(UpdateInsightTranslation::class,
        static fn(Container $c) => new UpdateInsightTranslation($c->make(InsightRepositoryInterface::class)),
    );
    $container->bind(InsightController::class,
        static fn(Container $c) => new InsightController(
            $c->make(ListInsights::class),
            $c->make(GetInsight::class),
        ),
    );
    $container->bind(InsightBackstageController::class,
        static fn(Container $c) => new InsightBackstageController(
            $c->make(ListInsights::class),
            $c->make(CreateInsight::class),
            $c->make(UpdateInsight::class),
            $c->make(DeleteInsight::class),
            $c->make(ListInsightStats::class),
            $c->make(InsightRepositoryInterface::class),
            $c->make(GetInsightWithAllTranslations::class),
            $c->make(UpdateInsightTranslation::class),
        ),
    );
};
