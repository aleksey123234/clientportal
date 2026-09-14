<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * FAQ page controller.
 *
 * Routes (dispatched by public/index.php):
 *   GET /faq  — render FAQ page with 11 sections, 50+ Q&As
 *
 * All FAQ content is static HTML inside the view file.
 * Client-side: keyword search (debounced), multi-select filter pills, visible count.
 *
 * @see src/views/faq/faq-page.php  — FAQ view (sections, accordions, search)
 * @see public/js/faq.js            — search, multi-filter pills, visible count
 */
class FaqController extends BaseController
{
    public function index(): void
    {
        $pageTitle  = 'FAQ';
        $activePage = 'faq';

        $content = $this->render(
            __DIR__ . '/../views/faq/faq-page.php',
            compact('pageTitle', 'activePage')
        );

        require __DIR__ . '/../views/layouts/main.php';
    }
}
