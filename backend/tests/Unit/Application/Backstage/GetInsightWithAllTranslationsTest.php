<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Tests\Unit\Application\Backstage;

use DaemsModule\Insights\Application\Backstage\GetInsightWithAllTranslations\GetInsightWithAllTranslations;
use DaemsModule\Insights\Application\Backstage\GetInsightWithAllTranslations\GetInsightWithAllTranslationsInput;
use DaemsModule\Insights\Domain\Insight;
use DaemsModule\Insights\Domain\InsightId;
use DaemsModule\Insights\Tests\Support\InMemoryInsightRepository;
use Daems\Domain\Auth\ActingUser;
use Daems\Domain\Auth\ForbiddenException;
use Daems\Domain\Locale\SupportedLocale;
use Daems\Domain\Locale\TranslationMap;
use Daems\Domain\Shared\NotFoundException;
use Daems\Domain\Tenant\TenantId;
use Daems\Domain\Tenant\UserTenantRole;
use Daems\Domain\User\UserId;
use PHPUnit\Framework\TestCase;

final class GetInsightWithAllTranslationsTest extends TestCase
{
    private const TENANT  = '019d0000-0000-7000-8000-000000000001';
    private const INSIGHT = '019d0000-0000-7000-8000-000000000010';
    private const USER    = '019d0000-0000-7000-8000-0000000000aa';

    public function test_returns_chrome_translations_and_coverage(): void
    {
        $repo = new InMemoryInsightRepository();
        $tenantId = TenantId::fromString(self::TENANT);
        $repo->save(new Insight(
            id: InsightId::fromString(self::INSIGHT),
            tenantId: $tenantId,
            slug: 'hello',
            title: 'Hei',
            category: 'tech',
            categoryLabel: 'Tech',
            featured: true,
            date: '2026-01-01',
            author: 'Sam',
            readingTime: 1,
            excerpt: 'esittely',
            heroImage: null,
            tags: ['fi'],
            content: '<p>runko</p>',
            translations: new TranslationMap([
                'fi_FI' => ['title' => 'Hei',   'excerpt' => 'esittely', 'content' => '<p>runko</p>'],
                'en_GB' => ['title' => 'Hello', 'excerpt' => '',          'content' => ''],
                'sw_TZ' => null,
            ]),
        ));
        $uc = new GetInsightWithAllTranslations($repo);

        $out = $uc->execute(new GetInsightWithAllTranslationsInput(
            $tenantId, self::INSIGHT, $this->admin($tenantId),
        ));

        self::assertSame(self::INSIGHT, $out->insight['id']);
        self::assertSame('hello',       $out->insight['slug']);
        self::assertSame('tech',        $out->insight['category']);
        self::assertTrue($out->insight['featured']);
        self::assertArrayHasKey('translations', $out->insight);
        self::assertArrayHasKey('coverage',     $out->insight);
        self::assertSame('Hei',   $out->insight['translations']['fi_FI']['title']);
        self::assertSame('Hello', $out->insight['translations']['en_GB']['title']);
        self::assertNull($out->insight['translations']['sw_TZ']);
        self::assertSame(['filled' => 3, 'total' => 3], $out->insight['coverage']['fi_FI']);
        self::assertSame(['filled' => 1, 'total' => 3], $out->insight['coverage']['en_GB']);
        self::assertSame(['filled' => 0, 'total' => 3], $out->insight['coverage']['sw_TZ']);
    }

    public function test_forbidden_for_non_admin(): void
    {
        $repo = new InMemoryInsightRepository();
        $tenantId = TenantId::fromString(self::TENANT);
        $uc = new GetInsightWithAllTranslations($repo);

        $this->expectException(ForbiddenException::class);
        $uc->execute(new GetInsightWithAllTranslationsInput(
            $tenantId, self::INSIGHT, $this->member($tenantId),
        ));
    }

    public function test_not_found_when_missing(): void
    {
        $repo = new InMemoryInsightRepository();
        $tenantId = TenantId::fromString(self::TENANT);
        $uc = new GetInsightWithAllTranslations($repo);

        $this->expectException(NotFoundException::class);
        $uc->execute(new GetInsightWithAllTranslationsInput(
            $tenantId, self::INSIGHT, $this->admin($tenantId),
        ));
    }

    private function admin(TenantId $tenant): ActingUser
    {
        return new ActingUser(
            UserId::fromString(self::USER),
            'admin@example.com',
            isPlatformAdmin: false,
            activeTenant: $tenant,
            roleInActiveTenant: UserTenantRole::Admin,
        );
    }

    private function member(TenantId $tenant): ActingUser
    {
        return new ActingUser(
            UserId::fromString(self::USER),
            'member@example.com',
            isPlatformAdmin: false,
            activeTenant: $tenant,
            roleInActiveTenant: UserTenantRole::Member,
        );
    }
}
