<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ApiSearchPermitsRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\PermitService;

/**
 * Action für die asynchrone Suche nach Genehmigungen im Admin-Dashboard.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ApiSearchPermitsAction implements ViewActionInterface
{
    public function __construct(
        private PermitService $permitService)
    {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto    = ApiSearchPermitsRequest::fromArray($request->post);
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
                    'total'       => $result['total'],
                    'page'        => $dto->page,
                    'limit'       => $dto->limit,
                    'total_pages' => \ceil($result['total'] / $dto->limit),
                ],
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 500);
        }
    }
}
