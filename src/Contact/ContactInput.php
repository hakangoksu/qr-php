<?php

namespace App\Contact;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The data a user sends to create a contact. Kept apart from the Contact entity,
 * which is what we store.
 */
final class ContactInput
{
    // Optional +, then 7 to 15 digits. Spaces, brackets, dots, slashes and hyphens are allowed.
    // Example: "+370 600 12345"
    public const PHONE_PATTERN = '#^\+?(?:[ ()./-]*\d){7,15}[ ()./-]*$#';

    // Letters, digits, spaces and hyphens, like "LT-44001" or "SW1Y 4JH".
    public const POSTAL_CODE_PATTERN = '/^[\p{L}\d][\p{L}\d -]{0,19}$/u';

    #[Assert\NotBlank(message: 'Enter a first name.', normalizer: 'trim')]
    #[Assert\Length(max: 100, maxMessage: 'First name can be at most {{ limit }} characters.')]
    public ?string $firstName = null;

    #[Assert\NotBlank(message: 'Enter a last name.', normalizer: 'trim')]
    #[Assert\Length(max: 100, maxMessage: 'Last name can be at most {{ limit }} characters.')]
    public ?string $lastName = null;

    #[Assert\Email(message: 'Enter an email address like name@example.com.')]
    #[Assert\Length(max: 254, maxMessage: 'Email can be at most {{ limit }} characters.')]
    public ?string $email = null;

    #[Assert\Regex(pattern: self::PHONE_PATTERN, message: 'Enter a phone number with 7 to 15 digits, for example +370 600 12345.')]
    #[Assert\Length(max: 32, maxMessage: 'Phone can be at most {{ limit }} characters.')]
    public ?string $phone = null;

    #[Assert\LessThanOrEqual('today', message: 'Birthday cannot be in the future.')]
    #[Assert\GreaterThanOrEqual('1900-01-01', message: 'Birthday cannot be before 1900-01-01.')]
    public ?\DateTimeImmutable $birthday = null;

    #[Assert\Length(max: 200, maxMessage: 'Street can be at most {{ limit }} characters.')]
    public ?string $street = null;

    #[Assert\Regex(pattern: self::POSTAL_CODE_PATTERN, message: 'Postal code can contain letters, digits, spaces and hyphens.')]
    #[Assert\Length(max: 20, maxMessage: 'Postal code can be at most {{ limit }} characters.')]
    public ?string $postalCode = null;

    #[Assert\Length(max: 100, maxMessage: 'City can be at most {{ limit }} characters.')]
    public ?string $city = null;

    #[Assert\Length(max: 100, maxMessage: 'Country can be at most {{ limit }} characters.')]
    public ?string $country = null;

    #[Assert\Length(max: 150, maxMessage: 'Organization can be at most {{ limit }} characters.')]
    public ?string $organization = null;

    #[Assert\Length(max: 100, maxMessage: 'Job title can be at most {{ limit }} characters.')]
    public ?string $jobTitle = null;

    /**
     * A vCard with no way to reach the person is useless, so email or phone is required.
     * The database has the same rule as a CHECK constraint.
     */
    #[Assert\Callback]
    public function validateEmailOrPhone(ExecutionContextInterface $context): void
    {
        if (self::isBlank($this->email) && self::isBlank($this->phone)) {
            foreach (['email', 'phone'] as $field) {
                $context->buildViolation('Enter an email address or a phone number, or both.')
                    ->atPath($field)
                    ->addViolation();
            }
        }
    }

    private static function isBlank(?string $value): bool
    {
        return null === $value || '' === trim($value);
    }
}
