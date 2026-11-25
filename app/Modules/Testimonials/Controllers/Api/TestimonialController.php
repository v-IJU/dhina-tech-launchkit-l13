<?php

declare(strict_types=1);

namespace App\Modules\Testimonials\Controllers\Api;

use App\Core\DataTable\DataTable;
use App\Core\Http\StoresUploads;
use App\Http\Controllers\Controller;
use App\Modules\Testimonials\Models\Testimonial;
use App\Modules\Testimonials\Requests\StoreTestimonialRequest;
use App\Modules\Testimonials\Requests\UpdateTestimonialRequest;
use App\Modules\Testimonials\Resources\TestimonialResource;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Mews\Purifier\Facades\Purifier;

/**
 * REST API for testimonials: /api/v1/testimonials
 *
 *   GET    /api/v1/testimonials            list (?q= &sort= &dir= &per_page= &page= &status= &from= &to=)
 *   GET    /api/v1/testimonials/{id}       one record
 *   POST   /api/v1/testimonials            create   (same fields and rules as the admin form)
 *   PUT    /api/v1/testimonials/{id}       update   (send every required field)
 *   DELETE /api/v1/testimonials/{id}       move to the trash
 *
 * Same permissions as the admin panel (testimonials.view / create / edit / delete).
 */
class TestimonialController extends Controller implements HasMiddleware
{
    use StoresUploads;

    /** @var array<int, string> File inputs saved to storage/app/public/testimonials */
    private array $uploads = ['photo'];

    /** @var array<int, string> Rich text fields cleaned with HTML Purifier */
    private array $richText = [];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:testimonials.view', only: ['index', 'show']),
            new Middleware('permission:testimonials.create', only: ['store']),
            new Middleware('permission:testimonials.edit', only: ['update']),
            new Middleware('permission:testimonials.delete', only: ['destroy']),
        ];
    }

    /**
     * List (paginated). Same search, filters and sorting as the admin list.
     *
     * @response AnonymousResourceCollection<LengthAwarePaginator<TestimonialResource>>
     */
    #[QueryParameter('q', description: 'Search text', type: 'string')]
    #[QueryParameter('sort', description: 'Sort by: client_name, company, rating, is_active, created_at', type: 'string')]
    #[QueryParameter('dir', description: 'asc or desc', type: 'string')]
    #[QueryParameter('per_page', description: '10, 15, 25, 50 or 100', type: 'integer')]
    #[QueryParameter('page', type: 'integer')]
    #[QueryParameter('status', description: '1 = active, 0 = inactive', type: 'string')]
    #[QueryParameter('from', description: 'From date (YYYY-MM-DD)', type: 'string', format: 'date')]
    #[QueryParameter('to', description: 'To date (YYYY-MM-DD)', type: 'string', format: 'date')]
    public function index(Request $request): JsonResponse
    {
        return DataTable::of(Testimonial::query(), $request)
            ->search(['client_name', 'company', 'quote'])
            ->sortable(['client_name', 'company', 'rating', 'is_active', 'created_at'], default: 'created_at', direction: 'desc')
            ->status('is_active')
            ->dateRange('created_at')
            ->json(TestimonialResource::class);
    }

    public function show(Testimonial $testimonial): TestimonialResource
    {
        return new TestimonialResource($testimonial);
    }

    public function store(StoreTestimonialRequest $request): JsonResponse
    {
        $testimonial = Testimonial::create($this->prepare($request, $request->validated()));

        return (new TestimonialResource($testimonial))->response()->setStatusCode(201);
    }

    public function update(UpdateTestimonialRequest $request, Testimonial $testimonial): TestimonialResource
    {
        $testimonial->update($this->prepare($request, $request->validated(), $testimonial));

        return new TestimonialResource($testimonial->refresh());
    }

    public function destroy(Testimonial $testimonial): JsonResponse
    {
        $testimonial->delete();

        return response()->json(['message' => 'Testimonial moved to the trash.']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(Request $request, array $data, ?Testimonial $testimonial = null): array
    {
        foreach ($this->richText as $field) {
            if (isset($data[$field])) {
                $data[$field] = Purifier::clean($data[$field]);
            }
        }

        return $this->storeUploads($request, $data, $this->uploads, 'testimonials', $testimonial);
    }
}
