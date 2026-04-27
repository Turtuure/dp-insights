<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\UpdateInsight;

use DaemsModule\Insights\Domain\Insight;

final class UpdateInsightOutput
{
    public function __construct(public readonly Insight $insight) {}
}
