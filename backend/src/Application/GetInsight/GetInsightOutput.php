<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\GetInsight;

final class GetInsightOutput
{
    public function __construct(
        public readonly ?array $insight,
    ) {}
}
