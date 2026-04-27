<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Controller;

use DaemsModule\Insights\Application\CreateInsight\CreateInsight;
use DaemsModule\Insights\Application\CreateInsight\CreateInsightInput;
use DaemsModule\Insights\Application\DeleteInsight\DeleteInsight;
use DaemsModule\Insights\Application\DeleteInsight\DeleteInsightInput;
use DaemsModule\Insights\Application\ListInsights\ListInsights;
use DaemsModule\Insights\Application\ListInsights\ListInsightsInput;
use DaemsModule\Insights\Application\ListInsightStats\ListInsightStats;
use DaemsModule\Insights\Application\ListInsightStats\ListInsightStatsInput;
use DaemsModule\Insights\Application\UpdateInsight\UpdateInsight;
use DaemsModule\Insights\Application\UpdateInsight\UpdateInsightInput;
use DaemsModule\Insights\Domain\Insight;
use DaemsModule\Insights\Domain\InsightId;
use DaemsModule\Insights\Domain\InsightRepositoryInterface;
use Daems\Domain\Auth\ForbiddenException;
use Daems\Domain\Shared\NotFoundException;
use Daems\Domain\Shared\ValidationException;
use Daems\Domain\Tenant\Tenant;
use Daems\Infrastructure\Framework\Http\Request;
use Daems\Infrastructure\Framework\Http\Response;

final class InsightBackstageController
{
    public function __construct(
        private readonly ListInsights $listInsights,
        private readonly CreateInsight $createInsight,
        private readonly UpdateInsight $updateInsight,
        private readonly DeleteInsight $deleteInsight,
        private readonly ListInsightStats $listInsightStats,
        private readonly InsightRepositoryInterface $insightRepo,
    ) {}

    public function list(Request $request): Response
    {
        $tenant = $this->requireTenant($request);
        $this->requireInsightsAdmin($request, $tenant);

        $category = $request->string('category');
        $out = $this->listInsights->execute(new ListInsightsInput(
            tenantId: $tenant->id,
            category: $category,
            includeUnpublished: true,
        ));

        return Response::json(['data' => $out->insights]);
    }

    public function create(Request $request): Response
    {
        $tenant = $this->requireTenant($request);
        $this->requireInsightsAdmin($request, $tenant);

        $body = $request->all();
        try {
            $out = $this->createInsight->execute(new CreateInsightInput(
                tenantId:      $tenant->id,
                slug:          self::bodyStr($body, 'slug'),
                title:         self::bodyStr($body, 'title'),
                category:      self::bodyStr($body, 'category'),
                categoryLabel: self::bodyStr($body, 'category_label', self::bodyStr($body, 'category')),
                featured:      self::bodyBool($body, 'featured'),
                publishedDate: self::bodyNullableStr($body, 'published_date'),
                author:        self::bodyStr($body, 'author'),
                excerpt:       self::bodyStr($body, 'excerpt'),
                heroImage:     self::bodyNullableStr($body, 'hero_image'),
                tags:          self::bodyStringArray($body, 'tags'),
                content:       self::bodyStr($body, 'content'),
            ));
        } catch (ValidationException $e) {
            return Response::json([
                'error'  => 'validation',
                'errors' => $e->fields(),
            ], 422);
        }
        return Response::json(['data' => $this->insightArray($out->insight)], 201);
    }

