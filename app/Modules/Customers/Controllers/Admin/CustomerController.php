<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Core\Import\CsvImport;
use App\Core\Import\HandlesImport;
use App\Http\Controllers\Controller;
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
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel: Customers list, profile page, add / edit, trash, bulk actions, CSV import.
 * Account actions (block, reset 2FA, sign in as…) are in CustomerActionController.
 */
class CustomerController extends Controller implements HasMiddleware
{
    use HandlesImport;

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
            ->export('customers', [
                'ID' => 'id',
                'Name' => 'name',
                'Email' => 'email',
                'Phone' => 'phone',
                'Status' => 'status',
                'Email confirmed' => fn (Customer $c) => $c->email_verified_at ? 'yes' : 'no',
                '2FA' => fn (Customer $c) => $c->two_factor_confirmed_at ? 'on' : 'off',
                'Marketing' => fn (Customer $c) => $c->marketing_opt_in ? 'yes' : 'no',
                'Addresses' => 'addresses_count',
                'Last login' => 'last_login_at',
                'Joined' => 'created_at',
            ], 'customers.export')
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
     * "Import CSV": rows are matched by email. New customers get a set-password email
     * and count as confirmed (staff vouch for the address).
     *
     * @return CsvImport<Customer>
     */
    protected function importer(): CsvImport
    {
        return CsvImport::make(Customer::class)
            ->matchBy('email')
            ->column('name', ['required', 'string', 'max:255'], example: 'Anita Raj')
            ->column('email', fn (?Customer $c) => ['required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($c)], example: 'anita@example.com')
            ->column('phone', ['nullable', 'string', 'max:30'], example: '9876543210')
            ->column('status', ['nullable', Rule::in(array_keys(Customer::STATUSES))], example: 'active')
            ->column('marketing_opt_in', ['nullable', 'boolean'], example: '0')
            ->column('notes', ['nullable', 'string', 'max:2000'])
            ->using(function (array $data, ?Customer $customer) {
                $data['email'] = strtolower($data['email'] ?? (string) $customer?->email);

                if ($customer) {
                    $customer->update($data);

                    return;
                }

                $customer = new Customer($data + ['status' => 'active']);
                $customer->password = Str::password(32);
                $customer->email_verified_at = now();
                $customer->save();
                $customer->notify(new CustomerResetPassword(Password::broker('customers')->createToken($customer), newAccount: true));
            });
    }

    /**
     * @return array{title: string, slug: string, back: string, breadcrumbs: array<int, string>, help: string}
     */
    protected function importPage(): array
    {
        return [
            'title' => 'Customers', 'slug' => 'customers', 'back' => route('admin.customers.index'),
            'breadcrumbs' => ['Customers', 'Import'],
            'help' => 'Rows are matched by <strong>email</strong>. New customers get an email with a link to set their password.',
        ];
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
}
