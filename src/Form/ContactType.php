<?php

namespace App\Form;

use App\Contact\ContactInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'First name', 'attr' => ['autocomplete' => 'given-name']])
            ->add('lastName', TextType::class, ['label' => 'Last name', 'attr' => ['autocomplete' => 'family-name']])
            ->add('email', EmailType::class, ['label' => 'Email', 'attr' => ['autocomplete' => 'email']])
            ->add('phone', TelType::class, ['label' => 'Phone', 'attr' => ['autocomplete' => 'tel', 'placeholder' => '+370 600 12345']])
            ->add('birthday', DateType::class, [
                'label' => 'Birthday',
                // One date input instead of three dropdowns.
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'invalid_message' => 'Enter a valid date.',
            ])
            ->add('organization', TextType::class, ['label' => 'Organization', 'attr' => ['autocomplete' => 'organization']])
            ->add('jobTitle', TextType::class, ['label' => 'Job title', 'attr' => ['autocomplete' => 'organization-title']])
            ->add('street', TextType::class, ['label' => 'Street and number', 'attr' => ['autocomplete' => 'address-line1']])
            ->add('postalCode', TextType::class, ['label' => 'Postal code', 'attr' => ['autocomplete' => 'postal-code']])
            ->add('city', TextType::class, ['label' => 'City', 'attr' => ['autocomplete' => 'address-level2']])
            ->add('country', TextType::class, ['label' => 'Country', 'attr' => ['autocomplete' => 'country-name']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactInput::class,
            // No HTML "required": the error messages come from the server, not the browser.
            'required' => false,
        ]);
    }
}
