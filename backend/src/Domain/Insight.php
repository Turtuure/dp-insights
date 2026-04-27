<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Domain;

use Daems\Domain\Locale\EntityTranslationView;
use Daems\Domain\Locale\SupportedLocale;
use Daems\Domain\Locale\TranslationMap;
use Daems\Domain\Tenant\TenantId;

final class Insight
{
    public const TRANSLATABLE_FIELDS = ['title', 'excerpt', 'content'];

    private readonly TranslationMap $translations;

    /**
     * @param string[] $tags
     */
    public function __construct(
        private readonly InsightId $id,
        private readonly TenantId $tenantId,
        private readonly string $slug,
        private readonly string $title,
        private readonly string $category,
        private readonly string $categoryLabel,
        private readonly bool $featured,
        private readonly ?string $date,
        private readonly string $author,
        private readonly int $readingTime,
        private readonly string $excerpt,
        private readonly ?string $heroImage,
        private readonly array $tags,
        private readonly string $content,
        ?TranslationMap $translations = null,
    ) {
        $this->translations = $translations ?? new TranslationMap([
            SupportedLocale::UI_DEFAULT => [
                'title'   => $this->title,
                'excerpt' => $this->excerpt,
                'content' => $this->content,
            ],
        ]);
    }

    public function id(): InsightId { return $this->id; }
    public function tenantId(): TenantId { return $this->tenantId; }
    public function slug(): string { return $this->slug; }
    public function title(): string { return $this->title; }
    public function category(): string { return $this->category; }
    public function categoryLabel(): string { return $this->categoryLabel; }
    public function featured(): bool { return $this->featured; }
    public function date(): ?string { return $this->date; }
    public function isDraft(): bool { return $this->date === null; }
    public function author(): string { return $this->author; }
    public function readingTime(): int { return $this->readingTime; }
    public function excerpt(): string { return $this->excerpt; }
    public function heroImage(): ?string { return $this->heroImage; }
    /** @return string[] */
    public function tags(): array { return $this->tags; }
    public function content(): string { return $this->content; }

    public function translations(): TranslationMap
    {
        return $this->translations;
    }

    public function view(SupportedLocale $requested, SupportedLocale $fallback): EntityTranslationView
    {
        return $this->translations->view($requested, $fallback, self::TRANSLATABLE_FIELDS);
    }
}
