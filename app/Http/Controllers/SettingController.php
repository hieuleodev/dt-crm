<?php

namespace App\Http\Controllers;

use App\Enums\Customer\CompanyType;
use App\Enums\Customer\CustomerTier;
use App\Enums\Customer\Industry;
use App\Enums\Customer\LifecycleStage;
use App\Enums\Customer\ProcurementMethod;
use App\Enums\Customer\ProductCategory;
use App\Enums\Customer\SourceChannel;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /**
     * Display the settings and profile page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user && $user->is_active, 403);

        $users = $user->isManager()
            ? User::query()
                ->select(['id', 'name', 'email', 'phone', 'role', 'is_active', 'created_at', 'last_login_at'])
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'role' => $u->role->value,
                    'roleLabel' => $u->role->label(),
                    'is_active' => (bool) $u->is_active,
                    'created_at' => $u->created_at?->format('d/m/Y H:i'),
                    'last_login_at' => $u->last_login_at?->format('d/m/Y H:i') ?? 'Chưa đăng nhập',
                ])
            : [];

        $masterData = $user->isManager() ? [
            'leadStages' => array_map(fn ($k, $label) => [
                'key' => $k,
                'label' => $label,
            ], array_keys(Lead::STAGES['lead']), Lead::STAGES['lead']),
            'opportunityStages' => array_map(fn ($k, $label) => [
                'key' => $k,
                'label' => $label,
                'probability' => Lead::STAGE_PROBABILITIES[$k] ?? 0,
            ], array_keys(Lead::STAGES['opportunity']), Lead::STAGES['opportunity']),
            'lostReasons' => array_map(fn ($k, $label) => [
                'key' => $k,
                'label' => $label,
            ], array_keys(Lead::LOST_REASONS), Lead::LOST_REASONS),
            'customerTiers' => CustomerTier::options(),
            'lifecycleStages' => LifecycleStage::options(),
            'productCategories' => ProductCategory::options(),
            'industries' => Industry::options(),
            'sourceChannels' => SourceChannel::options(),
            'companyTypes' => CompanyType::options(),
            'procurementMethods' => ProcurementMethod::options(),
        ] : null;

        return Inertia::render('Settings/Index', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'roleLabel' => $user->role->label(),
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->format('d/m/Y H:i'),
                'last_login_at' => $user->last_login_at?->format('d/m/Y H:i') ?? 'Chưa ghi nhận',
            ],
            'users' => $users,
            'company' => $user->isManager() ? CompanySetting::current() : null,
            'masterData' => $masterData,
        ]);
    }

    /**
     * Update company setting information.
     */
    public function updateCompany(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->is_active && $user->isManager(), 403);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'hotline' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'representative' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'footer_quote_note' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'logo_url' => ['nullable', 'string', 'max:500'],
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $validated['logo_url'] = '/storage/'.$path;
        }

        unset($validated['logo']);

        CompanySetting::current()->update($validated);

        return back()->with('success', 'Đã cập nhật thông tin doanh nghiệp thành công.');
    }

    /**
     * Update the authenticated user's profile information.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->is_active, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ]);

        $user->update($validated);

        return back()->with('success', 'Đã cập nhật thông tin cá nhân.');
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->is_active, 403);

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Đã đổi mật khẩu thành công.');
    }
}
