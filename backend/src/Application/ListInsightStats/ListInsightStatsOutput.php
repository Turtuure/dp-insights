<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\ListInsightStats;

final class ListInsightStatsOutput
{
    /**
     * @param array{
     *   published: array{value: int, sparkline: list<array{date: string, value: int}>},
     *   scheduled: array{value: int, sparkline: list<array{date: string, value: int}>},
     *   featured:  array{
     *     value: int,
     *     sparkline: list<array{date: string, value: int}>,
     *     sparkline_scheduled: list<array{date: string, value: int}>
     *   }
     * } $stats
     */
    public function __construct(
        public readonly array $stats,
    ) {}
}
