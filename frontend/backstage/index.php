<?php
declare(strict_types=1);

$u = $_SESSION['user'] ?? null;
$isAdmin = $u && (!empty($u['is_platform_admin']) || ($u['role'] ?? '') === 'admin'
               || ($u['role'] ?? '') === 'global_system_administrator');
if (!$isAdmin) { header('Location: /'); exit; }

$pageTitle   = 'Insights';
$activePage  = 'insights';
$breadcrumbs = [];

ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Insights</h1>
    <p class="page-header__subtitle">Create, edit, publish, and delete insights.</p>
  </div>
  <div>
    <a href="/backstage/insights/new" class="btn btn--success-outline" id="insight-add-btn">
      <i class="bi bi-plus-lg" aria-hidden="true"></i>&nbsp;Add insight
    </a>
  </div>
</div>

<!-- KPI cards -->
<div class="kpis-grid">
  <?php
    $iconNews = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>';
    $iconClock = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
    $iconStar = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 2l2.9 6.9L22 10l-5 4.8L18.2 22 12 18.5 5.8 22 7 14.8 2 10l7.1-1.1z"/></svg>';

    foreach ([
      ['kpi_id' => 'published', 'label' => 'Published',  'value' => '—', 'icon_html' => $iconNews,  'icon_variant' => 'green',  'trend_label' => 'last 30 days', 'trend_direction' => 'muted'],
      ['kpi_id' => 'scheduled', 'label' => 'Scheduled',  'value' => '—', 'icon_html' => $iconClock, 'icon_variant' => 'blue',   'trend_label' => 'next 30 days', 'trend_direction' => 'muted'],
      ['kpi_id' => 'featured',  'label' => 'Featured',   'value' => '—', 'icon_html' => $iconStar,  'icon_variant' => 'purple', 'trend_label' => 'highlighted',  'trend_direction' => 'muted'],
    ] as $kpi) {
      daems_shared_partial('components/cards/kpi-card/kpi-card', $kpi);
    }
  ?>
</div>

<!-- Data explorer -->
<div class="data-explorer__panel">
  <div class="data-explorer__toolbar">
    <div class="data-explorer__seg" id="insight-status-filter" role="tablist">
      <button type="button" class="data-explorer__seg-btn is-active" data-status="all">All</button>
      <button type="button" class="data-explorer__seg-btn"           data-status="published">Published</button>
      <button type="button" class="data-explorer__seg-btn"           data-status="scheduled">Scheduled</button>
      <button type="button" class="data-explorer__seg-btn"           data-status="draft">Draft</button>
    </div>
    <input type="search" id="insight-filter-input" class="data-explorer__search" placeholder="Search by title…">
  </div>

  <table class="data-explorer__data">
    <thead>
      <tr>
        <th>Title</th>
        <th>Category</th>
        <th>Author</th>
        <th>Status</th>
        <th>Published date</th>
        <th></th>
      </tr>
    </thead>
    <tbody id="insights-tbody"></tbody>
  </table>

  <!-- Empty / Error mount points -->
  <div id="insights-empty-mount" style="display:none;">
    <?php
      $svg_path  = '/modules/insights/assets/backstage/empty-state.svg';
      $title     = 'No insights yet';
      $body      = 'Create your first insight to share news with members.';
      $cta_label = '+ Add insight';
      $cta_href  = '/backstage/insights/new';
      include DAEMS_SITE_PUBLIC . '/pages/shared/empty-state.php';
    ?>
  </div>
  <div id="insights-error-mount" style="display:none;"></div>
</div>

<script src="/modules/insights/assets/backstage/insight-panel.js" defer></script>

<?php
$pageContent = ob_get_clean();
require DAEMS_SITE_PUBLIC . '/pages/layout.php';
