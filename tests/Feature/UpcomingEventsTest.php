<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\Customer;
use App\Models\User;
use App\Services\Care\UpcomingEventsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpcomingEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(UserRole $role): User
    {
        return User::create([
            'name' => $role->label().' '.uniqid(),
            'email' => uniqid().'@test.vn',
            'password' => 'Secret123!',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_detects_company_anniversary_within_7_days(): void
    {
        $sales = $this->user(UserRole::Sales);

        // Giả lập mốc thời gian là 15/11/2026
        $mockDate = CarbonImmutable::create(2026, 11, 15, 9, 0, 0, 'Asia/Ho_Chi_Minh');

        // Khách hàng 1: thành lập ngày 18/11/2018 (kỷ niệm 8 năm, còn 3 ngày) -> nằm trong 7 ngày
        $customer1 = Customer::create([
            'code' => 'KH00001',
            'name' => 'Công ty TNHH An Phát',
            'status' => 'active',
            'owner_id' => $sales->id,
            'founded_date' => '2018-11-18',
        ]);

        // Khách hàng 2: thành lập ngày 15/11/2020 (kỷ niệm 6 năm, đúng ngày hôm nay) -> nằm trong 7 ngày
        $customer2 = Customer::create([
            'code' => 'KH00002',
            'name' => 'Công ty CP Minh Châu',
            'status' => 'active',
            'owner_id' => $sales->id,
            'founded_date' => '2020-11-15',
        ]);

        // Khách hàng 3: thành lập ngày 30/11/2019 (còn 15 ngày) -> ngoài 7 ngày
        Customer::create([
            'code' => 'KH00003',
            'name' => 'Công ty TNHH Viễn Đông',
            'status' => 'active',
            'owner_id' => $sales->id,
            'founded_date' => '2019-11-30',
        ]);

        $service = new UpcomingEventsService;
        $result = $service->getUpcomingEvents($sales, $mockDate);

        // Khách hàng 1 và 2 phải xuất hiện
        $companyEvents = array_filter($result['events'], fn ($e) => $e['category'] === 'company_anniversary');
        $this->assertCount(2, $companyEvents);

        $titles = array_column($companyEvents, 'title');
        $this->assertContains('Công ty TNHH An Phát', $titles);
        $this->assertContains('Công ty CP Minh Châu', $titles);

        // Kiểm tra tính toán số năm và đếm ngược
        $eventToday = collect($companyEvents)->firstWhere('title', 'Công ty CP Minh Châu');
        $this->assertEquals(0, $eventToday['days_left']);
        $this->assertTrue($eventToday['is_today']);
        $this->assertEquals('Hôm nay!', $eventToday['badge']);
        $this->assertEquals('Kỷ niệm 6 năm thành lập', $eventToday['subtitle']);

        $eventIn3Days = collect($companyEvents)->firstWhere('title', 'Công ty TNHH An Phát');
        $this->assertEquals(3, $eventIn3Days['days_left']);
        $this->assertFalse($eventIn3Days['is_today']);
        $this->assertEquals('Còn 3 ngày', $eventIn3Days['badge']);
        $this->assertEquals('Kỷ niệm 8 năm thành lập', $eventIn3Days['subtitle']);
    }

    public function test_detects_holidays_within_7_days(): void
    {
        $sales = $this->user(UserRole::Sales);

        // Mốc thời gian là 16/11/2026 -> trong 7 ngày có ngày Nhà giáo VN 20/11 (còn 4 ngày)
        $mockDate = CarbonImmutable::create(2026, 11, 16, 9, 0, 0, 'Asia/Ho_Chi_Minh');

        $service = new UpcomingEventsService;
        $result = $service->getUpcomingEvents($sales, $mockDate);

        $holidays = array_filter($result['events'], fn ($e) => $e['category'] === 'holiday');
        $this->assertNotEmpty($holidays);

        $teacherDay = collect($holidays)->firstWhere('title', 'Ngày Nhà giáo Việt Nam (20/11)');
        $this->assertNotNull($teacherDay);
        $this->assertEquals(4, $teacherDay['days_left']);
        $this->assertEquals('Còn 4 ngày', $teacherDay['badge']);
    }

    public function test_sales_only_sees_own_customer_anniversaries(): void
    {
        $sales1 = $this->user(UserRole::Sales);
        $sales2 = $this->user(UserRole::Sales);

        $mockDate = CarbonImmutable::create(2026, 11, 15, 9, 0, 0, 'Asia/Ho_Chi_Minh');

        Customer::create([
            'code' => 'KH00001',
            'name' => 'Khách của Sales 1',
            'status' => 'active',
            'owner_id' => $sales1->id,
            'founded_date' => '2018-11-18',
        ]);

        Customer::create([
            'code' => 'KH00002',
            'name' => 'Khách của Sales 2',
            'status' => 'active',
            'owner_id' => $sales2->id,
            'founded_date' => '2018-11-18',
        ]);

        $service = new UpcomingEventsService;
        $result = $service->getUpcomingEvents($sales1, $mockDate);

        $companyEvents = array_filter($result['events'], fn ($e) => $e['category'] === 'company_anniversary');
        $this->assertCount(1, $companyEvents);
        $this->assertEquals('Khách của Sales 1', $companyEvents[array_key_first($companyEvents)]['title']);
    }

    public function test_workbench_page_receives_upcoming_events(): void
    {
        $sales = $this->user(UserRole::Sales);

        $response = $this->actingAs($sales)->get('/workbench');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Workbench/Index')
                ->has('upcomingEvents')
                ->has('upcomingEvents.events')
                ->has('upcomingEvents.total')
                ->has('upcomingEvents.notice_days'),
        );
    }

    public function test_events_page_renders_and_supports_filtering(): void
    {
        $sales = $this->user(UserRole::Sales);

        $response = $this->actingAs($sales)->get('/events');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Events/Index')
                ->has('eventsData')
                ->has('filters')
                ->has('options')
                ->has('assignees'),
        );

        // Test filtering by category and range
        $filteredResponse = $this->actingAs($sales)->get('/events?range=30&category=holiday');
        $filteredResponse->assertOk();
        $filteredResponse->assertInertia(
            fn ($page) => $page
                ->component('Events/Index')
                ->where('filters.range', '30')
                ->where('filters.category', 'holiday'),
        );
    }
}
