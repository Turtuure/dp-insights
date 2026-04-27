<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\CreateInsight;

use DaemsModule\Insights\Domain\Insight;

final class CreateInsightOutput
{
    public function __construct(public readonly Insight $insight) {}
}
