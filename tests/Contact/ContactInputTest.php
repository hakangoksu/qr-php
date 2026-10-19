<?php

namespace App\Tests\Contact;

use App\Contact\ContactInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The validation rules alone, no database.
 */
final class ContactInputTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        // A validator that reads the #[Assert\...] attributes, like the form does.
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    private static function valid(): ContactInput
    {
        $input = new ContactInput();
        $input->firstName = 'Ada';
        $input->lastName = 'Lovelace';
        $input->email = 'ada@example.com';

        return $input;
    }

    /**
     * @return list<string> the messages for one field
     */
    private function errorsFor(ContactInput $input, string $field): array
    {
        $messages = [];
        foreach ($this->validator->validate($input) as $violation) {
            if ($violation->getPropertyPath() === $field) {
                $messages[] = (string) $violation->getMessage();
            }
        }

        return $messages;
    }

    public function testAValidContactHasNoErrors(): void
    {
        self::assertCount(0, $this->validator->validate(self::valid()));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function blankValues(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
    }

    #[DataProvider('blankValues')]
    public function testFirstNameIsRequired(?string $firstName): void
    {
        $input = self::valid();
        $input->firstName = $firstName;

        self::assertContains('Enter a first name.', $this->errorsFor($input, 'firstName'));
    }

    public function testLastNameIsRequired(): void
    {
        $input = self::valid();
        $input->lastName = null;

        self::assertContains('Enter a last name.', $this->errorsFor($input, 'lastName'));
    }

    public function testNamesLongerThan100CharactersAreRejected(): void
    {
        $input = self::valid();
        $input->firstName = str_repeat('a', 101);

        self::assertContains('First name can be at most 100 characters.', $this->errorsFor($input, 'firstName'));
    }

    public function testEmailOrPhoneIsRequired(): void
    {
        $input = self::valid();
        $input->email = null;
        $input->phone = '  ';

        $message = 'Enter an email address or a phone number, or both.';
        self::assertContains($message, $this->errorsFor($input, 'email'));
        self::assertContains($message, $this->errorsFor($input, 'phone'));
    }

    public function testPhoneAloneIsEnough(): void
    {
        $input = self::valid();
        $input->email = null;
        $input->phone = '+370 600 12345';

        self::assertCount(0, $this->validator->validate($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedEmails(): iterable
    {
        yield 'no at sign' => ['not-an-email'];
        yield 'no domain' => ['ada@'];
        yield 'no local part' => ['@example.com'];
    }

    #[DataProvider('malformedEmails')]
    public function testMalformedEmailIsRejected(string $email): void
    {
        $input = self::valid();
        $input->email = $email;

        self::assertNotEmpty($this->errorsFor($input, 'email'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function commonPhones(): iterable
    {
        yield 'Lithuania' => ['+370 600 12345'];
        yield 'United Kingdom' => ['+44 20 7946 0000'];
        yield 'Germany, local' => ['(030) 1234-5678'];
        yield 'France, dots' => ['0601.23.45.67'];
        yield 'United States' => ['+1 (555) 010-9999'];
    }

    #[DataProvider('commonPhones')]
    public function testCommonPhoneFormatsAreAccepted(string $phone): void
    {
        $input = self::valid();
        $input->phone = $phone;

        self::assertSame([], $this->errorsFor($input, 'phone'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPhones(): iterable
    {
        yield '6 digits' => ['123456'];
        yield '16 digits' => ['1234567890123456'];
        yield 'a letter' => ['+370 600 1234x'];
        yield 'two plus signs' => ['++370600123456'];
        yield 'words' => ['call me'];
    }

    #[DataProvider('invalidPhones')]
    public function testInvalidPhoneNumbersAreRejected(string $phone): void
    {
        $input = self::valid();
        $input->phone = $phone;

        self::assertNotEmpty($this->errorsFor($input, 'phone'));
    }

    public function testBirthdayInTheFutureIsRejected(): void
    {
        $input = self::valid();
        $input->birthday = new \DateTimeImmutable('tomorrow');

        self::assertContains('Birthday cannot be in the future.', $this->errorsFor($input, 'birthday'));
    }

    public function testBirthdayBefore1900IsRejected(): void
    {
        $input = self::valid();
        $input->birthday = new \DateTimeImmutable('1899-12-31');

        self::assertNotEmpty($this->errorsFor($input, 'birthday'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function commonPostalCodes(): iterable
    {
        yield 'UK' => ['SW1Y 4JH'];
        yield 'Lithuania' => ['LT-44001'];
        yield 'Germany' => ['10115'];
    }

    #[DataProvider('commonPostalCodes')]
    public function testCommonPostalCodesAreAccepted(string $postalCode): void
    {
        $input = self::valid();
        $input->postalCode = $postalCode;

        self::assertCount(0, $this->validator->validate($input));
    }

    public function testPostalCodeWithSymbolsIsRejected(): void
    {
        $input = self::valid();
        $input->postalCode = '<script>';

        self::assertNotEmpty($this->errorsFor($input, 'postalCode'));
    }

    public function testCrossFieldRuleRunsTogetherWithTheFieldRules(): void
    {
        // Symfony runs every rule at once, so an empty form shows all messages together.
        $input = new ContactInput();

        self::assertNotEmpty($this->errorsFor($input, 'firstName'));
        self::assertContains('Enter an email address or a phone number, or both.', $this->errorsFor($input, 'email'));
    }
}
