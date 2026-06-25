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

    public function testLegacyPlainTextDescriptionIsConvertedIntoParagraphs(): void
    {
        $input = <<<'TEXT'
Em "A Casa do Penhasco", o espírito Antônio Carlos constrói uma narrativa eletrizante que mistura suspense cotidiano com profundas lições doutrinárias. A história gira em torno de uma família comum que, atraída pelo valor excessivamente barato do aluguel, decide se mudar para uma imponente residência isolada, conhecida exatamente como a Casa do Penhasco.  Pouco tempo após a mudança, o lar é tomado por acontecimentos estranhos, inexplicáveis e profundamente assustadores. O alvo principal dessa perseguição invisível e maléfica é o filho do casal. Sem entender a origem dos fenômenos perturbadores que ameaçam a sanidade e a segurança do menino, os pais, em completo desespero, são conduzidos a buscar respostas e auxílio nos fundamentos do Espiritismo.  A partir daí, a obra descortina os bastidores magnéticos da obsessão espiritual e os resgates de vidas passadas, demonstrando como o plano espiritual inferior se aproveita de construções impregnadas de fluidos densos para agir. Acima de tudo, o livro foca no poder da prece, da evangelização e do tratamento mediúnico sério como os únicos caminhos reais para desarmar as forças das sombras e devolver a paz ao ambiente doméstico.

✨ Por que vale a pena ler este romance de Vera Lúcia?Se você gosta de histórias dinâmicas que prendem a atenção do início ao fim com um clima de mistério, mas que não abrem mão do compromisso doutrinário, este livro é uma excelente escolha. O espírito Antônio Carlos possui uma linguagem muito visual e direta, ideal para explicar como funcionam as influências espirituais em nossa própria casa.  "A Casa do Penhasco" funciona como um grande alerta sobre a importância de cuidarmos da atmosfera psíquica do nosso lar através de bons pensamentos e atitudes equilibradas. Uma leitura emocionante, envolvente e repleta de ensinamentos práticos para blindar a nossa família contra as correntes da invigilância!
TEXT;
        $result = BookshopDescriptionSanitizer::sanitize($input);

        $this->assertStringContainsString('<p>Em &quot;A Casa do Penhasco&quot;', $result['content']);
        $this->assertStringContainsString('<p>Pouco tempo após a mudança', $result['content']);
        $this->assertStringContainsString('<p>A partir daí, a obra descortina', $result['content']);
        $this->assertStringContainsString('<p>Por que vale a pena ler este romance de Vera Lúcia?</p>', $result['content']);
        $this->assertStringContainsString('<p>Se você gosta de histórias dinâmicas', $result['content']);
        $this->assertStringContainsString('<p>&quot;A Casa do Penhasco&quot; funciona como um grande alerta', $result['content']);
        $this->assertStringContainsString('<p>Uma leitura emocionante, envolvente', $result['content']);
        $this->assertStringNotContainsString('✨ Por que vale', $result['content']);
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
