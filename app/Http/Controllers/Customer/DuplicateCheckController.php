<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Customer\Actions\FindDuplicateCustomers;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DuplicateCheckController extends Controller
{
    public function __invoke(Request $request, FindDuplicateCustomers $finder): JsonResponse
    {
        $data = $request->validate([
            'tax_code' => ['nullable', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'max:255'],
            'except' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'matches' => $finder->handle($data['tax_code'] ?? null, $data['name'] ?? null, $data['phone'] ?? null, $data['email'] ?? null, $data['except'] ?? null),
        ]);
    }
}