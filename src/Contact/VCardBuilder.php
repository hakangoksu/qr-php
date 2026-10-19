<?php

namespace App\Contact;

use App\Entity\Contact;

/**
 * Builds vCard 3.0 text (RFC 2426). Same output, byte for byte, as VCard.Build in the .NET app.
 */
final class VCardBuilder
{
    // RFC 2426 requires CRLF line endings.
    private const LINE_BREAK = "\r\n";

    public function build(Contact $contact): string
    {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            // N is structured: family name; given name; additional names; prefixes; suffixes.
            sprintf('N:%s;%s;;;', self::escape($contact->getLastName()), self::escape($contact->getFirstName())),
            'FN:'.self::escape($contact->getFirstName().' '.$contact->getLastName()),
        ];

        self::addIfPresent($lines, 'ORG', $contact->getOrganization());
        self::addIfPresent($lines, 'TITLE', $contact->getJobTitle());
        self::addIfPresent($lines, 'TEL;TYPE=CELL', $contact->getPhone());
        self::addIfPresent($lines, 'EMAIL;TYPE=INTERNET', $contact->getEmail());

        if (self::hasAddress($contact)) {
            // ADR is structured: PO box; extended address; street; city; region; postal code; country.
            $lines[] = sprintf(
                'ADR;TYPE=HOME:;;%s;%s;;%s;%s',
                self::escape($contact->getStreet()),
                self::escape($contact->getCity()),
                self::escape($contact->getPostalCode()),
                self::escape($contact->getCountry()),
            );
        }

        if (null !== $contact->getBirthday()) {
            $lines[] = 'BDAY:'.$contact->getBirthday()->format('Y-m-d');
        }

        $lines[] = 'END:VCARD';

        return implode(self::LINE_BREAK, $lines).self::LINE_BREAK;
    }

    /**
     * RFC 2426 section 4: backslash, comma and semicolon are escaped with a backslash,
     * and a line break inside a value is written as the two characters \n.
     */
    public static function escape(?string $value): string
    {
        return strtr($value ?? '', [
            '\\' => '\\\\',
            ',' => '\\,',
            ';' => '\\;',
            "\r\n" => '\\n',
            "\n" => '\\n',
            "\r" => '\\n',
        ]);
    }

    /**
     * @param list<string> $lines
     */
    private static function addIfPresent(array &$lines, string $property, ?string $value): void
    {
        if (null !== $value && '' !== trim($value)) {
            $lines[] = $property.':'.self::escape(trim($value));
        }
    }

    private static function hasAddress(Contact $contact): bool
    {
        foreach ([$contact->getStreet(), $contact->getPostalCode(), $contact->getCity(), $contact->getCountry()] as $part) {
            if (null !== $part && '' !== trim($part)) {
                return true;
            }
        }

        return false;
    }
}
