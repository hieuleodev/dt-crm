<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanySettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(UserRole $role = UserRole::Sales, bool $active = true): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    public function test_sales_cannot_update_company_settings(): void
    {
        $sales = $this->user(UserRole::Sales);

        $this->actingAs($sales)->post('/settings/company', [
            'name' => 'Công ty Bị Sửa Trái Phép',
        ])->assertForbidden();
    }

    public function test_sales_does_not_see_company_data_in_settings(): void
    {
        $sales = $this->user(UserRole::Sales);

        $response = $this->actingAs($sales)->get('/settings');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->where('company', null)
        );
    }

    public function test_manager_sees_company_data_in_settings(): void
    {
        $manager = $this->user(UserRole::Manager);

        $response = $this->actingAs($manager)->get('/settings');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->has('company')
        );
    }

    public function test_manager_can_update_company_information(): void
    {
        $manager = $this->user(UserRole::Manager);

        $response = $this->actingAs($manager)->post('/settings/company', [
            'name' => 'CÔNG TY TNHH GIẢI PHÁP CÔNG NGHỆ D&T VIỆT NAM',
            'tax_id' => '0109998888',
            'hotline' => '0988 888 999',
            'email' => 'info@dandtcrm.vn',
            'address' => 'Tầng 10, Tòa nhà D&T Center, Cầu Giấy, Hà Nội',
            'website' => 'https://dandtcrm.vn',
            'representative' => 'Trần Văn Giám Đốc',
            'bank_name' => 'Vietcombank Chi nhánh Thăng Long',
            'bank_account_number' => '1234567890123',
            'bank_account_holder' => 'CONG TY D&T VIET NAM',
            'footer_quote_note' => 'Báo giá đã bao gồm thuế VAT 10% và chi phí vận chuyển toàn quốc.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Đã cập nhật thông tin doanh nghiệp thành công.');

        $company = CompanySetting::current();
        $this->assertSame('CÔNG TY TNHH GIẢI PHÁP CÔNG NGHỆ D&T VIỆT NAM', $company->name);
        $this->assertSame('0109998888', $company->tax_id);
        $this->assertSame('0988 888 999', $company->hotline);
        $this->assertSame('info@dandtcrm.vn', $company->email);
        $this->assertSame('Tầng 10, Tòa nhà D&T Center, Cầu Giấy, Hà Nội', $company->address);
        $this->assertSame('https://dandtcrm.vn', $company->website);
        $this->assertSame('Trần Văn Giám Đốc', $company->representative);
        $this->assertSame('Vietcombank Chi nhánh Thăng Long', $company->bank_name);
        $this->assertSame('1234567890123', $company->bank_account_number);
        $this->assertSame('CONG TY D&T VIET NAM', $company->bank_account_holder);
        $this->assertSame('Báo giá đã bao gồm thuế VAT 10% và chi phí vận chuyển toàn quốc.', $company->footer_quote_note);
    }

    public function test_manager_can_upload_company_logo(): void
    {
        Storage::fake('public');

        $manager = $this->user(UserRole::Manager);
        $file = UploadedFile::fake()->image('dt-logo.png', 200, 200);

        $response = $this->actingAs($manager)->post('/settings/company', [
            'name' => 'CÔNG TY D&T CÓ LOGO',
            'logo' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Đã cập nhật thông tin doanh nghiệp thành công.');

        $company = CompanySetting::current();
        $this->assertNotNull($company->logo_url);
        $this->assertStringStartsWith('/storage/logos/', $company->logo_url);

        $relativePath = str_replace('/storage/', '', $company->logo_url);
        Storage::disk('public')->assertExists($relativePath);
    }
}
