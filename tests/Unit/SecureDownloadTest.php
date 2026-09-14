<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SecureDownload;
use PHPUnit\Framework\TestCase;

final class SecureDownloadTest extends TestCase
{
    /** @var string */
    private $base;

    /** @var string */
    private $insideFile;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'portal_sd_' . bin2hex(random_bytes(4));
        mkdir($this->base, 0777, true);
        $this->insideFile = $this->base . DIRECTORY_SEPARATOR . 'doc.pdf';
        file_put_contents($this->insideFile, '%PDF-test');
    }

    protected function tearDown(): void
    {
        if (is_file($this->insideFile)) {
            @unlink($this->insideFile);
        }
        if (is_dir($this->base)) {
            @rmdir($this->base);
        }
    }

    public function testResolveRelativeUnderBase(): void
    {
        $resolved = SecureDownload::resolveUnderBase($this->base, 'doc.pdf');
        $this->assertNotNull($resolved);
        $this->assertSame(realpath($this->insideFile), $resolved);
    }

    public function testResolveAbsoluteUnderBase(): void
    {
        $resolved = SecureDownload::resolveUnderBase($this->base, $this->insideFile);
        $this->assertNotNull($resolved);
    }

    public function testRejectsPathEscape(): void
    {
        $outside = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'portal_sd_out_' . bin2hex(random_bytes(4));
        file_put_contents($outside, 'x');
        try {
            $this->assertNull(SecureDownload::resolveUnderBase($this->base, $outside));
            $this->assertNull(SecureDownload::resolveUnderBase($this->base, '../' . basename($outside)));
        } finally {
            @unlink($outside);
        }
    }

    public function testSafeContentDispositionStripsControlChars(): void
    {
        $h = SecureDownload::safeContentDisposition("evil\r\nName\".pdf", 'attachment');
        $this->assertStringNotContainsString("\r", $h);
        $this->assertStringNotContainsString("\n", $h);
        $this->assertStringStartsWith('attachment; filename="', $h);
        $this->assertMatchesRegularExpression('/filename="[^"]+"/', $h);
        $this->assertStringContainsString('evil', $h);
    }

    public function testSafeContentDispositionInlineAndFallback(): void
    {
        $this->assertStringStartsWith('inline; filename="', SecureDownload::safeContentDisposition('a.pdf', 'inline'));
        $this->assertStringContainsString('filename="download"', SecureDownload::safeContentDisposition("\r\n", 'attachment'));
    }
}
