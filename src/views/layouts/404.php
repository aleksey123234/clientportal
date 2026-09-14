<?php

use App\Support\Asset;
use App\Support\Html;

$pageTitle = '404 — Page Not Found';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Html::e($pageTitle) ?></title>
    <?= Asset::cdnTag('bootstrap_css') . "\n" ?>
    <?= Asset::cdnTag('bootstrap_icons') . "\n" ?>
</head>

<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light">
    <div class="text-center">
        <i class="bi bi-exclamation-circle text-secondary" style="font-size:5rem;"></i>
        <h1 class="display-4 fw-bold mt-3">404</h1>
        <p class="text-muted mb-4">Page not found.</p>
        <a href="/dashboard" class="btn btn-primary">
            <i class="bi bi-house me-1"></i>Go to Dashboard
        </a>
    </div>
</body>

</html>
