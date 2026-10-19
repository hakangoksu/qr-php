<?php

namespace App\Tests\Contact;

use App\Entity\Contact;

/**
 * An example contact for the tests. The comma and semicolon in the organization are on purpose.
 */
final class SampleContact
{
    public static function create(): Contact
    {
        return (new Contact('Ada', 'Lovelace'))
            ->setEmail('ada@example.com')
            ->setPhone('+44 20 7946 0000')
            ->setBirthday(new \DateTimeImmutable('1815-12-10'))
            ->setStreet("12 St James's Square")
            ->setPostalCode('SW1Y 4JH')
            ->setCity('London')
            ->setCountry('United Kingdom')
            ->setOrganization('Babbage, Lovelace; Partners')
            ->setJobTitle('Analyst');
    }
}
