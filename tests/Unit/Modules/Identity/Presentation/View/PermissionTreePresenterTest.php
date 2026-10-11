<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Presentation\View;

use App\Contracts\System\AssetHelperInterface;
use App\Modules\Identity\Presentation\View\PermissionTreePresenter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermissionTreePresenter::class)]
final class PermissionTreePresenterTest extends TestCase
{
    #[Test]
    public function itRendersTreeRecursivelyWithCorrectAttributes(): void
    {
        $asset = $this->createStub(AssetHelperInterface::class);
        $asset->method('url')->willReturnCallback(fn (string $path): string => '/' . $path);

        $structure = [
            'category1' => [
                'label' => 'Hauptkategorie',
                'icon' => 'test.webp',
                'children' => [
                    'child1' => ['label' => 'Recht 1', 'key' => 'perm.1'],
                    'child2' => ['label' => 'Recht 2', 'key' => 'perm.2'],
                ],
            ],
        ];

        $rolePerms = ['perm.1']; // Nur Recht 1 ist aktiv

        $html = PermissionTreePresenter::renderTree($structure, $rolePerms, 0, $asset);

        // Prüfe Tiefe und Kategorien
        $this->assertStringContainsString('style="--depth: 0;"', $html);
        $this->assertStringContainsString('Hauptkategorie', $html);
        $this->assertStringContainsString('Kategorie</span>', $html);
        $this->assertStringContainsString('src="/assets/img/icons/test.webp"', $html);

        // Prüfe Kind 1 (Aktiv)
        $this->assertStringContainsString('value="perm.1"', $html);
        $this->assertStringContainsString('aria-label="Berechtigung: Recht 1"', $html);
        // Da wir exakten Stringvergleich nicht blind machen können, nutzen wir Reguläre Ausdrücke für den checked state
        $this->assertMatchesRegularExpression('/value="perm\.1"[^>]*checked/', $html);

        // Prüfe Kind 2 (Inaktiv)
        $this->assertStringContainsString('value="perm.2"', $html);
        $this->assertDoesNotMatchRegularExpression('/value="perm\.2"[^>]*checked/', $html);
    }
}
