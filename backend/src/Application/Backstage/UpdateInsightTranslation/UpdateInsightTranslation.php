<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\Backstage\UpdateInsightTranslation;

use DaemsModule\Insights\Application\CreateInsight\CreateInsight;
use DaemsModule\Insights\Domain\Insight;
use DaemsModule\Insights\Domain\InsightId;
use DaemsModule\Insights\Domain\InsightRepositoryInterface;
use Daems\Domain\Auth\ForbiddenException;
use Daems\Domain\Locale\SupportedLocale;

final class UpdateInsightTranslation
{
    public function __construct(private readonly InsightRepositoryInterface $insights)
    {
    }

    public function execute(UpdateInsightTranslationInput $input): UpdateInsightTranslationOutput
    {
        if (!$input->actor->isAdminIn($input->tenantId) && !$input->actor->isPlatformAdmin()) {
            throw new ForbiddenException();
        }
        $locale = SupportedLocale::fromString($input->localeRaw);

        $rawTitle   = $input->fields['title']   ?? null;
        $rawExcerpt = $input->fields['excerpt'] ?? null;
        $rawContent = $input->fields['content'] ?? null;
        $safeFields = [
            'title'   => is_scalar($rawTitle)   ? (string) $rawTitle   : '',
            'excerpt' => is_scalar($rawExcerpt) ? (string) $rawExcerpt : '',
            'content' => is_scalar($rawContent) ? (string) $rawContent : '',
        ];
        if (trim($safeFields['title']) === '') {
            throw new \DomainException('title_required');
        }
        if (trim($safeFields['excerpt']) === '') {
            throw new \DomainException('excerpt_required');
        }
        if (trim($safeFields['content']) === '') {
            throw new \DomainException('content_required');
        }

        $this->insights->saveTranslation($input->tenantId, $input->insightId, $locale, $safeFields);

        // After upserting the translation, recompute reading_time (chrome
        // column) when the saved locale is the UI default — the public list
        // displays fi_FI by default and reading_time tracks fi_FI body size.
        // Other locales leave reading_time alone.
        if ($locale->value() === SupportedLocale::UI_DEFAULT) {
            $insight = $this->insights->findByIdForTenant(
                InsightId::fromString($input->insightId),
                $input->tenantId,
            );
            if ($insight !== null) {
                $newReadingTime = CreateInsight::computeReadingTime($safeFields['content']);
                if ($newReadingTime !== $insight->readingTime()) {
                    $rebuilt = new Insight(
                        id: $insight->id(),
                        tenantId: $insight->tenantId(),
                        slug: $insight->slug(),
                        title: $insight->title(),
                        category: $insight->category(),
                        categoryLabel: $insight->categoryLabel(),
                        featured: $insight->featured(),
                        date: $insight->date(),
                        author: $insight->author(),
                        readingTime: $newReadingTime,
                        excerpt: $insight->excerpt(),
                        heroImage: $insight->heroImage(),
                        tags: $insight->tags(),
                        content: $insight->content(),
                        translations: $insight->translations(),
                    );
                    // save() upserts the chrome row; the translations map
                    // is preserved because we passed the same map back in.
                    $this->insights->save($rebuilt);
                }
            }
        }

        $insight = $this->insights->findByIdForTenant(
            InsightId::fromString($input->insightId),
            $input->tenantId,
        );
        if ($insight === null) {
            throw new \RuntimeException('insight_vanished');
        }
        $coverage = $insight->translations()->coverage(Insight::TRANSLATABLE_FIELDS);
        return new UpdateInsightTranslationOutput($coverage);
    }
}
