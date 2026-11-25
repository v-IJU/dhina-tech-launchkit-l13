<?php

declare(strict_types=1);

namespace App\Modules\Faqs\Controllers\Api;

use App\Core\DataTable\DataTable;
use App\Core\Http\StoresUploads;
use App\Http\Controllers\Controller;
use App\Modules\Faqs\Models\Faq;
use App\Modules\Faqs\Requests\StoreFaqRequest;
use App\Modules\Faqs\Requests\UpdateFaqRequest;
use App\Modules\Faqs\Resources\FaqResource;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Mews\Purifier\Facades\Purifier;

/**
 * REST API for FAQs: /api/v1/faqs
 *
 *   GET    /api/v1/faqs            list (?q= &sort= &dir= &per_page= &page= &status= &from= &to=)
 *   GET    /api/v1/faqs/{id}       one record
 *   POST   /api/v1/faqs            create   (same fields and rules as the admin form)
 *   PUT    /api/v1/faqs/{id}       update   (send every required field)
 *   DELETE /api/v1/faqs/{id}       move to the trash
 *
 * Same permissions as the admin panel (faqs.view / create / edit / delete).
 */
class FaqController extends Controller implements HasMiddleware
{
    use StoresUploads;

    /** @var array<int, string> File inputs saved to storage/app/public/faqs */
    private array $uploads = [];

    /** @var array<int, string> Rich text fields cleaned with HTML Purifier */
    private array $richText = [];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:faqs.view', only: ['index', 'show']),
            new Middleware('permission:faqs.create', only: ['store']),
            new Middleware('permission:faqs.edit', only: ['update']),
            new Middleware('permission:faqs.delete', only: ['destroy']),
        ];
    }

    /**
     * List (paginated). Same search, filters and sorting as the admin list.
     *
     * @response AnonymousResourceCollection<LengthAwarePaginator<FaqResource>>
     */
    #[QueryParameter('q', description: 'Search text', type: 'string')]
    #[QueryParameter('sort', description: 'Sort by: question, group_name, sort_order, is_active, created_at', type: 'string')]
    #[QueryParameter('dir', description: 'asc or desc', type: 'string')]
    #[QueryParameter('per_page', description: '10, 15, 25, 50 or 100', type: 'integer')]
    #[QueryParameter('page', type: 'integer')]
    #[QueryParameter('status', description: '1 = active, 0 = inactive', type: 'string')]
    #[QueryParameter('from', description: 'From date (YYYY-MM-DD)', type: 'string', format: 'date')]
    #[QueryParameter('to', description: 'To date (YYYY-MM-DD)', type: 'string', format: 'date')]
    public function index(Request $request): JsonResponse
    {
        return DataTable::of(Faq::query(), $request)
            ->search(['question', 'answer', 'group_name'])
            ->sortable(['question', 'group_name', 'sort_order', 'is_active', 'created_at'], default: 'sort_order', direction: 'asc')
            ->status('is_active')
            ->dateRange('created_at')
            ->json(FaqResource::class);
    }

    public function show(Faq $faq): FaqResource
    {
        return new FaqResource($faq);
    }

    public function store(StoreFaqRequest $request): JsonResponse
    {
        $faq = Faq::create($this->prepare($request, $request->validated()));

        return (new FaqResource($faq))->response()->setStatusCode(201);
    }

    public function update(UpdateFaqRequest $request, Faq $faq): FaqResource
    {
        $faq->update($this->prepare($request, $request->validated(), $faq));

        return new FaqResource($faq->refresh());
    }

    public function destroy(Faq $faq): JsonResponse
    {
        $faq->delete();

        return response()->json(['message' => 'FAQ moved to the trash.']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(Request $request, array $data, ?Faq $faq = null): array
    {
        foreach ($this->richText as $field) {
            if (isset($data[$field])) {
                $data[$field] = Purifier::clean($data[$field]);
            }
        }

        return $this->storeUploads($request, $data, $this->uploads, 'faqs', $faq);
    }
}
