<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\SessionManager;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use DateTimeImmutable;
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

        $manager->setAnalyticsSessionId(12345);
        $this->assertSame(12345, $manager->getAnalyticsSessionId());

        $token1 = $manager->initCsrfToken();
        $this->assertSame($token1, $manager->getCsrfToken());

        $manager->rotateCsrfToken();
        $token2 = $manager->getCsrfToken();

        $this->assertNotSame($token1, $token2);
    }

    #[Test]
    public function itManagesAllAdditionalGettersAndSetters(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable());
        $manager = new SessionManager($clock);

        // Edit State
        $manager->setEditState('test@test.de', 'token123');
        $this->assertSame('test@test.de', $manager->getVerifiedEmail());
        $this->assertSame('token123', $manager->getEditToken());
        $manager->clearEditState();
        $this->assertNull($manager->getVerifiedEmail());

        // Admin Filters
        $manager->setAdminFilters(['q' => 'test']);
        $this->assertSame(['q' => 'test'], $manager->getAdminFilters());
        $manager->clearAdminFilters();
        $this->assertSame([], $manager->getAdminFilters());

        // History Email
        $manager->setHistoryEmail('history@test.de');
        $this->assertSame('history@test.de', $manager->getHistoryEmail());
        $manager->clearHistoryEmail();
        $this->assertNull($manager->getHistoryEmail());

        // Form Start Time
        $manager->setFormStartTime(1234567890);
        $this->assertSame(1234567890, $manager->getFormStartTime());
        $manager->clearFormStartTime();
        $this->assertSame(0, $manager->getFormStartTime());

        // Permissions
        $manager->setPermissions(['perm' => true]);
        $this->assertSame(['perm' => true], $manager->getPermissions());

        // Admin Username Update
        $manager->setAuthSession('1', 'admin', 'Old', null);
        $manager->updateAdminUsername('New');
        $this->assertSame('New', $manager->getAdminUser());
    }

    #[Test]
    public function itEnforcesIdleTimeoutAndDestroysSession(): void
    {
        // Setze Session in die Vergangenheit (Idle Timeout = 1800s)
        $_SESSION['session_created'] = 1000;
        $_SESSION['last_activity'] = 1000;
        $_SESSION['user_id'] = 'usr_1';

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn((new DateTimeImmutable())->setTimestamp(3000)); // 2000s später (> 1800)

        $manager = new SessionManager($clock);

        // Session wurde resettet, user_id ist weg
        $this->assertSame('', $manager->getUserId());
        $this->assertSame(3000, $_SESSION['session_created']);
    }

    #[Test]
    public function itEnforcesAbsoluteTimeoutAndDestroysSession(): void
    {
        // Setze Session in die Vergangenheit (Max Lifetime = 43200s)
        $_SESSION['session_created'] = 1000;
        $_SESSION['last_activity'] = 44000; // Letzte Aktivität war erst vor kurzem
        $_SESSION['user_id'] = 'usr_1';

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn((new DateTimeImmutable())->setTimestamp(45000)); // 44000s nach Erstellung (> 43200)

        $manager = new SessionManager($clock);

        $this->assertSame('', $manager->getUserId());
        $this->assertSame(45000, $_SESSION['session_created']);
    }
}
