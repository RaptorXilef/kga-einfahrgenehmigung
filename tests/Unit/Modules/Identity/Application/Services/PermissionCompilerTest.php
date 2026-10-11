<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\Services;

use App\Modules\Identity\Application\Services\PermissionCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermissionCompiler::class)]
final class PermissionCompilerTest extends TestCase
{
    private function getTestStructure(): array
    {
        return [
            'system' => [
                'key' => 'system.manage',
                'children' => [
                    'users' => ['key' => 'system.users.manage'],
                    'roles' => ['key' => 'system.roles.manage'],
                ],
            ],
            'stats' => [
                'key' => 'stats.view',
                'children' => [
                    'charts' => ['key' => 'stats.charts'],
                ],
            ],
            'orphaned' => [
                'children' => [
                    'child' => ['key' => 'orphaned.child'],
                ],
            ],
        ];
    }

    #[Test]
    public function itCompilesPermissionsAndInheritsParentGrants(): void
    {
        $compiler = new PermissionCompiler();

        // Wenn der Parent (system.manage) aktiv ist, erben die Kinder automatisch (system.users.manage)
        $compiled = $compiler->compile($this->getTestStructure(), ['system.manage']);

        $this->assertTrue($compiled['system.manage']);
        $this->assertTrue($compiled['system.users.manage']);
        $this->assertTrue($compiled['system.roles.manage']);
        $this->assertFalse($compiled['stats.view'] ?? false);
    }

    #[Test]
    public function itAppliesImplicitGrantsFromChildrenToParents(): void
    {
        $compiler = new PermissionCompiler();

        // Nur das Kind ist aktiv
        $compiled = $compiler->compile($this->getTestStructure(), ['system.users.manage', 'stats.charts']);

        $this->assertTrue($compiled['system.users.manage']);
        // Impliziter Grant greift: Um Nutzer zu verwalten, MUSS system.manage aktiv sein
        $this->assertTrue($compiled['system.manage']);

        $this->assertTrue($compiled['stats.charts']);
        // Impliziter Grant: Um Charts zu sehen, MUSS stats.view aktiv sein
        $this->assertTrue($compiled['stats.view']);
    }

    #[Test]
    public function itRespectsExplicitDenyOverridingImplicitGrants(): void
    {
        $compiler = new PermissionCompiler();

        // Versucht das Kind zu erlauben, verbietet aber explizit den Parent!
        $compiled = $compiler->compile($this->getTestStructure(), ['system.users.manage', '-system.manage']);

        // FIX: Ein verbotener Parent (Verwaltung gesperrt) negiert auch alle Kind-Freigaben,
        // da das `-` nach unten propagiert wird!
        $this->assertFalse($compiled['system.users.manage']);
        // Explizites -system.manage verbietet den impliziten Grant
        $this->assertFalse($compiled['system.manage'] ?? false);
    }

    #[Test]
    public function itGrantsEverythingWithWildcardExceptExplicitDenies(): void
    {
        $compiler = new PermissionCompiler();
        $compiled = $compiler->compile($this->getTestStructure(), ['*', '-system.roles.manage']);

        $this->assertTrue($compiled['system.manage']);
        $this->assertTrue($compiled['stats.view']);
        $this->assertTrue($compiled['orphaned.child']);
        // Wildcard erlaubt alles, aber das explizite Deny hat Vorrang!
        $this->assertFalse($compiled['system.roles.manage']);
    }
}
