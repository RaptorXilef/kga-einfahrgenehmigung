<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Bootstrap\Container;
use App\Contracts\Application\ViewActionInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ApiActionFactory
{
    public function __construct(
        private Container $container,
    ) {
    }

    public function create(string $actionKey): ?ViewActionInterface
    {
        $class = match ($actionKey) {
            'capture'            => CapturePaymentAction::class,
            'check_update'       => SystemCheckUpdateAction::class,
            'create_order'       => CheckoutCreateOrderAction::class,
            'finalize_update'    => SystemFinalizeUpdateAction::class,
            'finalize_wire'      => CheckoutFinalizeWireAction::class,
            'get_date_info'      => ApiGetDateInfoAction::class,
            'get_template_price' => ApiGetTemplatePriceAction::class,
            'perform_update'     => SystemPerformUpdateAction::class,
            'process_mail_queue' => SystemProcessMailQueueAction::class,
            'search_permits'     => ApiSearchPermitsAction::class,
            default              => null,
        };

        return $class ? $this->container->get($class) : null;
    }
}
