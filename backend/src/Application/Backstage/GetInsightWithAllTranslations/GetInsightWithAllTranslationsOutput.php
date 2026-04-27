<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\Backstage\GetInsightWithAllTranslations;

final class GetInsightWithAllTranslationsOutput
{
    /**
     * @param array<string, mixed> $insight
     */
    public function __construct(public readonly array $insight)
    {
    }
}
