<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    public function testStripsScriptTags(): void
    {
        $out = HtmlSanitizer::clean('<p>Hi</p><script>alert(1)</script>');
        $this->assertStringContainsString('Hi', $out);
        $this->assertStringNotContainsString('<script', strtolower($out));
        $this->assertStringNotContainsString('alert', $out);
    }

    public function testStripsEventHandlers(): void
    {
        $out = HtmlSanitizer::clean('<img src=x onerror="alert(1)"><p onclick="evil()">ok</p>');
        $this->assertStringNotContainsString('onerror', strtolower($out));
        $this->assertStringNotContainsString('onclick', strtolower($out));
        $this->assertStringContainsString('ok', $out);
    }

    public function testStripsJavascriptHref(): void
    {
        $out = HtmlSanitizer::clean('<a href="javascript:alert(1)">x</a>');
        $this->assertStringNotContainsString('javascript:', strtolower($out));
    }

    public function testKeepsSafeParagraphAndHttpsLink(): void
    {
        $out = HtmlSanitizer::clean('<p>Hello <a href="https://example.com">link</a></p>');
        $this->assertStringContainsString('<p>', $out);
        $this->assertStringContainsString('https://example.com', $out);
        $this->assertStringContainsString('Hello', $out);
    }
}