    /** @param array<string, string> $params */
    public function get(Request $request, array $params): Response
    {
        $tenant = $this->requireTenant($request);
        $this->requireInsightsAdmin($request, $tenant);

        $id = (string) ($params['id'] ?? '');
        if ($id === '') {
            return Response::json(['error' => 'invalid_id'], 400);
        }

        $insight = $this->insightRepo->findByIdForTenant(InsightId::fromString($id), $tenant->id);
        if ($insight === null) {
            return Response::json(['error' => 'not_found'], 404);
        }
        return Response::json(['data' => $this->insightArray($insight)]);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params): Response
    {
        $tenant = $this->requireTenant($request);
        $this->requireInsightsAdmin($request, $tenant);

        $id = (string) ($params['id'] ?? '');
        if ($id === '') {
            return Response::json(['error' => 'invalid_id'], 400);
        }

        $body = $request->all();
        try {
            // Chrome-only update. Title/excerpt/content are owned by
            // UpdateInsightTranslation per locale and intentionally
            // ignored here even if the legacy frontend sends them.
            $out = $this->updateInsight->execute(new UpdateInsightInput(
                insightId:     InsightId::fromString($id),
                tenantId:      $tenant->id,
                slug:          self::bodyStr($body, 'slug'),
                category:      self::bodyStr($body, 'category'),
                categoryLabel: self::bodyStr($body, 'category_label', self::bodyStr($body, 'category')),
                featured:      self::bodyBool($body, 'featured'),
                publishedDate: self::bodyNullableStr($body, 'published_date'),
                author:        self::bodyStr($body, 'author'),
                heroImage:     self::bodyNullableStr($body, 'hero_image'),
                tags:          self::bodyStringArray($body, 'tags'),
            ));
        } catch (NotFoundException) {
            return Response::json(['error' => 'not_found'], 404);
        } catch (ValidationException $e) {
            return Response::json(['error' => 'validation', 'errors' => $e->fields()], 422);
        }
        return Response::json(['data' => $this->insightArray($out->insight)]);
    }

    /** @param array<string, string> $params */
    public function delete(Request $request, array $params): Response
    {
        $tenant = $this->requireTenant($request);
        $this->requireInsightsAdmin($request, $tenant);

        $id = (string) ($params['id'] ?? '');
        if ($id === '') {
            return Response::json(['error' => 'invalid_id'], 400);
        }

        try {
            $this->deleteInsight->execute(new DeleteInsightInput(
                insightId: InsightId::fromString($id),
                tenantId:  $tenant->id,
            ));
        } catch (NotFoundException) {
            return Response::json(['error' => 'not_found'], 404);
        }
        return Response::json(['data' => ['deleted' => true]]);
    }

    public function stats(Request $request): Response
    {
        $tenant = $this->requireTenant($request);
        $this->requireInsightsAdmin($request, $tenant);

        $out = $this->listInsightStats->execute(new ListInsightStatsInput(
            tenantId: $tenant->id,
        ));

        return Response::json(['data' => $out->stats]);
    }

    private function requireInsightsAdmin(Request $request, Tenant $tenant): void
    {
        $actor = $request->requireActingUser();
        if (!$actor->isAdminIn($tenant->id)) {
            throw new ForbiddenException('forbidden');
        }
    }

    /** @return array<string, mixed> */
    private function insightArray(Insight $i): array
    {
        return [
            'id'             => $i->id()->value(),
            'slug'           => $i->slug(),
            'title'          => $i->title(),
            'category'       => $i->category(),
            'category_label' => $i->categoryLabel(),
            'featured'       => $i->featured(),
            'published_date' => $i->date(),
            'author'         => $i->author(),
            'reading_time'   => $i->readingTime(),
            'excerpt'        => $i->excerpt(),
            'hero_image'     => $i->heroImage(),
            'tags'           => $i->tags(),
            'content'        => $i->content(),
        ];
    }

    /** @param array<mixed> $body */
    private static function bodyStr(array $body, string $key, string $default = ''): string
    {
        $v = $body[$key] ?? null;
        return is_string($v) ? $v : $default;
    }

    /** @param array<mixed> $body */
    private static function bodyBool(array $body, string $key): bool
    {
        return (bool) ($body[$key] ?? false);
    }

    /** @param array<mixed> $body */
    private static function bodyNullableStr(array $body, string $key): ?string
    {
        $v = $body[$key] ?? null;
        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * @param array<mixed> $body
     * @return string[]
     */
    private static function bodyStringArray(array $body, string $key): array
    {
        $v = $body[$key] ?? null;
        if (!is_array($v)) {
            return [];
        }
        return array_values(array_filter($v, 'is_string'));
    }

    private function requireTenant(Request $request): Tenant
    {
        $tenant = $request->attribute('tenant');
        if (!$tenant instanceof Tenant) {
            throw new NotFoundException('unknown_tenant');
        }
        return $tenant;
    }
}
