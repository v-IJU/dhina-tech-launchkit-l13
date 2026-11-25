<?php

declare(strict_types=1);

namespace App\Modules\Settings\Controllers\Admin;

use App\Core\Settings\SettingsRepository;
use App\Http\Controllers\Controller;
use App\Modules\Settings\Support\SettingDefinitions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller implements HasMiddleware
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:settings.view', only: ['edit']),
            new Middleware('permission:settings.edit', only: ['update', 'testMail']),
        ];
    }

    public function edit(Request $request): View
    {
        $tabs = SettingDefinitions::tabs();
        $tab = array_key_exists($request->query('tab'), $tabs) ? $request->query('tab') : 'general';

        return view('settings::admin.edit', [
            'tabs' => $tabs,
            'tab' => $tab,
            'values' => $this->settings->all() + SettingDefinitions::defaults(),
            'colors' => SettingDefinitions::COLORS,
        ]);
    }

    public function update(Request $request, string $tab): RedirectResponse
    {
        $definition = SettingDefinitions::tabs()[$tab] ?? abort(404);
        $fields = $definition['fields'];

        $data = $request->validate(collect($fields)->map(fn ($f) => $f['rules'])->all());

        foreach ($fields as $key => $field) {
            if ($field['type'] === 'toggle') {
                $data[$key] = $request->boolean($key);
            }
            if ($field['type'] === 'checkboxes') {
                $data[$key] = implode(',', array_intersect((array) $request->input($key, []), array_keys($field['options'])));
            }
            if ($field['type'] === 'password' && blank($data[$key] ?? null)) {
                unset($data[$key]); // keep the saved password
            }
        }

        $this->settings->set($data);

        activity('settings')->event('updated')->withProperties(['keys' => array_keys($data)])->log("Updated {$definition['label']} settings");

        return redirect()->route('admin.settings.edit', ['tab' => $tab])->with('success', "{$definition['label']} settings saved.");
    }

    public function testMail(Request $request): RedirectResponse
    {
        $to = setting('contact_email') ?: $request->user()->email;

        try {
            Mail::raw('This is a test email from '.setting('site_name', config('app.name')).'. Your mail settings work.', function ($m) use ($to) {
                $m->to($to)->subject('Test email');
            });
        } catch (Throwable $e) {
            return back()->with('error', 'Sending failed: '.$e->getMessage());
        }

        return back()->with('success', "Test email sent to {$to}.");
    }
}
