<?php
declare(strict_types=1);

namespace DaemsModule\Insights\Application\DeleteInsight;

use DaemsModule\Insights\Domain\InsightRepositoryInterface;
use Daems\Domain\Shared\NotFoundException;

final class DeleteInsight
{
    public function __construct(private readonly InsightRepositoryInterface $repo) {}

    public function execute(DeleteInsightInput $in): void
    {
        $existing = $this->repo->findByIdForTenant($in->insightId, $in->tenantId)
            ?? throw new NotFoundException('not_found');

        $this->repo->delete($existing->id(), $in->tenantId);
    }
}
