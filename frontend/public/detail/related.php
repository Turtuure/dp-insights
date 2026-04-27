<?php
/**
 * Insight detail page — Related articles carousel.
 *
 * Expects $insight array to be in scope (set by detail.php).
 * Loads all other insights from /data/ excluding the current one.
 *
 * @package DaemsPublic
 */

$allInsights = ApiClient::get('/insights') ?? [];
$related = array_values(array_filter($allInsights, fn($i) => $i['slug'] !== $insight['slug']));
usort($related, fn($a, $b) => strcmp($b['date'], $a['date']));

if (empty($related)) return;

$categoryColors = [
    'analysis' => '#0d6efd',
    'report'   => '#198754',
    'opinion'  => '#6610f2',
    'research' => '#fd7e14',
];
?>
<section class="insights-related">
    <div class="container">

        <div class="insights-related-header">
            <h2>More Insights</h2>
        </div>

        <div class="insights-carousel" id="insights-carousel">
            <button class="insights-carousel-btn insights-carousel-prev" aria-label="Previous" disabled>
                <i class="bi bi-chevron-left"></i>
            </button>

            <div class="insights-carousel-track-wrap">
                <div class="insights-carousel-track">
                    <?php foreach ($related as $item):
                        $date = new DateTime($item['date']);
                        $formattedDate = $date->format('j M Y');
                        $color = $categoryColors[$item['category']] ?? '#0d6efd';
                    ?>
                    <a href="/insights/<?= htmlspecialchars($item['slug']) ?>" class="insight-related-card">
                        <div class="insight-related-card-img<?= !$item['hero_image'] ? ' insight-related-card-img--no-image' : '' ?>"<?= !$item['hero_image'] ? ' style="--insight-color:' . $color . '"' : '' ?>>
                            <?php if ($item['hero_image']): ?>
                            <img src="<?= htmlspecialchars($item['hero_image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" loading="lazy" />
                            <?php endif; ?>
                            <span class="insight-category-badge"><?= htmlspecialchars($item['category_label']) ?></span>
                        </div>
                        <div class="insight-related-card-body">
                            <p class="insight-related-card-meta">
                                <span><?= htmlspecialchars($item['author']) ?></span>
                                <span class="event-meta-sep">·</span>
                                <span><?= $formattedDate ?></span>
                            </p>
                            <h3><?= htmlspecialchars($item['title']) ?></h3>
                            <span class="project-link">Read more <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <button class="insights-carousel-btn insights-carousel-next" aria-label="Next">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>

    </div>
</section>
