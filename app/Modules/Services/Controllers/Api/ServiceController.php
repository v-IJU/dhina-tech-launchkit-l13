<?php

declare(strict_types=1);

namespace App\Modules\Services\Controllers\Api;

use App\Core\DataTable\DataTable;
use App\Core\Http\StoresUploads;
use App\Http\Controllers\Controller;
use App\Modules\Services\Models\Service;
use App\Modules\Services\Requests\StoreServiceRequest;
use App\Modules\Services\Requests\UpdateServiceRequest;
use App\Modules\Services\Resources\ServiceResource;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Mews\Purifier\Facades\Purifier;

/**
 * REST API for services: /api/v1/services
 *
 *   GET    /api/v1/services            list (?q= &sort= &dir= &per_page= &page= &status= &from= &to=)
 *   GET    /api/v1/services/{id}       one record
 *   POST   /api/v1/services            create   (same fields and rules as the admin form)
 *   PUT    /api/v1/services/{id}       update   (send every required field)
 *   DELETE /api/v1/services/{id}       move to the trash
 *
 * Same permissions as the admin panel (services.view / create / edit / delete).
 */
class ServiceController extends Controller implements HasMiddleware
{
    use StoresUploads;

    /** @var array<int, string> File inputs saved to storage/app/public/services */
    private array $uploads = [];

    /** @var array<int, string> Rich text fields cleaned with HTML Purifier */
    private array $richText = [];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:services.view', only: ['index', 'show']),
            new Middleware('permission:services.create', only: ['store']),
            new Middleware('permission:services.edit', only: ['update']),
            new Middleware('permission:services.delete', only: ['destroy']),
        ];
    }

    /**
     * List (paginated). Same search, filters and sorting as the admin list.
     *
     * @response AnonymousResourceCollection<LengthAwarePaginator<ServiceResource>>
     */
    #[QueryParameter('q', description: 'Search text', type: 'string')]
    #[QueryParameter('sort', description: 'Sort by: title, icon, sort_order, is_active, created_at', type: 'string')]
    #[QueryParameter('dir', description: 'asc or desc', type: 'string')]
    #[QueryParameter('per_page', description: '10, 15, 25, 50 or 100', type: 'integer')]
    #[QueryParameter('page', type: 'integer')]
    #[QueryParameter('status', description: '1 = active, 0 = inactive', type: 'string')]
    #[QueryParameter('from', description: 'From date (YYYY-MM-DD)', type: 'string', format: 'date')]
    #[QueryParameter('to', description: 'To date (YYYY-MM-DD)', type: 'string', format: 'date')]
    public function index(Request $request): JsonResponse
    {
        return DataTable::of(Service::query(), $request)
            ->search(['title', 'icon', 'summary'])
            ->sortable(['title', 'icon', 'sort_order', 'is_active', 'created_at'], default: 'sort_order', direction: 'asc')
            ->status('is_active')
            ->dateRange('created_at')
            ->json(ServiceResource::class);
    }

    public function show(Service $service): ServiceResource
    {
        return new ServiceResource($service);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = Service::create($this->prepare($request, $request->validated()));

        return (new ServiceResource($service))->response()->setStatusCode(201);
    }

    public function update(UpdateServiceRequest $request, Service $service): ServiceResource
    {
        $service->update($this->prepare($request, $request->validated(), $service));

        return new ServiceResource($service->refresh());
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json(['message' => 'Service moved to the trash.']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(Request $request, array $data, ?Service $service = null): array
    {
        foreach ($this->richText as $field) {
            if (isset($data[$field])) {
                $data[$field] = Purifier::clean($data[$field]);
            }
        }

        return $this->storeUploads($request, $data, $this->uploads, 'services', $service);
    }
}
