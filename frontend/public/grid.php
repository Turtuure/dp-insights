<?php
/**
 * Insights page — Filter + insight card grid with sidebar.
 *
 * Loads all insight data files dynamically from /data/.
 *
 * @package DaemsPublic
 */

$insights = ApiClient::get('/insights') ?? [];
usort($insights, fn($a, $b) => strcmp($b['date'], $a['date']));

$categoryColors = [
    'analysis' => '#0d6efd',
    'report'   => '#198754',
    'opinion'  => '#6610f2',
    'research' => '#fd7e14',
];

// Featured articles for sidebar
$featured = array_filter($insights, fn($i) => !empty($i['featured']));
// Fallback: oldest articles if none are marked featured
if (empty($featured)) {
    $featured = array_slice(array_reverse($insights), 0, 3);
}
$featured = array_values($featured);
?>
<section class="insights-grid">
    <div class="container">
        <div class="row g-3 g-md-5">

            <!-- Main content -->
            <div class="col-lg-9">

                <div class="project-filters">
                    <button class="insight-filter-btn active" data-filter="all">All</button>
                    <button class="insight-filter-btn" data-filter="analysis">Analysis</button>
                    <button class="insight-filter-btn" data-filter="report">Report</button>
                    <button class="insight-filter-btn" data-filter="opinion">Opinion</button>
                    <button class="insight-filter-btn" data-filter="research">Research</button>
                </div>

                <div class="row g-4" id="insights-list" data-per-page="6">
                    <?php foreach ($insights as $item):
                        $date = new DateTime($item['date']);
                        $formattedDate = $date->format('j M Y');
                        $color = $categoryColors[$item['category']] ?? '#0d6efd';
                    ?>
                    <div class="col-md-6 col-lg-4 insight-card-wrap" data-category="<?= htmlspecialchars($item['category']) ?>">
                        <a href="/insights/<?= htmlspecialchars($item['slug']) ?>" class="insight-card">
                            <div class="insight-card-header<?= $item['hero_image'] ? ' insight-card-header--has-image' : ' insight-card-header--no-image' ?>"<?= !$item['hero_image'] ? ' style="--insight-color:' . $color . '"' : '' ?>>
                                <?php if ($item['hero_image']): ?>
                                <img src="<?= htmlspecialchars($item['hero_image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="insight-card-img" loading="lazy" />
                                <?php endif; ?>
                                <span class="insight-category-badge"><?= htmlspecialchars($item['category_label']) ?></span>
                            </div>
                            <div class="insight-card-body">
                                <div class="insight-card-meta">
                                    <i class="bi bi-calendar3"></i>
                                    <span><?= $formattedDate ?></span>
                                    <span class="event-meta-sep">·</span>
                                    <i class="bi bi-person"></i>
                                    <span><?= htmlspecialchars($item['author']) ?></span>
                                </div>
                                <h3><?= htmlspecialchars($item['title']) ?></h3>
                                <p class="insight-card-excerpt"><?= htmlspecialchars($item['excerpt']) ?></p>
                                <span class="project-link">Read more <i class="bi bi-arrow-right"></i></span>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <nav class="insights-pagination" id="insights-pagination" aria-label="Insights pages"></nav>

            </div>

            <!-- Sidebar -->
            <div class="col-lg-3">
                <aside class="insights-sidebar">

                    <!-- Search -->
                    <div class="insights-widget">
                        <h4 class="insights-widget-title">Search</h4>
                        <div class="insights-search">
                            <input
                                type="search"
                                class="form-control insights-search-input"
                                placeholder="Search insights…"
                                id="insights-search-input"
                                aria-label="Search insights"
                            />
                            <button class="insights-search-btn" aria-label="Submit search">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Featured -->
                    <div class="insights-widget">
                        <h4 class="insights-widget-title">Featured</h4>
                        <ul class="insights-latest-list">
                            <?php foreach ($featured as $item):
                                $date = new DateTime($item['date']);
                                $formattedDate = $date->format('j M Y');
                                $color = $categoryColors[$item['category']] ?? '#0d6efd';
                            ?>
                            <li class="insights-latest-item">
                                <a href="/insights/<?= htmlspecialchars($item['slug']) ?>" class="insights-latest-link">
                                    <div class="insights-latest-thumb<?= !$item['hero_image'] ? ' insights-latest-thumb--no-image' : '' ?>"<?= !$item['hero_image'] ? ' style="--insight-color:' . $color . '"' : '' ?>>
                                        <?php if ($item['hero_image']): ?>
                                        <img src="<?= htmlspecialchars($item['hero_image']) ?>" alt="" loading="lazy" />
                                        <?php endif; ?>
                                    </div>
                                    <div class="insights-latest-info">
                                        <p class="insights-latest-date"><i class="bi bi-calendar3"></i> <?= $formattedDate ?></p>
                                        <p class="insights-latest-title"><?= htmlspecialchars($item['title']) ?></p>
                                    </div>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                </aside>
            </div>

        </div>
    </div>
</section>
