<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SearchPermits;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use Override;
use Throwable;

#[Route('POST', '/api/search_permits')]
#[RequiresAuth]
final readonly class SearchPermitsAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private SearchPermitsHandler $searchHandler,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'permits.view';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = ApiSearchPermitsRequest::fromArray($request->post);

            $result = $this->searchHandler->handle(new SearchPermitsQuery(
                $dto->query,
                $dto->page,
                $dto->limit,
                $dto->tab,
                $dto->template,
            ));

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
