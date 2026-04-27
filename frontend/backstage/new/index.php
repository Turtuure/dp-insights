<?php
declare(strict_types=1);

$u = $_SESSION['user'] ?? null;
$isAdmin = $u && (!empty($u['is_platform_admin']) || ($u['role'] ?? '') === 'admin'
               || ($u['role'] ?? '') === 'global_system_administrator');
if (!$isAdmin) { header('Location: /'); exit; }

$pageTitle   = 'New insight';
$activePage  = 'insights';
$breadcrumbs = [
    ['label' => 'Insights', 'url' => '/backstage/insights'],
    ['label' => 'New'],
];

$insight       = [];
$primary_label = 'Create';
$show_delete   = false;
$contentClass  = 'content--no-scroll';

ob_start();
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title">New insight</h1>
        <p class="page-header__subtitle">Fill in the fields and publish or schedule.</p>
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

<div class="insight-form-panel insight-form-panel--full-height" data-mode="create">
    <?php include __DIR__ . '/../_form.php'; ?>
</div>

<link rel="stylesheet" href="/pages/backstage/insights/insight-form.css">
<script src="/pages/backstage/insights/insight-form-page.js" defer></script>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../../layout.php';
