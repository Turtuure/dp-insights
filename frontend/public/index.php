<?php
/**
 * Insights listing page.
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
        <title>Insights — Daem Society</title>

        <link rel="shortcut icon" href="/assets/img/brand/daems-favicon.svg" />

        <link rel="stylesheet" href="/assets/css/bootstrap.min.css" />
        <link rel="stylesheet" href="/assets/css/bootstrap-icons.min.css" />
        <link rel="stylesheet" href="/assets/css/daems.css" />
        <link rel="stylesheet" href="/assets/css/daems-search.css" />
    </head>
    <body>

        <?php include __DIR__ . '/../../partials/top-nav.php'; ?>

        <main>
            <?php include __DIR__ . '/hero.php'; ?>
            <?php include __DIR__ . '/grid.php'; ?>
            <?php include __DIR__ . '/cta.php'; ?>
        </main>

        <?php include __DIR__ . '/../../partials/footer.php'; ?>

        <script src="/assets/js/bootstrap.bundle.min.js"></script>
        <script src="/assets/js/daems.js"></script>
        <script src="/assets/js/daems-search.js"></script>
    </body>
</html>
