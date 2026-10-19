<?php

namespace App\Tests\Contact;

use App\Contact\QrCodeRenderer;
use App\Contact\VCardBuilder;
use App\Entity\Contact;
use PHPUnit\Framework\TestCase;
use Zxing\QrReader;

final class QrCodeRendererTest extends TestCase
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    public function testRenderPngReturnsASquarePngImage(): void
    {
        $png = (new QrCodeRenderer())->renderPng((new VCardBuilder())->build(SampleContact::create()));

        self::assertSame(self::PNG_SIGNATURE, substr($png, 0, 8));
        // In a PNG, width and height are 4-byte numbers at bytes 16 and 20.
        ['width' => $width, 'height' => $height] = unpack('Nwidth/Nheight', substr($png, 16, 8));
        self::assertSame($width, $height);
        self::assertGreaterThanOrEqual(250, $width);
    }

    public function testEncodedVcardDecodesBackToTheSameText(): void
    {
        $vcard = (new VCardBuilder())->build(SampleContact::create());

        self::assertSame($vcard, $this->decode((new QrCodeRenderer())->renderPng($vcard)));
    }

    public function testNonAsciiNamesSurviveTheRoundTrip(): void
    {
        $vcard = (new VCardBuilder())->build((new Contact('Hakan', 'Göksu'))->setPhone('+370 600 00000'));

        self::assertSame($vcard, $this->decode((new QrCodeRenderer())->renderPng($vcard)));
    }

    /**
     * Reads the PNG back with a separate QR reader (a PHP port of ZXing).
     */
    private function decode(string $png): string
    {
        $text = (new QrReader($png, QrReader::SOURCE_TYPE_BLOB))->text();
        self::assertIsString($text, 'The QR code could not be decoded');

        return $text;
    }
}
