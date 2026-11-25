<?php

declare(strict_types=1);

namespace App\Modules\Menus\Controllers\Admin;

use App\Core\Localization\Locales;
use App\Core\Menus\MenuItem;
use App\Core\Menus\MenuService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin sidebar: items come from module.json; here you can reorder, rename,
 * change the icon or hide them. Website menus (header / footer) are fully
 * managed here.
 */
class MenuController extends Controller implements HasMiddleware
{
    public const LOCATIONS = ['admin' => 'Admin sidebar', 'header' => 'Website header', 'footer' => 'Website footer'];

    public function __construct(private readonly MenuService $menus) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:menus.view', only: ['index']),
            new Middleware('permission:menus.create', only: ['store']),
            new Middleware('permission:menus.edit', only: ['update', 'sort', 'toggle']),
            new Middleware('permission:menus.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $location = array_key_exists($request->query('location'), self::LOCATIONS) ? $request->query('location') : 'admin';

        $items = MenuItem::query()->where('location', $location)->whereNull('parent_id')
            ->with('children.children')->orderBy('sort_order')->get();

        return view('menus::admin.index', [
            'location' => $location,
            'locations' => self::LOCATIONS,
            'groups' => $location === 'admin'
                ? $items->groupBy(fn ($i) => $i->group ?: 'Modules')->sortBy(fn ($v, $k) => array_search($k, config('starter.menu_groups'), true) ?: 99)
                : collect(),
            'items' => $items,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'location' => ['required', Rule::in(['header', 'footer'])],
            'label' => ['required', 'string', 'max:60'],
            'url' => ['required', 'string', 'max:255', 'regex:/^(\/|https?:\/\/|#|mailto:|tel:)/'],
            'parent_id' => ['nullable', 'integer', Rule::exists('menu_items', 'id')->where('location', $request->input('location'))],
            'new_tab' => ['boolean'],
        ], ['url.regex' => 'Start the link with /, http:// or https://']);

        $data['sort_order'] = (int) MenuItem::query()->where('location', $data['location'])->max('sort_order') + 1;
        MenuItem::query()->create($data);
        $this->menus->flush();

        return back()->with('success', 'Menu item added.');
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        $rules = [
            'label' => ['required', 'string', 'max:60'],
            'icon' => ['nullable', 'string', 'max:40', 'regex:/^bi-[a-z0-9-]+$/'],
        ];
        if ($item->location !== 'admin') {
            $rules['url'] = ['required', 'string', 'max:255', 'regex:/^(\/|https?:\/\/|#|mailto:|tel:)/'];
            $rules['new_tab'] = ['boolean'];
            $rules['translations'] = ['nullable', 'array'];
            $rules['translations.*'] = ['nullable', 'string', 'max:60'];
        }

        $data = $request->validate($rules, ['icon.regex' => 'Use a Bootstrap icon class such as bi-people.']);
        if (array_key_exists('translations', $data)) {
            $data['translations'] = array_filter(array_intersect_key((array) $data['translations'], array_flip(Locales::translatable())));
        }
        $item->update($data);
        $this->menus->flush();

        return back()->with('success', 'Menu item saved.');
    }

    /**
     * Saves the drag-and-drop order. Receives {"ids": [3, 1, 2]}.
     */
    public function sort(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        foreach ($ids as $position => $id) {
            MenuItem::query()->whereKey($id)->update(['sort_order' => $position + 1]);
        }
        $this->menus->flush();

        return response()->json(['saved' => true]);
    }

    public function toggle(MenuItem $item): RedirectResponse
    {
        $item->update(['is_active' => ! $item->is_active]);
        $this->menus->flush();

        return back()->with('success', $item->label.($item->is_active ? ' is visible again.' : ' is hidden.'));
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        if ($item->location === 'admin' && $item->key) {
            return back()->with('error', 'Sidebar items from modules can be hidden but not deleted.');
        }

        $item->delete();
        $this->menus->flush();

        return back()->with('success', 'Menu item removed.');
    }
}
