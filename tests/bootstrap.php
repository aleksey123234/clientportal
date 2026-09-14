<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Unit tests may touch $_SESSION without a full HTTP session.
    $_SESSION = [];
}
