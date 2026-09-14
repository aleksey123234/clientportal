<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Allowlist HTML sanitizer for CRM/inbox email bodies shown via innerHTML.
 */
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p' => true,
        'br' => true,
        'div' => true,
        'span' => true,
        'a' => true,
        'strong' => true,
        'em' => true,
        'b' => true,
        'i' => true,
        'ul' => true,
        'ol' => true,
        'li' => true,
        'h1' => true,
        'h2' => true,
        'h3' => true,
        'table' => true,
        'thead' => true,
        'tbody' => true,
        'tr' => true,
        'th' => true,
        'td' => true,
    ];

    private const DROP_TAGS = [
        'script' => true,
        'style' => true,
        'iframe' => true,
        'object' => true,
        'embed' => true,
        'link' => true,
        'meta' => true,
        'img' => true,
    ];

    public static function clean(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $wrapped = '<div id="sanitize-root">' . $html . '</div>';
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $loaded = @$doc->loadHTML(
            '<?xml encoding="UTF-8">' . $wrapped,
            LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded) {
            return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
        }

        $root = $doc->getElementById('sanitize-root');
        if (!$root) {
            return '';
        }

        self::sanitizeNode($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        if ($node->hasChildNodes()) {
            foreach (iterator_to_array($node->childNodes) as $child) {
                self::sanitizeNode($child);
            }
        }

        if ($node->nodeType === XML_COMMENT_NODE && $node->parentNode) {
            $node->parentNode->removeChild($node);
            return;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        /** @var \DOMElement $node */
        $tag = strtolower($node->tagName);
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }

        if ($tag === 'div' && $node->getAttribute('id') === 'sanitize-root') {
            return;
        }

        if (isset(self::DROP_TAGS[$tag]) || !isset(self::ALLOWED_TAGS[$tag])) {
            if (isset(self::DROP_TAGS[$tag])) {
                $parent->removeChild($node);
                return;
            }
            while ($node->firstChild) {
                $parent->insertBefore($node->firstChild, $node);
            }
            $parent->removeChild($node);
            return;
        }

        if ($node->hasAttributes()) {
            foreach (iterator_to_array($node->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (strpos($name, 'on') === 0) {
                    $node->removeAttribute($attr->name);
                    continue;
                }
                if ($tag === 'a' && $name === 'href') {
                    if (!self::isSafeHref(trim($attr->value))) {
                        $node->removeAttribute($attr->name);
                    }
                    continue;
                }
                if (!($tag === 'a' && $name === 'href')) {
                    $node->removeAttribute($attr->name);
                }
            }
        }
    }

    private static function isSafeHref(string $href): bool
    {
        if ($href === '' || $href[0] === '#') {
            return true;
        }
        if (preg_match('#^(https?://|mailto:)#i', $href)) {
            return true;
        }
        if (preg_match('#^(javascript|data|vbscript):#i', $href)) {
            return false;
        }
        if (preg_match('#^[a-z0-9_./?-]#i', $href) && strpos($href, ':') === false) {
            return true;
        }
        return false;
    }
}
