<?php

declare(strict_types=1);

namespace App\Application\Actions\Api\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\ApiSearchPermitsRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Core\Service\PermitService;
use Throwable;

/**
 * Action für die asynchrone Suche nach Genehmigungen im Admin-Dashboard.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('POST', '/api/search_permits')]
#[RequiresAuth]
final readonly class SearchPermitsAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private PermitService $permitService,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.control_bar.search';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = ApiSearchPermitsRequest::fromArray($request->post);

            $result = $this->permitService->searchAndPaginate(
                $dto->query,
                $dto->tab,
                $dto->template,
                $dto->page,
                $dto->limit,
            );

            return JsonResponse::success([
                'data' => $result['items'],
                'meta' => [
                    'total' => $result['total'],
                    'page' => $dto->page,
                    'limit' => $dto->limit,
                    'total_pages' => \ceil($result['total'] / $dto->limit),
                ],
            ]);
        } catch (Throwable $e) {
            return JsonResponse::error($e->getMessage(), 500);
        }
    }
}
