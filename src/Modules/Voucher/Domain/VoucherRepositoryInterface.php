<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Domain;

/**
 * Der Vertrag zum Speichern von Gutscheinen.
 * Das Domain-Modul diktiert, WAS gebraucht wird. Die Infrastructure setzt es später in PDO um.
 */
interface VoucherRepositoryInterface
{
    public function save(Voucher $voucher): void;

    public function findByCode(string $code): ?Voucher;

    public function delete(string $code): void;
}
