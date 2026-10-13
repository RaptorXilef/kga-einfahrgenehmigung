<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\SessionManager;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionManager::class)]
final class SessionManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
    }

    #[Test]
    public function itCanSetAndRetrieveFormData(): void
    {
        $manager = new SessionManager(new SystemClock());

        $this->assertSame([], $manager->getFormData());

        $manager->setFormData(['name' => 'Max']);
        $this->assertSame(['name' => 'Max'], $manager->getFormData());

        $manager->clearFormData();
        $this->assertSame([], $manager->getFormData());
    }

    #[Test]
    public function itCanManageFlashesAndClearsThemAfterReading(): void
    {
        $manager = new SessionManager(new SystemClock());

        $manager->addFlash('success', 'Erfolg!');
        $manager->addFlash('error', 'Fehler!');

        $flashes = $manager->getFlashes();

        $this->assertSame(['success' => ['Erfolg!'], 'error' => ['Fehler!']], $flashes);
        // Flashes should be empty after reading (Pop-Verhalten)
        $this->assertSame([], $manager->getFlashes());
    }

    #[Test]
    public function itCanManageAuthSessionProperties(): void
    {
        $manager = new SessionManager(new SystemClock());

        $manager->setAuthSession('usr_123', 'role_admin', 'Max', 'hash123');
        $manager->setAdminRoleName('Chef');

        $this->assertSame('usr_123', $manager->getUserId());
        $this->assertSame('role_admin', $manager->getAdminGroup());
        $this->assertSame('Chef', $manager->getAdminRoleName());
        $this->assertSame('Max', $manager->getAdminUser());
        $this->assertSame('hash123', $manager->getAuthHash());
    }

    #[Test]
    public function itCanManageCollectiveTransfers(): void
    {
        $manager = new SessionManager(new SystemClock());

        $manager->addCollectiveTransfer(['id' => 'transfer_1', 'name' => 'Max']);
        $this->assertSame(['transfer_1' => ['id' => 'transfer_1', 'name' => 'Max']], $manager->getCollectiveTransfers());

        $manager->removeCollectiveTransfer('transfer_1');
        $this->assertSame([], $manager->getCollectiveTransfers());
    }

    #[Test]
    public function itCanManageAnalyticsAndCsrfTokens(): void
    {
        $manager = new SessionManager(new SystemClock());

        $manager->setAnalyticsId('CLIENT-A');
        $this->assertSame('CLIENT-A', $manager->getAnalyticsId());

        $token1 = $manager->initCsrfToken();
        $this->assertSame($token1, $manager->getCsrfToken());

        $manager->rotateCsrfToken();
        $token2 = $manager->getCsrfToken();

        $this->assertNotSame($token1, $token2);
    }
}
