<?php
/**
 * Insight detail page — Hero section.
 *
 * Expects $insight array to be in scope (set by detail.php).
 *
 * @package DaemsPublic
 */
$date = new DateTime($insight['date']);
$formattedDate = $date->format('F j, Y');

// Category colour map
$categoryColors = [
    'analysis' => 'insight-detail-hero--analysis',
    'report'   => 'insight-detail-hero--report',
    'opinion'  => 'insight-detail-hero--opinion',
    'research' => 'insight-detail-hero--research',
];
$heroClass = $categoryColors[$insight['category']] ?? 'insight-detail-hero--analysis';
?>
<section class="insight-detail-hero <?= $heroClass ?><?= $insight['hero_image'] ? ' insight-detail-hero--has-image' : '' ?>">
    <?php if ($insight['hero_image']): ?>
    <img
        src="<?= htmlspecialchars($insight['hero_image']) ?>"
        alt="<?= htmlspecialchars($insight['title']) ?>"
        class="insight-detail-hero-bg"
        loading="eager"
    />
    <div class="insight-detail-hero-overlay"></div>
    <?php endif; ?>

    <div class="container insight-detail-hero-content">
        <a href="/insights" class="event-detail-back">
            <i class="bi bi-arrow-left"></i> Back to Insights
        </a>
        <span class="about-tag event-detail-tag"><?= htmlspecialchars($insight['category_label']) ?></span>
        <h1><?= htmlspecialchars($insight['title']) ?></h1>
        <p class="event-detail-hero-meta">
            <i class="bi bi-person"></i> <?= htmlspecialchars($insight['author']) ?>
            <span class="event-meta-sep">·</span>
            <i class="bi bi-calendar3"></i> <?= $formattedDate ?>
            <span class="event-meta-sep">·</span>
            <i class="bi bi-clock"></i> <?= (int)$insight['reading_time'] ?> min read
        </p>
    </div>
</section>
