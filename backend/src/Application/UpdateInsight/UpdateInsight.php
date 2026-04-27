<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\UpdateInsight;

use DaemsModule\Insights\Application\CreateInsight\CreateInsight;
use DaemsModule\Insights\Domain\Insight;
use DaemsModule\Insights\Domain\InsightRepositoryInterface;
use Daems\Domain\Shared\NotFoundException;
use Daems\Domain\Shared\ValidationException;

/**
 * Updates the chrome (non-translatable) fields of an Insight. Translations
 * are managed separately via UpdateInsightTranslation. The existing
 * TranslationMap on the entity is preserved on save() so this update never
 * blanks an existing translation row.
 */
final class UpdateInsight
{
    public function __construct(private readonly InsightRepositoryInterface $repo) {}

    public function execute(UpdateInsightInput $in): UpdateInsightOutput
    {
        $existing = $this->repo->findByIdForTenant($in->insightId, $in->tenantId)
            ?? throw new NotFoundException('not_found');

        $this->validate($in);

        // Slug uniqueness: OK if slug unchanged or owned by this same insight.
        $bySlug = $this->repo->findBySlugForTenant($in->slug, $in->tenantId);
        if ($bySlug !== null && $bySlug->id()->value() !== $in->insightId->value()) {
            throw new ValidationException(['slug' => 'already_exists']);
        }

        $updated = new Insight(
            id: $existing->id(),
            tenantId: $in->tenantId,
            slug: $in->slug,
            title: $existing->title(),
            category: $in->category,
            categoryLabel: $in->categoryLabel,
            featured: $in->featured,
            date: CreateInsight::normalizeDateTime($in->publishedDate),
            author: $in->author,
            // reading_time tracks fi_FI body size, recomputed by
            // UpdateInsightTranslation when the fi_FI translation changes.
            // Keep the existing value here so chrome-only updates don't
            // accidentally blank it.
            readingTime: $existing->readingTime(),
            excerpt: $existing->excerpt(),
            heroImage: $in->heroImage,
            tags: $in->tags,
            content: $existing->content(),
            translations: $existing->translations(),
        );
        $this->repo->save($updated);

        return new UpdateInsightOutput($updated);
    }

    private function validate(UpdateInsightInput $in): void
    {
        $errors = [];
        if (trim($in->slug) === '') {
            $errors['slug'] = 'required';
        }
        if (trim($in->category) === '') {
            $errors['category'] = 'required';
        }
        // null published_date = draft (not yet published or scheduled).
        // Only validate the format when a value is provided. The normalizer
        // accepts Y-m-d, Y-m-d H:i, Y-m-d H:i:s, and the HTML datetime-local
        // T-separator variants; null return means no format matched.
        if ($in->publishedDate !== null && CreateInsight::normalizeDateTime($in->publishedDate) === null) {
            $errors['published_date'] = 'invalid_format';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
