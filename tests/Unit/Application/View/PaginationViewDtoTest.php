<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\View;

use App\Application\View\PaginationPageItemDto;
use App\Application\View\PaginationViewDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaginationViewDto::class)]
#[CoversClass(PaginationPageItemDto::class)]
final class PaginationViewDtoTest extends TestCase
{
    #[Test]
    public function itReturnsEmptyDtoWhenOnlyOnePageExists(): void
    {
        $dto = PaginationViewDto::fromParameters(1, 10, 25, []);

        $this->assertFalse($dto->hasMultiplePages);
        $this->assertTrue($dto->hasEntries);
        $this->assertSame(1, $dto->startEntry);
        $this->assertSame(10, $dto->endEntry);
        $this->assertEmpty($dto->items);
    }

    #[Test]
    public function itReturnsEmptyDtoWhenNoEntriesExist(): void
    {
        $dto = PaginationViewDto::fromParameters(1, 0, 25, []);

        $this->assertFalse($dto->hasMultiplePages);
        $this->assertFalse($dto->hasEntries);
        $this->assertSame(0, $dto->startEntry);
        $this->assertSame(0, $dto->endEntry);
    }

    #[Test]
    public function itGeneratesCorrectPageNeighborsForSmallPageCount(): void
    {
        // 5 Seiten, aktuelle Seite 3
        $dto = PaginationViewDto::fromParameters(3, 50, 10, []);

        $this->assertTrue($dto->hasMultiplePages);
        $this->assertTrue($dto->hasPrev);
        $this->assertTrue($dto->hasNext);

        $this->assertSame('?page=2', $dto->prevUrl);
        $this->assertSame('?page=4', $dto->nextUrl);
        $this->assertCount(5, $dto->items); // 1, 2, 3, 4, 5

        // Prüfe, ob Seite 3 als isCurrent markiert ist
        $this->assertTrue($dto->items[2]->isCurrent);
        $this->assertSame('3', $dto->items[2]->label);
    }

    #[Test]
    public function itGeneratesEllipsisWhenOnFirstPageOfMany(): void
    {
        // 100 Seiten, aktuelle Seite 1
        $dto = PaginationViewDto::fromParameters(1, 1000, 10, []);

        $this->assertFalse($dto->hasPrev);
        $this->assertTrue($dto->hasNext);

        // Items: 1, 2, 3, 4, 5, ..., 100
        $this->assertCount(7, $dto->items);
        $this->assertSame('...', $dto->items[5]->label);
        $this->assertTrue($dto->items[5]->isEllipsis);
        $this->assertSame('100', $dto->items[6]->label);
    }

    #[Test]
    public function itGeneratesEllipsisWhenOnLastPageOfMany(): void
    {
        // 100 Seiten, aktuelle Seite 100
        $dto = PaginationViewDto::fromParameters(100, 1000, 10, []);

        $this->assertTrue($dto->hasPrev);
        $this->assertFalse($dto->hasNext);

        // Items: 1, ..., 96, 97, 98, 99, 100
        $this->assertCount(7, $dto->items);
        $this->assertSame('...', $dto->items[1]->label);
        $this->assertTrue($dto->items[1]->isEllipsis);
        $this->assertSame('100', $dto->items[6]->label);
        $this->assertTrue($dto->items[6]->isCurrent);
    }

    #[Test]
    public function itGeneratesDoubleEllipsisWhenInTheMiddleOfManyPages(): void
    {
        // 100 Seiten, aktuelle Seite 50
        $dto = PaginationViewDto::fromParameters(50, 1000, 10, []);

        // Items: 1, ..., 48, 49, 50, 51, 52, ..., 100
        $this->assertCount(9, $dto->items);
        $this->assertTrue($dto->items[1]->isEllipsis);
        $this->assertTrue($dto->items[7]->isEllipsis);
    }

    #[Test]
    public function itSanitizesQueryParamsCorrectly(): void
    {
        $queryParams = [
            'page' => '99', // Should be stripped
            'audit_page' => '5', // Should be stripped
            'search' => 'test',
            'archive_depth' => '2020',
        ];

        // Focus Tab ist NICHT tab-expired -> archive_depth muss entfernt werden!
        $dto = PaginationViewDto::fromParameters(2, 50, 10, $queryParams, 'page', 'tab-active');

        // Die URL darf NUR 'search=test', 'focus=tab-active' und '&page=x' enthalten
        $this->assertStringContainsString('search=test', $dto->prevUrl);
        $this->assertStringContainsString('focus=tab-active', $dto->prevUrl);
        $this->assertStringNotContainsString('archive_depth', $dto->prevUrl);
        $this->assertStringNotContainsString('audit_page', $dto->prevUrl);
    }
}
