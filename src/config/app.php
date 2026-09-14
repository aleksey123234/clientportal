<?php

/**
 * Application-wide constants.
 *
 * NOTE: This file is NOT currently required by index.php or any controller.
 * Session is started in public/index.php, and .env is loaded there via phpdotenv.
 * If you need these constants, add `require_once __DIR__ . '/../config/app.php';`
 * to the file that needs them.
 *
 * @see public/index.php   — front-controller & session_start()
 * @see src/config/database.php — PDO connection via .env
 */

// Environment settings
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');
define('APP_DEBUG', ($_ENV['APP_DEBUG'] ?? 'true') === 'true');

// Application constants
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Client Portal');
define('APP_VERSION', '1.0.0');

// Base URL
define('BASE_URL', $_ENV['APP_URL'] ?? 'http://localhost');

// Timezone is set in public/index.php via App\Support\AppTimezone (APP_TIMEZONE).
// Do not call date_default_timezone_set here — this file is not loaded by the front controller.
