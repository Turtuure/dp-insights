<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\UpdateInsight;

use DaemsModule\Insights\Domain\InsightId;
use Daems\Domain\Tenant\TenantId;

/**
 * Chrome-only update — translations (title, excerpt, content) are
 * persisted via UpdateInsightTranslation per locale. This mirrors the
 * Projects pattern where AdminUpdateProject is chrome-only and
 * UpdateProjectTranslation owns translatable fields.
 */
final class UpdateInsightInput
{
    /** @param string[] $tags */
    public function __construct(
        public readonly InsightId $insightId,
        public readonly TenantId $tenantId,
        public readonly string $slug,
        public readonly string $category,
        public readonly string $categoryLabel,
        public readonly bool $featured,
        public readonly ?string $publishedDate,
        public readonly string $author,
        public readonly ?string $heroImage,
        public readonly array $tags,
    ) {}
}
