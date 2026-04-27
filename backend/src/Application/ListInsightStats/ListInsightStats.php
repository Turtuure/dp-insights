<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\ListInsightStats;

use DaemsModule\Insights\Domain\InsightRepositoryInterface;

final class ListInsightStats
{
    public function __construct(
        private readonly InsightRepositoryInterface $repo,
    ) {}

    public function execute(ListInsightStatsInput $input): ListInsightStatsOutput
    {
        return new ListInsightStatsOutput(
            stats: $this->repo->statsForTenant($input->tenantId),
        );
    }
}
