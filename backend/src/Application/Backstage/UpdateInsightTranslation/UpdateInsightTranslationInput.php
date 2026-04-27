<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\Backstage\UpdateInsightTranslation;

use Daems\Domain\Auth\ActingUser;
use Daems\Domain\Tenant\TenantId;

final class UpdateInsightTranslationInput
{
    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(
        public readonly TenantId $tenantId,
        public readonly string $insightId,
        public readonly string $localeRaw,
        public readonly array $fields,
        public readonly ActingUser $actor,
    ) {
    }
}
