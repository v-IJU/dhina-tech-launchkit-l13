<?php

declare(strict_types=1);

namespace App\Modules\Pages\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Pages\Models\Page;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(Request $request): View
    {
        $page = Page::query()->originals()->where('is_home', true)->first()
            ?? Page::query()->originals()->where('slug', 'home')->first();

        if (! $page) {
            return view('pages::web.welcome');
        }

        return $this->render($request, $page);
    }

    public function show(Request $request, string $slug): View
    {
        $page = Page::query()->originals()->where('slug', $slug)->firstOrFail();

        return $this->render($request, $page);
    }

    private function render(Request $request, Page $page): View
    {
        // Drafts are visible to signed-in staff only, so they can preview.
        abort_if($page->status !== 'published' && ! $request->user()?->can('pages.view'), 404);

        // Show the translation for the visitor's language when there is a published one.
        return view('pages::web.page', ['page' => $page->localized()]);
    }
}
