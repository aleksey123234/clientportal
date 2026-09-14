<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Portal CTA block for inbox emails, driven by service_email_history.action_section.
 * CRM sets the column when inserting; Portal only renders it.
 */
class ServiceEmailCta
{
    private const MAP = [
        'profile' => [
            'text' => 'Please change this information in your profile',
            'href' => '/profile',
            'label' => 'Profile',
        ],
        'cif' => [
            'text' => 'Please fill in the missing information in your CIF',
            'href' => '/cif',
            'label' => 'CIF',
        ],
        'documents' => [
            'text' => 'Please upload the missing documents in your Documents',
            'href' => '/documents',
            'label' => 'Documents',
        ],
    ];

    /**
     * HTML CTA block, or empty string when section is null / unknown / Custom.
     */
    public static function html(?string $actionSection): string
    {
        if ($actionSection === null || $actionSection === '' || !isset(self::MAP[$actionSection])) {
            return '';
        }
        $m = self::MAP[$actionSection];
        $text = htmlspecialchars($m['text'], ENT_QUOTES, 'UTF-8');
        $href = htmlspecialchars($m['href'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8');

        return '<div class="email-action-cta mt-4 pt-3 border-top">'
            . '<p class="mb-1">' . $text . '</p>'
            . '<p class="mb-0"><a href="' . $href . '">' . $label . '</a></p>'
            . '</div>';
    }

    /**
     * Append CTA to email body HTML when action_section is set.
     */
    public static function appendToBody(string $body, ?string $actionSection): string
    {
        $cta = self::html($actionSection);
        if ($cta === '') {
            return $body;
        }
        return $body . $cta;
    }
}
