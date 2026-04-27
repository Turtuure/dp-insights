<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\Backstage\UpdateInsightTranslation;

final class UpdateInsightTranslationOutput
{
    /**
     * @param array<string, array{filled: int, total: int}> $coverage
     */
    public function __construct(public readonly array $coverage)
    {
    }
}
