<?php

declare(strict_types=1);

namespace App\Modules\Modules\Controllers\Admin;

use App\Core\Console\Generator\Field;
use App\Core\Menus\MenuService;
use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Module manager. The generator form runs "php artisan make:module" for you,
 * but only when APP_ENV=local: in production, generate locally, commit, deploy.
 */
class ModuleController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ModuleManager $modules) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:modules.view', only: ['index']),
            new Middleware('permission:modules.create', only: ['generate']),
            new Middleware('permission:modules.edit', only: ['toggle', 'sync']),
        ];
    }

    public function index(): View
    {
        return view('modules::admin.index', [
            'modules' => $this->modules->all(),
            'canGenerate' => app()->isLocal(),
            'types' => Field::TYPES,
            'groups' => config('starter.menu_groups'),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z][A-Za-z0-9 ]*$/'],
            'fields' => ['required', 'string', 'max:1000'],
            'group' => ['required', Rule::in(config('starter.menu_groups'))],
            'icon' => ['nullable', 'string', 'regex:/^bi-[a-z0-9-]+$/'],
            'web' => ['boolean'],
            'api' => ['boolean'],
        ]);

        if (! app()->isLocal()) {
            return back()->with('error', 'For safety, modules can only be generated when APP_ENV=local. Run the command on your machine and deploy.');
        }

        $exit = Artisan::call('make:module', [
            'name' => str_replace(' ', '', ucwords($data['name'])),
            '--fields' => $data['fields'],
            '--group' => $data['group'],
            '--icon' => $data['icon'] ?: 'bi-grid',
            '--web' => $request->boolean('web'),
            '--api' => $request->boolean('api'),
            '--migrate' => true,
        ]);

        $output = trim(Artisan::output());

        if ($exit !== 0) {
            return back()->withInput()->with('error', 'Generator failed: '.strip_tags(preg_replace('/\e\[[\d;]*m/', '', $output)));
        }

        app(MenuService::class)->flush();

        return redirect()->route('admin.modules.index')
            ->with('success', 'Module generated, migrated and added to the sidebar.')
            ->with('generator_output', preg_replace('/\e\[[\d;]*m/', '', $output));
    }

    public function toggle(string $slug): RedirectResponse
    {
        $module = $this->modules->find($slug) ?? abort(404);

        try {
            $this->modules->setEnabled($slug, ! $module->enabled);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        app(MenuService::class)->flush();
        Artisan::call('route:clear');

        return back()->with('success', $module->name.($module->enabled ? ' disabled. Its menus and pages are hidden; data is kept.' : ' enabled.'));
    }

    public function sync(): RedirectResponse
    {
        Artisan::call('module:sync');

        return back()->with('success', trim(preg_replace('/\e\[[\d;]*m/', '', Artisan::output())) ?: 'Synced.');
    }
}
