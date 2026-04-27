<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Application\Backstage\GetInsightWithAllTranslations;

use DaemsModule\Insights\Domain\Insight;
use DaemsModule\Insights\Domain\InsightId;
use DaemsModule\Insights\Domain\InsightRepositoryInterface;
use Daems\Domain\Auth\ForbiddenException;
use Daems\Domain\Shared\NotFoundException;

final class GetInsightWithAllTranslations
{
    public function __construct(private readonly InsightRepositoryInterface $insights)
    {
    }

    public function execute(GetInsightWithAllTranslationsInput $input): GetInsightWithAllTranslationsOutput
    {
        if (!$input->actor->isAdminIn($input->tenantId) && !$input->actor->isPlatformAdmin()) {
            throw new ForbiddenException();
        }
        $insight = $this->insights->findByIdForTenant(
            InsightId::fromString($input->insightId),
            $input->tenantId,
        );
        if ($insight === null) {
            throw new NotFoundException('insight');
        }

        $translations = [];
        foreach ($insight->translations()->raw() as $loc => $row) {
            $translations[$loc] = $row;
        }
        $coverage = $insight->translations()->coverage(Insight::TRANSLATABLE_FIELDS);

        return new GetInsightWithAllTranslationsOutput([
            'id'             => $insight->id()->value(),
            'slug'           => $insight->slug(),
            'category'       => $insight->category(),
            'category_label' => $insight->categoryLabel(),
            'featured'       => $insight->featured(),
            'published_date' => $insight->date(),
            'author'         => $insight->author(),
            'reading_time'   => $insight->readingTime(),
            'hero_image'     => $insight->heroImage(),
            'tags'           => $insight->tags(),
            'translations'   => $translations,
            'coverage'       => $coverage,
        ]);
    }
}
