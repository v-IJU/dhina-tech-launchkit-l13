<?php

declare(strict_types=1);

namespace App\Modules\Faqs\Controllers\Api\V2;

use App\Core\DataTable\DataTable;
use App\Http\Controllers\Controller;
use App\Modules\Faqs\Models\Faq;
use App\Modules\Faqs\Requests\V2\FaqRequest;
use App\Modules\Faqs\Resources\V2\FaqResource;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * FAQs API v2: /api/v2/faqs
 *
 * What changed from v1:
 *  - field names: title, body, group, position, active (in and out)
 *  - lists are sorted by position and return 50 per page
 *  - new: GET /api/v2/faqs/grouped → active FAQs grouped for an app's help screen
 */
class FaqController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:faqs.view', only: ['index', 'show', 'grouped']),
            new Middleware('permission:faqs.create', only: ['store']),
            new Middleware('permission:faqs.edit', only: ['update']),
            new Middleware('permission:faqs.delete', only: ['destroy']),
        ];
    }

    /**
     * List (paginated, 50 per page, by position).
     *
     * @response AnonymousResourceCollection<LengthAwarePaginator<FaqResource>>
     */
    #[QueryParameter('q', description: 'Search text', type: 'string')]
    #[QueryParameter('sort', description: 'Sort by: title, group, position, updated_at', type: 'string')]
    #[QueryParameter('dir', description: 'asc or desc', type: 'string')]
    #[QueryParameter('active', description: '1 = active only, 0 = inactive only', type: 'string')]
    #[QueryParameter('page', type: 'integer')]
    public function index(Request $request): JsonResponse
    {
        return DataTable::of(Faq::query(), $request)
            ->search(['question', 'answer', 'group_name'])
            // v2 sort names → real columns
            ->sortable(['title' => 'question', 'group' => 'group_name', 'position' => 'sort_order', 'updated_at' => 'updated_at'], default: 'position', direction: 'asc')
            ->filter('active', 'Active', ['1' => 'Yes', '0' => 'No'], fn ($q, string $v) => $q->where('is_active', (bool) $v))
            ->perPage(50)
            ->json(FaqResource::class);
    }

    /**
     * Active FAQs grouped by their group, for a help screen.
     */
    public function grouped(): JsonResponse
    {
        $groups = Faq::query()->where('is_active', true)->orderBy('sort_order')->get()
            ->groupBy(fn (Faq $faq) => $faq->group_name ?: 'General')
            ->map(fn ($faqs, $group) => ['group' => $group, 'items' => FaqResource::collection($faqs)->resolve()])
            ->values();

        return response()->json(['data' => $groups]);
    }

    public function show(Faq $faq): FaqResource
    {
        return new FaqResource($faq);
    }

    public function store(FaqRequest $request): JsonResponse
    {
        $faq = Faq::query()->create($request->validated());

        return (new FaqResource($faq))->response()->setStatusCode(201);
    }

    public function update(FaqRequest $request, Faq $faq): FaqResource
    {
        $faq->update($request->validated());

        return new FaqResource($faq->refresh());
    }

    public function destroy(Faq $faq): JsonResponse
    {
        $faq->delete();

        return response()->json(['message' => 'FAQ moved to the trash.']);
    }
}