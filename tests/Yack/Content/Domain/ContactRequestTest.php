<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\ContactRequest;
use App\Yack\Content\Domain\ContactRequestInvalid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ContactRequest::class)]
#[CoversClass(ContactRequestInvalid::class)]
final class ContactRequestTest extends TestCase
{
    public function testSubmitTrimsTheValues(): void
    {
        $request = ContactRequest::submit('  Julie Martin ', ' julie@clim-test.example ', ' Clim Test ', ' 04 42 00 00 00 ', ' Devis PAC ', "  Bonjour,\nje souhaite un devis.  ");

        $this->assertSame('Julie Martin', $request->name);
        $this->assertSame('julie@clim-test.example', $request->email);
        $this->assertSame('Clim Test', $request->company);
        $this->assertSame('04 42 00 00 00', $request->phone);
        $this->assertSame('Devis PAC', $request->subject);
        $this->assertSame("Bonjour,\nje souhaite un devis.", $request->message);
    }

    public function testBlankOptionalValuesBecomeNullAndTheSubjectHasADefault(): void
    {
        $request = ContactRequest::submit('Julie', 'julie@clim-test.example', '  ', null, '', 'Un message assez long.');

        $this->assertNull($request->company);
        $this->assertNull($request->phone);
        $this->assertSame(ContactRequest::DEFAULT_SUBJECT, $request->subject);
    }

    public function testSubjectAndNameStayOnOneLine(): void
    {
        $request = ContactRequest::submit("Julie\r\nBcc: x@example.com", 'julie@clim-test.example', null, null, "Devis\nBcc: x@example.com", 'Un message assez long.');

        $this->assertSame('Julie Bcc: x@example.com', $request->name);
        $this->assertSame('Devis Bcc: x@example.com', $request->subject);
    }

    public function testMessageKeepsItsLineBreaks(): void
    {
        $request = ContactRequest::submit('Julie', 'julie@clim-test.example', null, null, null, "Ligne 1\nLigne 2");

        $this->assertStringContainsString("\n", $request->message);
    }

    #[DataProvider('provideInvalidCases')]
    public function testSubmitRefusesAnInvalidField(string $field, string $message, string $name, string $email, ?string $company, ?string $phone, ?string $subject, string $text): void
    {
        try {
            ContactRequest::submit($name, $email, $company, $phone, $subject, $text);
        } catch (ContactRequestInvalid $invalid) {
            $this->assertSame($field, $invalid->field);
            $this->assertStringContainsString($message, $invalid->getMessage());

            return;
        }

        $this->assertTrue(false, 'ContactRequestInvalid attendue');
    }

    /**
     * @return \Iterator<string, array{string, string, string, string, ?string, ?string, ?string, string}>
     */
    public static function provideInvalidCases(): \Iterator
    {
        $text = 'Un message assez long.';

        yield 'nom vide' => ['name', 'Indiquez votre nom.', "  \n ", 'julie@clim-test.example', null, null, null, $text];
        yield 'nom trop long' => ['name', '120 caractères', str_repeat('a', 121), 'julie@clim-test.example', null, null, null, $text];
        yield 'e-mail vide' => ['email', 'adresse e-mail valide', 'Julie', '  ', null, null, null, $text];
        yield 'e-mail invalide' => ['email', 'adresse e-mail valide', 'Julie', 'julie@', null, null, null, $text];
        yield 'e-mail trop long' => ['email', 'adresse e-mail valide', 'Julie', str_repeat('a', 170) . '@example.com', null, null, null, $text];
        yield 'société trop longue' => ['company', '255 caractères', 'Julie', 'julie@clim-test.example', str_repeat('a', 256), null, null, $text];
        yield 'téléphone trop long' => ['phone', '32 caractères', 'Julie', 'julie@clim-test.example', null, str_repeat('0', 33), null, $text];
        yield 'sujet trop long' => ['subject', '150 caractères', 'Julie', 'julie@clim-test.example', null, null, str_repeat('a', 151), $text];
        yield 'message trop court' => ['message', 'au moins 10 caractères', 'Julie', 'julie@clim-test.example', null, null, null, '  Bonjour  '];
        yield 'message trop long' => ['message', '5000 caractères', 'Julie', 'julie@clim-test.example', null, null, null, str_repeat('a', 5001)];
    }

    public function testLimitsCountCharactersNotBytes(): void
    {
        $request = ContactRequest::submit(str_repeat('é', ContactRequest::NAME_MAX), 'julie@clim-test.example', null, null, null, 'Un message assez long.');

        $this->assertSame(ContactRequest::NAME_MAX, mb_strlen($request->name));
    }
}
