<?php
declare(strict_types=1);

namespace DaemsModule\Insights\Application\DeleteInsight;

use DaemsModule\Insights\Domain\InsightId;
use Daems\Domain\Tenant\TenantId;

final class DeleteInsightInput
{
    public function __construct(
        public readonly InsightId $insightId,
        public readonly TenantId $tenantId,
    ) {}
}
