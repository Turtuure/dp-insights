<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\Backstage\GetInsightWithAllTranslations;

use Daems\Domain\Auth\ActingUser;
use Daems\Domain\Tenant\TenantId;

final class GetInsightWithAllTranslationsInput
{
    public function __construct(
        public readonly TenantId $tenantId,
        public readonly string $insightId,
        public readonly ActingUser $actor,
    ) {
    }
}
