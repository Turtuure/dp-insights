<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Tests\Unit\Domain;

use DaemsModule\Insights\Domain\Insight;
use DaemsModule\Insights\Domain\InsightId;
use Daems\Domain\Locale\SupportedLocale;
use Daems\Domain\Locale\TranslationMap;
use Daems\Domain\Tenant\TenantId;
use PHPUnit\Framework\TestCase;

final class InsightTest extends TestCase
{
    private const TENANT  = '019d0000-0000-7000-8000-000000000001';
    private const INSIGHT = '019d0000-0000-7000-8000-000000000010';

    public function test_default_constructor_seeds_fi_FI_from_scalars(): void
    {
        $insight = $this->build(title: 'Hei', excerpt: 'esittely', content: '<p>runko</p>');

        $row = $insight->translations()->rowFor(SupportedLocale::uiDefault());
        self::assertNotNull($row);
        self::assertSame('Hei',           $row['title']);
        self::assertSame('esittely',      $row['excerpt']);
        self::assertSame('<p>runko</p>',  $row['content']);
    }

    public function test_explicit_translations_override_scalar_seed(): void
    {
        $map = new TranslationMap([
            'fi_FI' => ['title' => 'Hei',   'excerpt' => 'esittely', 'content' => 'r'],
            'en_GB' => ['title' => 'Hello', 'excerpt' => 'lead',     'content' => 'b'],
            'sw_TZ' => null,
        ]);
        $insight = $this->build(translations: $map);

        $en = $insight->translations()->rowFor(SupportedLocale::contentFallback());
        self::assertNotNull($en);
        self::assertSame('Hello', $en['title']);
        self::assertNull($insight->translations()->rowFor(SupportedLocale::fromString('sw_TZ')));
    }

    public function test_view_falls_back_to_content_fallback_locale(): void
    {
        $map = new TranslationMap([
            'fi_FI' => null,
            'en_GB' => ['title' => 'Hello', 'excerpt' => 'lead', 'content' => 'body'],
            'sw_TZ' => null,
        ]);
        $insight = $this->build(translations: $map);

        $view = $insight->view(SupportedLocale::uiDefault(), SupportedLocale::contentFallback());
        self::assertSame('Hello', $view->field('title'));
        self::assertTrue($view->isFallback('title'));
        self::assertFalse($view->isMissing('title'));
    }

    public function test_coverage_counts_filled_fields_per_locale(): void
    {
        $map = new TranslationMap([
            'fi_FI' => ['title' => 'Hei', 'excerpt' => 'e', 'content' => 'c'],
            'en_GB' => ['title' => 'Hello', 'excerpt' => '', 'content' => ''],
            'sw_TZ' => null,
        ]);
        $insight = $this->build(translations: $map);

        $cov = $insight->translations()->coverage(Insight::TRANSLATABLE_FIELDS);
        self::assertSame(['filled' => 3, 'total' => 3], $cov['fi_FI']);
        self::assertSame(['filled' => 1, 'total' => 3], $cov['en_GB']);
        self::assertSame(['filled' => 0, 'total' => 3], $cov['sw_TZ']);
    }

    public function test_translatable_fields_constant_lists_three_columns(): void
    {
        self::assertSame(['title', 'excerpt', 'content'], Insight::TRANSLATABLE_FIELDS);
    }

    private function build(
        string $title = 'T',
        string $excerpt = 'E',
        string $content = 'C',
        ?TranslationMap $translations = null,
    ): Insight {
        return new Insight(
            id: InsightId::fromString(self::INSIGHT),
            tenantId: TenantId::fromString(self::TENANT),
            slug: 's',
            title: $title,
            category: 'cat',
            categoryLabel: 'Cat',
            featured: false,
            date: '2026-01-01',
            author: 'a',
            readingTime: 1,
            excerpt: $excerpt,
            heroImage: null,
            tags: [],
            content: $content,
            translations: $translations,
        );
    }
}
