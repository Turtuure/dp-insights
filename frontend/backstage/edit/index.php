<?php
declare(strict_types=1);

if (!class_exists('ApiClient')) {
    require_once DAEMS_SITE_PUBLIC . '/../src/ApiClient.php';
}

$u = $_SESSION['user'] ?? null;
$isAdmin = $u && (!empty($u['is_platform_admin']) || ($u['role'] ?? '') === 'admin'
               || ($u['role'] ?? '') === 'global_system_administrator');
if (!$isAdmin) { header('Location: /'); exit; }

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    header('Location: /backstage/insights');
    exit;
}

// Server-side fetch so the form pre-fills synchronously (no flash of empty
// fields). After the i18n move we hit /translations to get chrome + per-locale
// rows + coverage in one round-trip — same shape Projects uses.
$insight = ApiClient::get('/backstage/insights/' . rawurlencode($id) . '/translations');
if (!is_array($insight) || empty($insight)) {
    http_response_code(404);
    require DAEMS_SITE_PUBLIC . '/pages/errors/404.php';
    exit;
}

$translations = is_array($insight['translations'] ?? null) ? $insight['translations'] : [];
$coverage     = is_array($insight['coverage']     ?? null) ? $insight['coverage']     : [];

// Title for the page subheader: prefer fi_FI, then en_GB, then sw_TZ.
$displayTitle = '(untitled)';
foreach (['fi_FI', 'en_GB', 'sw_TZ'] as $loc) {
    if (!empty($translations[$loc]['title'])) {
        $displayTitle = (string) $translations[$loc]['title'];
        break;
    }
}

$pageTitle   = 'Edit insight';
$activePage  = 'insights';
$breadcrumbs = [
    ['label' => 'Insights', 'url' => '/backstage/insights'],
    ['label' => 'Edit'],
];

$primary_label = 'Save';
$show_delete   = true;
$contentClass  = 'content--no-scroll';

$titleSafe = htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8');
$idSafe    = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title">Edit insight</h1>
        <p class="page-header__subtitle"><?= $titleSafe ?></p>
    </div>
    <div>
        <a href="/backstage/insights" class="btn btn--outline">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Back to insights
        </a>
    </div>
</div>

<div class="insight-form-panel insight-form-panel--full-height" data-mode="edit" data-insight-id="<?= $idSafe ?>">
    <?php include __DIR__ . '/../_form.php'; ?>
</div>

<link rel="stylesheet" href="/pages/backstage/shared/locale-cards.css">
<link rel="stylesheet" href="/modules/insights/assets/backstage/insight-form.css">
<script src="/pages/backstage/shared/locale-cards.js" defer></script>
<script src="/modules/insights/assets/backstage/insight-form-page.js" defer></script>

<?php
$pageContent = ob_get_clean();
require DAEMS_SITE_PUBLIC . '/pages/backstage/layout.php';
