<?php

use App\Support\Asset;
use App\Support\Html;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Html::e($pageTitle ?? 'Client Portal') ?></title>
    <link rel="icon" type="image/png" href="/images/LogoFolded.png">
    <?= Asset::cdnTag('bootstrap_css') . "\n" ?>
    <?= Asset::cdnTag('bootstrap_icons') . "\n" ?>
    <link rel="stylesheet" href="<?= Html::e(Asset::url('/css/auth.css')) ?>">
</head>

<body class="auth-bg d-flex align-items-center justify-content-center min-vh-100">

    <main class="auth-card <?= Html::e($mainClass ?? '') ?>">
        <?= $content ?? '' ?>
    </main>

    <footer class="position-fixed bottom-0 w-100 text-center text-muted small py-2">
        &copy; <?= date('Y') ?> Lev Tiutiunyk (Rimoker). MIT License.
    </footer>

    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="portalToastContainer" style="z-index: 1100;"></div>

    <?= Asset::cdnTag('bootstrap_js', 'js') . "\n" ?>
    <script src="<?= Html::e(Asset::url('/js/toast.js')) ?>"></script>
</body>

</html>
