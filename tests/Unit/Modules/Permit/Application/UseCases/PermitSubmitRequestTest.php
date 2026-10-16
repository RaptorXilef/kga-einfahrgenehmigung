<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Application\UseCases;

use App\Application\Exception\ValidationException;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Application\UseCases\SubmitPermitRequest\PermitSubmitRequest;
use DateTimeImmutable;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermitSubmitRequest::class)]
final class PermitSubmitRequestTest extends TestCase
{
    private function getValidPost(): array
    {
        return [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'parzelle' => '42',
            'kennzeichen' => 'B-XX 123',
            'datum_von' => '2026-10-15',
            'datum_bis' => '2026-10-20',
        ];
    }

    #[Test]
    public function itCreatesRequestFromValidArrayAndSanitizesHtml(): void
    {
        $post = $this->getValidPost();
        $post['firma'] = '  <b>Baufirma</b>  ';

        // Mockt das ClockInterface (anstelle der finalen Klasse)
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-01 12:00:00'));

        $request = PermitSubmitRequest::fromArray($post, $clock);

        $this->assertSame('Max Mustermann', $request->name);
        $this->assertSame('Baufirma', $request->firma);
        $this->assertSame('42', $request->parzelle);
    }

    #[Test]
    #[DataProvider('invalidPostProvider')]
    public function itThrowsExceptionOnInvalidData(array $overrides, string $expectedMessage): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-10 12:00:00'));

        $post = \array_merge($this->getValidPost(), $overrides);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        PermitSubmitRequest::fromArray($post, $clock);
    }

    public static function invalidPostProvider(): Iterator
    {
        yield 'missing name' => [['name' => '  '], 'Bitte geben Sie einen Namen ein.'];
        yield 'missing space in name' => [['name' => 'MaxMustermann'], 'Bitte geben Sie Ihren vollständigen Namen ein (Vor- und Nachname durch ein Leerzeichen getrennt).'];
        yield 'invalid email' => [['email' => 'invalid'], 'Die eingegebene E-Mail-Adresse ist ungültig.'];
        yield 'missing plot' => [['parzelle' => ''], 'Bitte geben Sie eine Parzelle an.'];
        yield 'missing dates' => [['datum_von' => ''], 'Bitte geben Sie einen Gültigkeitszeitraum an.'];
        yield 'start in past' => [['datum_von' => '2026-10-09'], 'Das Startdatum darf nicht in der Vergangenheit liegen.'];
        yield 'end before start' => [['datum_von' => '2026-10-15', 'datum_bis' => '2026-10-14'], 'Das Enddatum darf nicht vor dem Startdatum liegen.'];
        yield 'invalid date string' => [['datum_von' => 'Kartoffel'], 'Das eingegebene Datumsformat ist ungültig.'];
        yield 'missing plate for pkw' => [['typ' => 'pkw', 'kennzeichen' => ''], 'Bitte geben Sie Ihr Kfz-Kennzeichen ein.'];
        yield 'implausible plate' => [['kennzeichen' => 'XXX'], 'Bitte geben Sie ein plausibles Kfz-Kennzeichen ein (z.B. B-XX 123).'];
    }

    #[Test]
    public function itAllowsEmptyLicensePlateForNonPkw(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-10 12:00:00'));

        $post = $this->getValidPost();
        $post['typ'] = 'sharing';
        $post['kennzeichen'] = '';

        $request = PermitSubmitRequest::fromArray($post, $clock);

        $this->assertSame('', $request->kennzeichen);
        $this->assertSame('sharing', $request->typ);
    }
}
