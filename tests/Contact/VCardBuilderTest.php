<?php

namespace App\Tests\Contact;

use App\Contact\SampleContact;
use App\Contact\VCardBuilder;
use App\Entity\Contact;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VCardBuilderTest extends TestCase
{
    private VCardBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new VCardBuilder();
    }

    public function testMinimalContactProducesOnlyTheRequiredProperties(): void
    {
        $vcard = $this->builder->build(new Contact('Ada', 'Lovelace'));

        self::assertSame(
            "BEGIN:VCARD\r\n".
            "VERSION:3.0\r\n".
            "N:Lovelace;Ada;;;\r\n".
            "FN:Ada Lovelace\r\n".
            "END:VCARD\r\n",
            $vcard,
        );
    }

    public function testFullContactProducesEveryPropertyInAFixedOrder(): void
    {
        $vcard = $this->builder->build(SampleContact::create());

        self::assertSame(
            "BEGIN:VCARD\r\n".
            "VERSION:3.0\r\n".
            "N:Lovelace;Ada;;;\r\n".
            "FN:Ada Lovelace\r\n".
            "ORG:Babbage\\, Lovelace\\; Partners\r\n".
            "TITLE:Analyst\r\n".
            "TEL;TYPE=CELL:+44 20 7946 0000\r\n".
            "EMAIL;TYPE=INTERNET:ada@example.com\r\n".
            "ADR;TYPE=HOME:;;12 St James's Square;London;;SW1Y 4JH;United Kingdom\r\n".
            "BDAY:1815-12-10\r\n".
            "END:VCARD\r\n",
            $vcard,
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function escapeCases(): iterable
    {
        yield 'comma' => ['a,b', 'a\\,b'];
        yield 'semicolon' => ['a;b', 'a\\;b'];
        yield 'backslash' => ['a\\b', 'a\\\\b'];
        yield 'LF' => ["line1\nline2", 'line1\\nline2'];
        yield 'CRLF' => ["line1\r\nline2", 'line1\\nline2'];
        yield 'non-ASCII' => ['Gökşu', 'Gökşu'];
    }

    #[DataProvider('escapeCases')]
    public function testEscapeFollowsRfc2426(string $input, string $expected): void
    {
        self::assertSame($expected, VCardBuilder::escape($input));
    }

    public function testALineBreakInsideAValueCannotStartANewProperty(): void
    {
        // Without escaping, this street would inject a second EMAIL line into the card.
        $contact = (new Contact('Ada', 'Lovelace'))->setStreet("Main St\r\nEMAIL:attacker@example.com");

        $lines = explode("\r\n", $this->builder->build($contact));

        foreach ($lines as $line) {
            self::assertStringStartsNotWith('EMAIL', $line);
        }
    }

    public function testBlankOptionalFieldsAreLeftOut(): void
    {
        $contact = (new Contact('Ada', 'Lovelace'))->setEmail('   ')->setPhone('')->setCity(null);

        $vcard = $this->builder->build($contact);

        self::assertStringNotContainsString('EMAIL', $vcard);
        self::assertStringNotContainsString('TEL', $vcard);
        self::assertStringNotContainsString('ADR', $vcard);
    }

    public function testEveryLineEndsWithCrlf(): void
    {
        $vcard = $this->builder->build(SampleContact::create());

        self::assertStringEndsWith("\r\n", $vcard);
        self::assertStringNotContainsString("\n", str_replace("\r\n", '', $vcard));
    }
}
