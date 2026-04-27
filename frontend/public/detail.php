<?php
/**
 * Insight detail page template.
 *
 * Expects $insight array to be in scope (set by index.php router).
 *
 * @package DaemsPublic
 */
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title><?= htmlspecialchars($insight['title']) ?> — Daem Society</title>

        <link rel="shortcut icon" href="/assets/img/brand/daems-favicon.svg" />

        <link rel="stylesheet" href="/assets/css/bootstrap.min.css" />
        <link rel="stylesheet" href="/assets/css/bootstrap-icons.min.css" />
        <link rel="stylesheet" href="/assets/css/daems.css" />
        <link rel="stylesheet" href="/assets/css/daems-search.css" />
    </head>
    <body>

        <?php include __DIR__ . '/../../partials/top-nav.php'; ?>

        <main>
            <?php include __DIR__ . '/detail/hero.php'; ?>
            <?php include __DIR__ . '/detail/content.php'; ?>
            <?php include __DIR__ . '/detail/related.php'; ?>
        </main>

        <?php include __DIR__ . '/../../partials/footer.php'; ?>

        <script src="/assets/js/bootstrap.bundle.min.js"></script>
        <script src="/assets/js/daems.js"></script>
        <script src="/assets/js/daems-search.js"></script>
    </body>
</html>
