<?php

declare(strict_types=1);

namespace App\Tests\Yack\Common\Domain;

use App\Yack\Common\Domain\AiInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AiInput::class)]
final class AiInputTest extends TestCase
{
    public function testText(): void
    {
        $input = AiInput::text('Quelle longueur de liaison ?');

        $this->assertSame(AiInput::TEXT, $input->kind);
        $this->assertSame('Quelle longueur de liaison ?', $input->content);
        $this->assertNull($input->mediaType);
    }

    public function testEmptyTextIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AiInput::text('   ');
    }

    public function testImage(): void
    {
        $input = AiInput::image('png-bytes', 'image/png');

        $this->assertSame(AiInput::IMAGE, $input->kind);
        $this->assertSame('image/png', $input->mediaType);
    }

    public function testOtherImageTypesAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('image/heic');
        AiInput::image('bytes', 'image/heic');
    }

    public function testEmptyImageIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AiInput::image('', 'image/jpeg');
    }

    public function testPdf(): void
    {
        $input = AiInput::pdf("%PDF-1.7\n...");

        $this->assertSame(AiInput::PDF, $input->kind);
        $this->assertSame('application/pdf', $input->mediaType);
    }

    public function testNonPdfIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AiInput::pdf('PK zip file');
    }
}
