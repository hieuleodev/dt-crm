<?php

namespace App\Http\Controllers\Customers;

use App\Domain\Customer\Actions\DataCompleteness;
use App\Domain\Customer\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ContactRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ContactController extends Controller
{
    public function store(ContactRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $this->save($customer, new Contact, $request->validated());

        return back()->with('success', 'Đã thêm người liên hệ.');
    }

    public function update(ContactRequest $request, Customer $customer, Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $this->save($customer, $contact, $request->validated());

        return back()->with('success', 'Đã cập nhật người liên hệ.');
    }

    public function destroy(Customer $customer, Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $contact->delete();
        DataCompleteness::refresh($customer);

        return back()->with('success', 'Đã xóa người liên hệ.');
    }

    private function save(Customer $customer, Contact $contact, array $data): void
    {
        DB::transaction(function () use ($customer, $contact, $data) {
            if (! empty($data['is_primary'])) {
                $customer->contacts()->whereKeyNot($contact->id ?? 0)->update(['is_primary' => false]);
            }
            $contact->fill($data);
            $contact->customer()->associate($customer);
            $contact->save();
            DataCompleteness::refresh($customer);
        });
    }
}