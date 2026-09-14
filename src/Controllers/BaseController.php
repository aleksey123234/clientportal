<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Base controller — shared logic for all portal controllers.
 *
 * Provides:
 *   - Lazy-loaded PDO connection via $this->db()
 *   - View rendering via $this->render(file, vars)
 *
 * Every controller in src/controllers/ should extend this class
 * instead of duplicating the database and render boilerplate.
 *
 * @see src/config/database.php  — PDO connection setup
 */
abstract class BaseController
{
    /** @var \PDO|null Lazy-loaded database connection */
    private ?\PDO $pdo = null;

    /**
     * Get the PDO database connection (lazy-loaded on first call).
     * The connection is created once and reused for the lifetime of the request.
     */
    protected function db(): \PDO
    {
        if ($this->pdo === null) {
            require_once __DIR__ . '/../config/database.php';
            $this->pdo = $pdo;
        }
        return $this->pdo;
    }

    /**
     * Render a view file with extracted variables.
     *
     * @param  string $file  Absolute path to the view PHP file
     * @param  array  $vars  Associative array of variables to inject into the view
     * @return string        The rendered HTML output
     */
    protected function render(string $file, array $vars = []): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require $file;
        return ob_get_clean();
    }
}
