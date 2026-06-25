<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\BookshopDescriptionSanitizer;
use PHPUnit\Framework\TestCase;

final class BookshopDescriptionSanitizerTest extends TestCase
{
    public function testEmptyDescriptionIsAllowed(): void
    {
        $result = BookshopDescriptionSanitizer::sanitize('');
        $this->assertSame('', $result['content']);
        $this->assertNull($result['error']);
    }

    public function testSimpleTextIsSanitized(): void
    {
        $input = 'This is a simple description.';
        $result = BookshopDescriptionSanitizer::sanitize($input);
        $this->assertSame('This is a simple description.', $result['content']);
        $this->assertNull($result['error']);
    }

    public function testAllowedHtmlTagsAreKept(): void
    {
        $input = '<p>Bold text: <strong>Important</strong></p>';
        $result = BookshopDescriptionSanitizer::sanitize($input);
        $this->assertStringContainsString('<strong>Important</strong>', $result['content']);
        $this->assertNull($result['error']);
    }

    public function testDangerousScriptTagsAreRemoved(): void
    {
        $input = '<p>Hello</p><script>alert("XSS")</script><p>World</p>';
        $result = BookshopDescriptionSanitizer::sanitize($input);
        $this->assertStringNotContainsString('script', $result['content']);
        $this->assertStringNotContainsString('alert', $result['content']);
        $this->assertNull($result['error']);
    }

    public function testOnAttributesAreRemoved(): void
    {
        $input = '<p onclick="alert(1)">Click me</p>';
        $result = BookshopDescriptionSanitizer::sanitize($input);
        $this->assertStringNotContainsString('onclick', $result['content']);
        $this->assertNull($result['error']);
    }

    public function testJavascriptProtocolIsRemoved(): void
    {
        $input = '<a href="javascript:alert(1)">Click</a>';
        $result = BookshopDescriptionSanitizer::sanitize($input);
        $this->assertStringNotContainsString('javascript:', $result['content']);
        $this->assertNull($result['error']);
    }

    public function testTooLongDescriptionReturnsError(): void
    {
        $input = str_repeat('a', 5001);
        $result = BookshopDescriptionSanitizer::sanitize($input);
        $this->assertSame('', $result['content']);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('5000', $result['error']);
    }

    public function testMaxLengthDescriptionIsAllowed(): void
    {
        $input = str_repeat('a', 5000);
        $result = BookshopDescriptionSanitizer::sanitize($input);
        $this->assertSame($input, $result['content']);
        $this->assertNull($result['error']);
    }

    public function testComplexValidHtmlIsPreserved(): void
    {
        $input = <<<'HTML'
<p>Introduction paragraph</p>
<ul>
<li>First point</li>
<li>Second point</li>
</ul>
<blockquote>A quote</blockquote>
<p><strong>Bold</strong> and <em>italic</em> text</p>
HTML;
        $result = BookshopDescriptionSanitizer::sanitize($input);
        $this->assertStringContainsString('<p>', $result['content']);
        $this->assertStringContainsString('<ul>', $result['content']);
        $this->assertStringContainsString('<blockquote>', $result['content']);
        $this->assertNull($result['error']);
    }

    public function testWhitespaceIsTrimmed(): void
    {
        $input = '   <p>Text</p>   ';
        $result = BookshopDescriptionSanitizer::sanitize($input);
        $this->assertStringStartsWith('<p>', $result['content']);
        $this->assertStringEndsWith('</p>', $result['content']);
        $this->assertNull($result['error']);
    }

    public function testUnallowedTagsAreStripped(): void
    {
        $input = '<p>Hello</p><div class="danger">Evil</div><p>World</p>';
        $result = BookshopDescriptionSanitizer::sanitize($input);
        $this->assertStringNotContainsString('div', strtolower($result['content']));
        $this->assertStringContainsString('Hello', $result['content']);
        $this->assertStringContainsString('World', $result['content']);
        $this->assertNull($result['error']);
    }

    public function testGetMaxLength(): void
    {
        $maxLength = BookshopDescriptionSanitizer::getMaxLength();
        $this->assertSame(5000, $maxLength);
    }

    public function testGetAllowedTagsDoc(): void
    {
        $doc = BookshopDescriptionSanitizer::getAllowedTagsDoc();
        $this->assertStringContainsString('<p>', $doc);
        $this->assertStringContainsString('<strong>', $doc);
        $this->assertStringContainsString('permitidas', $doc);
    }
}
