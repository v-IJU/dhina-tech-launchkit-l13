<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Export\TemplateExport;
use App\Core\Import\ImportRun;
use App\Http\Controllers\Controller;
use App\Modules\Customers\Exports\CustomersExport;
use App\Modules\Customers\Imports\CustomersImport;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Notifications\CustomerResetPassword;
use App\Modules\Customers\Requests\CustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel: Customers list, profile page, add / edit, trash, bulk actions, CSV import.
 * Account actions (block, reset 2FA, sign in as…) are in CustomerActionController.
 */
class CustomerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:customers.view', only: ['index', 'show']),
            new Middleware('permission:customers.create', only: ['create', 'store']),
            new Middleware('permission:customers.edit', only: ['edit', 'update', 'bulk']),
            new Middleware('permission:customers.delete', only: ['destroy', 'restore', 'forceDelete']),
            new Middleware('permission:customers.import', only: ['importForm', 'import', 'importTemplate']),
        ];
    }

    public function index(Request $request): Response
    {
        return DataTable::of(Customer::query()->withCount('addresses'), $request)
            ->search(fn ($query, string $term) => $query->search($term))
            ->sortable(['name', 'status', 'last_login_at', 'created_at'], default: 'created_at')
            ->status('status', Customer::STATUSES)
            ->filter('verified', 'Email', ['yes' => 'Confirmed', 'no' => 'Not confirmed'],
                fn ($query, string $value) => $value === 'yes' ? $query->whereNotNull('email_verified_at') : $query->whereNull('email_verified_at'))
            ->filter('two_factor', '2FA', ['on' => 'On', 'off' => 'Off'],
                fn ($query, string $value) => $value === 'on' ? $query->whereNotNull('two_factor_confirmed_at') : $query->whereNull('two_factor_confirmed_at'))
            ->filter('marketing', 'Marketing', ['yes' => 'Opted in'], fn ($query) => $query->where('marketing_opt_in', true))
            ->dateRange('created_at', 'Joined')
            ->export('customers', CustomersExport::class, 'customers.export')   // app/Modules/Customers/Exports/CustomersExport.php
            ->trash('customers.delete')
            ->bulk(route('admin.customers.bulk'), ['activate' => 'customers.edit', 'block' => 'customers.edit', 'delete' => 'customers.delete'])
            ->render('customers::admin.index', 'customers::admin._table');
    }

    public function show(Customer $customer): View
    {
        $customer->load('addresses');

        return view('customers::admin.show', [
            'customer' => $customer,
            'logins' => $customer->loginEvents()->limit(15)->get(),
            'apps' => $customer->tokens()->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('customers::admin.form', ['customer' => new Customer(['status' => 'active'])]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = new Customer($this->customerData($request));
        $customer->password = $request->filled('password') ? $request->validated('password') : Str::password(32);
        $customer->email_verified_at = $request->boolean('email_verified') ? now() : null;
        $customer->save();

        if ($request->boolean('send_invite')) {
            $customer->notify(new CustomerResetPassword(Password::broker('customers')->createToken($customer), newAccount: true));
        }

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', "Customer {$customer->name} added".($request->boolean('send_invite') ? ' · set-password email queued' : '').'.');
    }

    public function edit(Customer $customer): View
    {
        return view('customers::admin.form', ['customer' => $customer]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->fill($this->customerData($request));
        if ($request->filled('password')) {
            $customer->password = $request->validated('password');
            $customer->tokens()->delete();
        }
        if ($request->boolean('email_verified') !== (bool) $customer->email_verified_at) {
            $customer->email_verified_at = $request->boolean('email_verified') ? now() : null;
        }
        $customer->save();

        return redirect()->route('admin.customers.show', $customer)->with('success', "Customer {$customer->name} updated.");
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->tokens()->delete();
        $customer->delete();

        return redirect()->route('admin.customers.index')->with('success', "Customer {$customer->name} moved to the trash.");
    }

    public function restore(Customer $customer): RedirectResponse
    {
        $customer->restore();

        return back()->with('success', "Customer {$customer->name} restored.");
    }

    public function forceDelete(Customer $customer): RedirectResponse
    {
        $customer->forceDelete();

        return back()->with('success', "Customer {$customer->name} deleted forever.");
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'block', 'delete', 'restore', 'force-delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        if (in_array($data['action'], ['delete', 'restore', 'force-delete'], true)) {
            abort_unless($request->user()->can('customers.delete'), 403);
        }

        $inTrash = in_array($data['action'], ['restore', 'force-delete'], true);
        $customers = Customer::query()->when($inTrash, fn ($q) => $q->onlyTrashed())->whereKey($data['ids'])->get();

        foreach ($customers as $customer) {
            if (in_array($data['action'], ['delete', 'block'], true)) {
                $customer->tokens()->delete(); // signed out of the app straight away
            }

            match ($data['action']) {
                'delete' => $customer->delete(),
                'restore' => $customer->restore(),
                'force-delete' => $customer->forceDelete(),
                'block' => $customer->update(['status' => 'blocked']),
                default => $customer->update(['status' => 'active']),
            };
        }

        return back()->with('success', $customers->count().' customer(s) updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function customerData(CustomerRequest $request): array
    {
        $data = $request->safe()->only(['name', 'email', 'phone', 'status', 'date_of_birth', 'gender', 'locale', 'notes']);
        $data['marketing_opt_in'] = $request->boolean('marketing_opt_in');

        return $data;
    }

    /*
    | Import (Excel / CSV). Columns and saving: app/Modules/Customers/Imports/CustomersImport.php
    */
    public function importForm(): View
    {
        return view('customers::admin.import', [
            'columns' => (new CustomersImport)->describe(),
            'runs' => ImportRun::recent('customers'),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ImportRun::fileRules(), 'dry_run' => ['nullable', 'boolean']]);

        $run = ImportRun::start('customers', $request->file('file'), $request->user(), dryRun: $request->boolean('dry_run'));
        $run->process(new CustomersImport($run));   // small file: now · big file: queued in chunks

        return redirect()->route('admin.customers.import')->with(
            $run->status === 'failed' ? 'error' : 'success',
            match (true) {
                $run->status === 'failed' => 'The file could not be imported: '.$run->message,
                $run->queued => 'Import started in the background ('.number_format($run->total_rows).' rows). You will get a notification when it is done.',
                $run->dry_run => 'Check finished, nothing was saved: '.$run->summary().'.',
                default => 'Import finished: '.$run->summary().'.',
            },
        );
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(new TemplateExport(new CustomersImport), 'customers-import-template.xlsx');
    }
}
