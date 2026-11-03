<?php

namespace Database\Seeders;

use App\Domain\Customer\Actions\DataCompleteness;
use App\Domain\Customer\Actions\SaveCustomer;
use App\Domain\Customer\Models\Customer;
use App\Domain\Staff\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', UserRole::Admin)->firstOrFail();
        $sale1 = User::updateOrCreate(['email' => 'sale1@trangthietbi.vn'], ['name' => 'Nguyễn Minh Anh', 'password' => 'Sale@12345', 'role' => UserRole::Sales, 'is_active' => true]);
        $sale2 = User::updateOrCreate(['email' => 'sale2@trangthietbi.vn'], ['name' => 'Trần Quốc Bảo', 'password' => 'Sale@12345', 'role' => UserRole::Sales, 'is_active' => true]);
        auth()->login($admin);

        $rows = [
            ['DEMO01', 'Công ty TNHH Điện tử Minh Long (demo)', '0109000001', 'fdi', 'electronics', 'Bắc Ninh', 'KCN Yên Phong', 4200, 'A', 'loyal', $sale1, 'seo',
                [['Lê Thu Hà', 'Purchasing Manager', 'purchasing', 'decision_maker', '0912000001', 'ha.le@minhlong.test', true], ['Phạm Văn Đức', 'EHS Supervisor', 'ehs', 'influencer', '0912000002', null, false]]],
            ['DEMO02', 'Công ty TNHH Linh kiện Bắc Hà (demo)', '0109000002', 'fdi', 'electronics', 'Bắc Giang', 'KCN Quang Châu', 2800, 'A', 'at_risk', $sale1, 'referral',
                [['Ngô Thanh Tâm', 'Senior Buyer', 'purchasing', 'decision_maker', '0912000003', 'tam.ngo@bacha.test', true]]],
            ['DEMO03', 'Công ty CP Thép Hưng Phát (demo)', '0109000003', 'domestic', 'steel', 'Hải Phòng', 'KCN Đình Vũ', 1500, 'B', 'repeat', $sale2, 'outbound',
                [['Đỗ Mạnh Hùng', 'Trưởng phòng An toàn', 'ehs', 'decision_maker', '0912000004', null, true], ['Bùi Lan', 'Nhân viên mua hàng', 'purchasing', 'user', '0912000005', 'lan.bui@hungphat.test', false]]],
            ['DEMO04', 'Công ty TNHH Thực phẩm Sao Mai (demo)', null, 'domestic', 'food', 'Hưng Yên', 'KCN Phố Nối A', 900, 'B', 'new_customer', $sale2, 'ai_search',
                [['Hoàng Ngọc Mai', 'Admin & Purchasing', 'purchasing', 'decision_maker', '0912000006', null, true]]],
            ['DEMO05', 'Công ty TNHH Kính Ánh Dương (demo)', '0109000005', 'fdi', 'glass', 'Hải Dương', null, null, 'C', 'quoting', $sale1, 'tender', []],
            ['DEMO06', 'Công ty CP Xây lắp Đông Á (demo)', null, 'contractor', 'construction', 'Hà Nội', null, 600, null, 'contacted', $sale2, 'event',
                [['Phan Đình Khoa', 'Chỉ huy trưởng', 'production', 'influencer', '0912000007', null, true]]],
            ['DEMO07', 'Công ty TNHH Dầu khí Biển Xanh (demo)', '0109000007', 'fdi', 'oil_gas', 'Bà Rịa - Vũng Tàu', 'KCN Phú Mỹ', 1200, 'A', 'sampling', null, 'linkedin', []],
            ['DEMO08', 'Công ty TNHH Cơ khí Tân Tiến (demo)', null, null, 'mechanical', null, null, null, null, 'dormant', $sale1, null, []],
        ];

        $save = new SaveCustomer;
        foreach ($rows as [$code, $name, $tax, $type, $industry, $province, $kcn, $workers, $tier, $stage, $owner, $channel, $contacts]) {
            if (Customer::withTrashed()->where('code', $code)->exists()) {
                continue;
            }
            $customer = $save->handle([
                'code' => $code, 'name' => $name, 'tax_code' => $tax, 'company_type' => $type, 'industry' => $industry,
                'province' => $province, 'industrial_zone' => $kcn, 'employee_count' => $workers, 'tier' => $tier,
                'lifecycle_stage' => $stage, 'status' => 'active', 'owner_id' => $owner?->id, 'source_channel' => $channel,
                'tags' => $type === 'fdi' ? ['FDI'] : [],
            ], $admin);
            if (! $owner) {
                $customer->update(['owner_id' => null]);
            }
            foreach ($contacts as [$fullName, $title, $role, $influence, $phone, $email, $primary]) {
                $customer->contacts()->create(['full_name' => $fullName, 'title' => $title, 'buying_role' => $role, 'influence' => $influence, 'phone' => $phone, 'email' => $email, 'is_primary' => $primary]);
            }
            DataCompleteness::refresh($customer);
        }
    }
}