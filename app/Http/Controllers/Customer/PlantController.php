<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\Plant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\PlantRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PlantController extends Controller
{
    public function store(PlantRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $customer->plants()->create($request->validated());

        return back()->with('success', 'Đã thêm nhà máy.');
    }

    public function update(PlantRequest $request, Customer $customer, Plant $plant): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $plant->update($request->validated());

        return back()->with('success', 'Đã cập nhật nhà máy.');
    }

    public function destroy(Customer $customer, Plant $plant): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $plant->delete();

        return back()->with('success', 'Đã xóa nhà máy.');
    }
}