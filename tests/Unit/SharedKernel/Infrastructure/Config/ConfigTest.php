<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Config;

use App\SharedKernel\Infrastructure\Config\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    #[Test]
    public function itRetrievesTypedValuesWithFallbacks(): void
    {
        $config = new Config([
            'str_key' => 'Hallo',
            'int_as_str' => '42',
            'int_key' => 100,
            'bool_true' => true,
            'bool_false' => false,
            'int_bool' => 1,
            'int_bool_zero' => 0,
            'arr_key' => ['a' => 1],
        ]);

        // Raw get
        $this->assertSame('Hallo', $config->get('str_key'));
        $this->assertSame('fallback', $config->get('missing', 'fallback'));

        // getString
        $this->assertSame('Hallo', $config->getString('str_key'));
        $this->assertSame('100', $config->getString('int_key'));
        $this->assertSame('def', $config->getString('arr_key', 'def'));
        $this->assertSame('', $config->getString('missing'));

        // getInt
        $this->assertSame(100, $config->getInt('int_key'));
        $this->assertSame(42, $config->getInt('int_as_str'));
        $this->assertSame(99, $config->getInt('str_key', 99));
        $this->assertSame(0, $config->getInt('missing'));

        // getBool
        $this->assertTrue($config->getBool('bool_true'));
        $this->assertFalse($config->getBool('bool_false', true));
        $this->assertTrue($config->getBool('int_bool'));
        $this->assertFalse($config->getBool('int_bool_zero', true));
        $this->assertFalse($config->getBool('missing'));
        $this->assertTrue($config->getBool('missing', true));

        // getArray
        $this->assertSame(['a' => 1], $config->getArray('arr_key'));
        $this->assertSame(['f'], $config->getArray('str_key', ['f']));
        $this->assertSame([], $config->getArray('missing'));
    }

    #[Test]
    public function itResolvesTestModeAndMailSettings(): void
    {
        // Default test_mode ist true, wenn nicht angegeben
        $defaultConfig = new Config([
            'mail' => ['default' => 'smtp_live'],
        ]);
        $this->assertTrue($defaultConfig->isTestMode());
        // Fällt auf 'mail' zurück, wenn 'mail-test' leer ist
        $this->assertSame(['default' => 'smtp_live'], $defaultConfig->getMailSettings());

        $sandboxConfig = new Config([
            'test_mode' => true,
            'mail' => ['default' => 'smtp_live'],
            'mail-test' => ['default' => 'smtp_sandbox'],
        ]);
        $this->assertTrue($sandboxConfig->isTestMode());
        $this->assertSame(['default' => 'smtp_sandbox'], $sandboxConfig->getMailSettings());

        $prodConfig = new Config([
            'test_mode' => false,
            'mail' => ['default' => 'smtp_live'],
            'mail-test' => ['default' => 'smtp_sandbox'],
        ]);
        $this->assertFalse($prodConfig->isTestMode());
        $this->assertSame(['default' => 'smtp_live'], $prodConfig->getMailSettings());
    }

    #[Test]
    public function itCalculatesPriceForVehicleTypeWithFallbacks(): void
    {
        $config = new Config([
            'vehicle_types' => [
                'lkw' => ['label' => 'LKW'],
                'pkw' => ['label' => 'PKW'],
            ],
            'prices' => [
                'lkw' => 25.5,
                'pkw' => 10,
                'corrupt' => ['not-a-scalar'],
            ],
        ]);

        $this->assertEqualsWithDelta(10.0, $config->getPriceForType('pkw'), \PHP_FLOAT_EPSILON);
        // Unbekannter Typ fällt auf den ersten Key von vehicle_types ('lkw') zurück
        $this->assertEqualsWithDelta(25.5, $config->getPriceForType('unknown_type'), \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.0, $config->getPriceForType('corrupt'), \PHP_FLOAT_EPSILON);

        // Wenn vehicle_types leer ist, ist der Default-Key 'pkw'
        $fallbackPkwConfig = new Config([
            'prices' => ['pkw' => 5.0],
        ]);
        $this->assertEqualsWithDelta(5.0, $fallbackPkwConfig->getPriceForType('other'), \PHP_FLOAT_EPSILON);

        $emptyConfig = new Config([]);
        $this->assertEqualsWithDelta(0.0, $emptyConfig->getPriceForType('pkw'), \PHP_FLOAT_EPSILON);
    }

    #[Test]
    public function itBuildsBaseUrlAndStoragePathsCleanly(): void
    {
        $configured = new Config([
            'base_url' => 'https://kga-berlin.de///',
            'root_path' => '/var/www/app/',
            'storage_path_prefix' => '/storage/',
        ]);

        $this->assertSame('https://kga-berlin.de', $configured->getBaseUrl());
        $this->assertSame('/var/www/app/storage/users.json', $configured->getStoragePath('/users.json'));

        // CLI Fallback ohne base_url
        $cliConfig = new Config([
            'cli_fallback_url' => 'http://localhost:8080/',
        ]);
        $this->assertSame('http://localhost:8080', $cliConfig->getBaseUrl());

        $defaultCliConfig = new Config([]);
        $this->assertSame('http://localhost', $defaultCliConfig->getBaseUrl());
    }
}
