<?php
/**
 * Insight detail page — Article body + meta sidebar.
 *
 * Expects $insight array to be in scope (set by detail.php).
 *
 * @package DaemsPublic
 */
$date = new DateTime($insight['date']);
$formattedDate = $date->format('l, F j, Y');

// Category colour modifier
$metaCardMod = [
    'analysis' => 'insight-detail-meta-card--analysis',
    'report'   => 'insight-detail-meta-card--report',
    'opinion'  => 'insight-detail-meta-card--opinion',
    'research' => 'insight-detail-meta-card--research',
];
$cardClass = $metaCardMod[$insight['category']] ?? 'insight-detail-meta-card--analysis';
?>
<section class="insight-detail-content">
    <div class="container">
        <div class="row g-3 g-md-5">

            <div class="col-lg-8">
                <div class="insight-article-body">
                    <?= $insight['content'] ?>
                </div>

                <?php if (!empty($insight['tags'])): ?>
                <div class="insight-tags">
                    <?php foreach ($insight['tags'] as $tag): ?>
                    <span class="insight-tag"><?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="insight-detail-meta-card <?= $cardClass ?>">
                    <h4>About this article</h4>
                    <ul class="insight-detail-meta-list <?= str_replace('insight-detail-meta-card', 'insight-detail-meta-list', $cardClass) ?>">
                        <li>
                            <i class="bi bi-person"></i>
                            <div>
                                <div class="insight-meta-label">Author</div>
                                <div><?= htmlspecialchars($insight['author']) ?></div>
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-calendar3"></i>
                            <div>
                                <div class="insight-meta-label">Published</div>
                                <div><?= $formattedDate ?></div>
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-tag"></i>
                            <div>
                                <div class="insight-meta-label">Category</div>
                                <div><?= htmlspecialchars($insight['category_label']) ?></div>
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-clock"></i>
                            <div>
                                <div class="insight-meta-label">Reading time</div>
                                <div><?= (int)$insight['reading_time'] ?> minutes</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</section>
