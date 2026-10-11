<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Finance\Application\UseCases;

use App\Application\Exception\ValidationException;
use App\Modules\Finance\Application\UseCases\ProcessBankImport\BankImportProcessRequest;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BankImportProcessRequest::class)]
final class BankImportProcessRequestTest extends TestCase
{
    #[Test]
    public function itCreatesRequestFromValidArray(): void
    {
        $post = [
            'temp_file' => ' /tmp/bank.csv ',
            'col_id' => '1',
            'col_amount' => '3',
            'col_date' => '0',
            'col_sender' => '2',
            'col_currency' => '4',
        ];

        $request = BankImportProcessRequest::fromArray($post);

        $this->assertSame('/tmp/bank.csv', $request->tempFile);
        $this->assertSame(1, $request->idColumn);
        $this->assertSame(3, $request->amountColumn);
        $this->assertSame(0, $request->dateColumn);
        $this->assertSame(2, $request->senderColumn);
        $this->assertSame(4, $request->currencyColumn);
    }

    #[Test]
    #[DataProvider('invalidPostProvider')]
    public function itThrowsExceptionOnInvalidFile(array $post): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Temporäre Importdatei nicht angegeben oder ungültig.');

        BankImportProcessRequest::fromArray($post);
    }

    public static function invalidPostProvider(): Iterator
    {
        yield 'missing file' => [['col_id' => 1]];
        yield 'empty file' => [['temp_file' => '  ']];
        yield 'wrong extension' => [['temp_file' => 'bank.txt']];
    }
}
