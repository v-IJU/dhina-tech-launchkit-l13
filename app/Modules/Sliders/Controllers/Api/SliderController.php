<?php

declare(strict_types=1);

namespace App\Modules\Sliders\Controllers\Api;

use App\Core\DataTable\DataTable;
use App\Core\Http\StoresUploads;
use App\Http\Controllers\Controller;
use App\Modules\Sliders\Models\Slider;
use App\Modules\Sliders\Requests\StoreSliderRequest;
use App\Modules\Sliders\Requests\UpdateSliderRequest;
use App\Modules\Sliders\Resources\SliderResource;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Mews\Purifier\Facades\Purifier;

/**
 * REST API for sliders: /api/v1/sliders
 *
 *   GET    /api/v1/sliders            list (?q= &sort= &dir= &per_page= &page= &status= &from= &to=)
 *   GET    /api/v1/sliders/{id}       one record
 *   POST   /api/v1/sliders            create   (same fields and rules as the admin form)
 *   PUT    /api/v1/sliders/{id}       update   (send every required field)
 *   DELETE /api/v1/sliders/{id}       move to the trash
 *
 * Same permissions as the admin panel (sliders.view / create / edit / delete).
 */
class SliderController extends Controller implements HasMiddleware
{
    use StoresUploads;

    /** @var array<int, string> File inputs saved to storage/app/public/sliders */
    private array $uploads = ['image'];

    /** @var array<int, string> Rich text fields cleaned with HTML Purifier */
    private array $richText = [];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:sliders.view', only: ['index', 'show']),
            new Middleware('permission:sliders.create', only: ['store']),
            new Middleware('permission:sliders.edit', only: ['update']),
            new Middleware('permission:sliders.delete', only: ['destroy']),
        ];
    }

    /**
     * List (paginated). Same search, filters and sorting as the admin list.
     *
     * @response AnonymousResourceCollection<LengthAwarePaginator<SliderResource>>
     */
    #[QueryParameter('q', description: 'Search text', type: 'string')]
    #[QueryParameter('sort', description: 'Sort by: title, subtitle, button_text, button_url, is_active, created_at', type: 'string')]
    #[QueryParameter('dir', description: 'asc or desc', type: 'string')]
    #[QueryParameter('per_page', description: '10, 15, 25, 50 or 100', type: 'integer')]
    #[QueryParameter('page', type: 'integer')]
    #[QueryParameter('status', description: '1 = active, 0 = inactive', type: 'string')]
    #[QueryParameter('from', description: 'From date (YYYY-MM-DD)', type: 'string', format: 'date')]
    #[QueryParameter('to', description: 'To date (YYYY-MM-DD)', type: 'string', format: 'date')]
    public function index(Request $request): JsonResponse
    {
        return DataTable::of(Slider::query(), $request)
            ->search(['title', 'subtitle', 'button_text', 'button_url'])
            ->sortable(['title', 'subtitle', 'button_text', 'button_url', 'is_active', 'created_at'], default: 'sort_order', direction: 'asc')
            ->status('is_active')
            ->dateRange('created_at')
            ->json(SliderResource::class);
    }

    public function show(Slider $slider): SliderResource
    {
        return new SliderResource($slider);
    }

    public function store(StoreSliderRequest $request): JsonResponse
    {
        $slider = Slider::create($this->prepare($request, $request->validated()));

        return (new SliderResource($slider))->response()->setStatusCode(201);
    }

    public function update(UpdateSliderRequest $request, Slider $slider): SliderResource
    {
        $slider->update($this->prepare($request, $request->validated(), $slider));

        return new SliderResource($slider->refresh());
    }

    public function destroy(Slider $slider): JsonResponse
    {
        $slider->delete();

        return response()->json(['message' => 'Slider moved to the trash.']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(Request $request, array $data, ?Slider $slider = null): array
    {
        foreach ($this->richText as $field) {
            if (isset($data[$field])) {
                $data[$field] = Purifier::clean($data[$field]);
            }
        }

        return $this->storeUploads($request, $data, $this->uploads, 'sliders', $slider);
    }
}
