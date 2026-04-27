<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Tests\Unit\Application\Backstage;

use DaemsModule\Insights\Application\Backstage\UpdateInsightTranslation\UpdateInsightTranslation;
use DaemsModule\Insights\Application\Backstage\UpdateInsightTranslation\UpdateInsightTranslationInput;
use DaemsModule\Insights\Domain\Insight;
use DaemsModule\Insights\Domain\InsightId;
use DaemsModule\Insights\Tests\Support\InMemoryInsightRepository;
use Daems\Domain\Auth\ActingUser;
use Daems\Domain\Auth\ForbiddenException;
use Daems\Domain\Locale\InvalidLocaleException;
use Daems\Domain\Locale\SupportedLocale;
use Daems\Domain\Locale\TranslationMap;
use Daems\Domain\Tenant\TenantId;
use Daems\Domain\Tenant\UserTenantRole;
use Daems\Domain\User\UserId;
use PHPUnit\Framework\TestCase;

final class UpdateInsightTranslationTest extends TestCase
{
    private const TENANT  = '019d0000-0000-7000-8000-000000000001';
    private const INSIGHT = '019d0000-0000-7000-8000-000000000010';
    private const USER    = '019d0000-0000-7000-8000-0000000000aa';

    public function test_upserts_locale_row_and_returns_coverage(): void
    {
        [$repo, $tenantId] = $this->seed();
        $uc = new UpdateInsightTranslation($repo);

        $out = $uc->execute(new UpdateInsightTranslationInput(
            $tenantId,
            self::INSIGHT,
            'en_GB',
            ['title' => 'Hello', 'excerpt' => 'lead', 'content' => '<p>body</p>'],
            $this->admin($tenantId),
        ));

        self::assertSame(['filled' => 3, 'total' => 3], $out->coverage['en_GB']);
        // sw_TZ untouched.
        self::assertSame(['filled' => 0, 'total' => 3], $out->coverage['sw_TZ']);
    }

    public function test_recomputes_reading_time_when_fi_FI_is_saved(): void
    {
        [$repo, $tenantId] = $this->seed();
        $uc = new UpdateInsightTranslation($repo);

        // 600 words / 200 wpm = 3 minutes. Existing reading_time was 1.
        $longContent = '<p>' . str_repeat('word ', 600) . '</p>';
        $uc->execute(new UpdateInsightTranslationInput(
            $tenantId,
            self::INSIGHT,
            'fi_FI',
            ['title' => 'Pidempi', 'excerpt' => 'pitkampi esittely', 'content' => $longContent],
            $this->admin($tenantId),
        ));

        $insight = $repo->findByIdForTenant(InsightId::fromString(self::INSIGHT), $tenantId);
        self::assertNotNull($insight);
        self::assertSame(3, $insight->readingTime());
    }

    public function test_does_not_touch_reading_time_for_non_default_locale(): void
    {
        [$repo, $tenantId] = $this->seed();
        $uc = new UpdateInsightTranslation($repo);

        $longContent = '<p>' . str_repeat('word ', 600) . '</p>';
        $uc->execute(new UpdateInsightTranslationInput(
            $tenantId,
            self::INSIGHT,
            'en_GB',
            ['title' => 'Long', 'excerpt' => 'lead', 'content' => $longContent],
            $this->admin($tenantId),
        ));

        $insight = $repo->findByIdForTenant(InsightId::fromString(self::INSIGHT), $tenantId);
        self::assertNotNull($insight);
        self::assertSame(1, $insight->readingTime(), 'reading_time tracks fi_FI only');
    }

    public function test_rejects_blank_required_field(): void
    {
        [$repo, $tenantId] = $this->seed();
        $uc = new UpdateInsightTranslation($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('title_required');
        $uc->execute(new UpdateInsightTranslationInput(
            $tenantId,
            self::INSIGHT,
            'fi_FI',
            ['title' => '   ', 'excerpt' => 'e', 'content' => 'c'],
            $this->admin($tenantId),
        ));
    }

    public function test_rejects_unsupported_locale(): void
    {
        [$repo, $tenantId] = $this->seed();
        $uc = new UpdateInsightTranslation($repo);

        $this->expectException(InvalidLocaleException::class);
        $uc->execute(new UpdateInsightTranslationInput(
            $tenantId,
            self::INSIGHT,
            'es_ES',
            ['title' => 'Hola', 'excerpt' => 'e', 'content' => 'c'],
            $this->admin($tenantId),
        ));
    }

    public function test_forbidden_for_non_admin(): void
    {
        [$repo, $tenantId] = $this->seed();
        $uc = new UpdateInsightTranslation($repo);

        $this->expectException(ForbiddenException::class);
        $uc->execute(new UpdateInsightTranslationInput(
            $tenantId,
            self::INSIGHT,
            'fi_FI',
            ['title' => 'T', 'excerpt' => 'E', 'content' => 'C'],
            $this->member($tenantId),
        ));
    }

    public function test_rejects_missing_insight(): void
    {
        $repo = new InMemoryInsightRepository();
        $tenantId = TenantId::fromString(self::TENANT);
        $uc = new UpdateInsightTranslation($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('insight_not_found_in_tenant');
        $uc->execute(new UpdateInsightTranslationInput(
            $tenantId,
            self::INSIGHT,
            'fi_FI',
            ['title' => 'T', 'excerpt' => 'E', 'content' => 'C'],
            $this->admin($tenantId),
        ));
    }

    /** @return array{0: InMemoryInsightRepository, 1: TenantId} */
    private function seed(): array
    {
        $repo     = new InMemoryInsightRepository();
        $tenantId = TenantId::fromString(self::TENANT);
        $repo->save(new Insight(
            id: InsightId::fromString(self::INSIGHT),
            tenantId: $tenantId,
            slug: 's',
            title: 'Hei',
            category: 'tech',
            categoryLabel: 'Tech',
            featured: false,
            date: '2026-01-01',
            author: 'Sam',
            readingTime: 1,
            excerpt: 'esittely',
            heroImage: null,
            tags: [],
            content: '<p>runko</p>',
            translations: new TranslationMap([
                'fi_FI' => ['title' => 'Hei', 'excerpt' => 'esittely', 'content' => '<p>runko</p>'],
                'en_GB' => null,
                'sw_TZ' => null,
            ]),
        ));
        return [$repo, $tenantId];
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
