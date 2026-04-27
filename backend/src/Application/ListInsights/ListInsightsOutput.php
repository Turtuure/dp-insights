<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\ListInsights;

final class ListInsightsOutput
{
    public function __construct(
        public readonly array $insights,
    ) {}
}
