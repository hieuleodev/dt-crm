<?php

namespace App\Services\Care;

use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;

class UpcomingEventsService
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public const NOTICE_DAYS = 7; // Thông báo trước 1 tuần

    /**
     * Danh sách ngày lễ và kỷ niệm cố định / theo năm tại Việt Nam.
     * Cung cấp thông tin ngày, tháng, tên ngày lễ, biểu tượng, gợi ý hoạt động.
     *
     * @return list<array{month: int, day: int, name: string, icon: string, description: string}>
     */
    public function getHolidays(int $year): array
    {
        $fixed = [
            ['month' => 1, 'day' => 1, 'name' => 'Tết Dương Lịch', 'icon' => '🎆', 'description' => 'Nghỉ Tết Dương Lịch toàn quốc'],
            ['month' => 2, 'day' => 14, 'name' => 'Lễ tình nhân (Valentine)', 'icon' => '💖', 'description' => 'Ngày lễ tình nhân'],
            ['month' => 2, 'day' => 27, 'name' => 'Ngày Thầy thuốc Việt Nam', 'icon' => '🩺', 'description' => 'Tri ân các y bác sĩ, đối tác ngành y tế/dược'],
            ['month' => 3, 'day' => 8, 'name' => 'Ngày Quốc tế Phụ nữ (8/3)', 'icon' => '💐', 'description' => 'Chúc mừng khách hàng và đối tác nữ'],
            ['month' => 3, 'day' => 26, 'name' => 'Ngày Thành lập Đoàn TNCS Hồ Chí Minh', 'icon' => '🎖️', 'description' => 'Kỷ niệm ngày thành lập Đoàn'],
            ['month' => 4, 'day' => 30, 'name' => 'Ngày Giải phóng Miền Nam (30/4)', 'icon' => '🇻🇳', 'description' => 'Đại lễ thống nhất đất nước'],
            ['month' => 5, 'day' => 1, 'name' => 'Ngày Quốc tế Lao động (1/5)', 'icon' => '🛠️', 'description' => 'Ngày Quốc tế Lao động'],
            ['month' => 6, 'day' => 1, 'name' => 'Ngày Quốc tế Thiếu nhi (1/6)', 'icon' => '🎈', 'description' => 'Tặng quà con em đối tác/khách hàng'],
            ['month' => 6, 'day' => 21, 'name' => 'Ngày Báo chí Cách mạng Việt Nam', 'icon' => '📰', 'description' => 'Chúc mừng đối tác truyền thông, báo chí'],
            ['month' => 6, 'day' => 28, 'name' => 'Ngày Gia đình Việt Nam', 'icon' => '👨‍👩‍👧‍👦', 'description' => 'Tôn vinh giá trị gia đình'],
            ['month' => 7, 'day' => 27, 'name' => 'Ngày Thương binh Liệt sĩ', 'icon' => '🕯️', 'description' => 'Tri ân các anh hùng liệt sĩ, người có công'],
            ['month' => 8, 'day' => 19, 'name' => 'Ngày Cách mạng Tháng Tám', 'icon' => '🚩', 'description' => 'Kỷ niệm Cách mạng Tháng Tám'],
            ['month' => 9, 'day' => 2, 'name' => 'Ngày Quốc khánh Việt Nam (2/9)', 'icon' => '🇻🇳', 'description' => 'Đại lễ Quốc khánh nước CHXHCN Việt Nam'],
            ['month' => 10, 'day' => 13, 'name' => 'Ngày Doanh nhân Việt Nam', 'icon' => '💼', 'description' => 'Chúc mừng ban lãnh đạo doanh nghiệp, khách hàng VIP'],
            ['month' => 10, 'day' => 20, 'name' => 'Ngày Phụ nữ Việt Nam (20/10)', 'icon' => '🌹', 'description' => 'Gửi hoa, thiệp chúc mừng đối tác/khách hàng nữ'],
            ['month' => 11, 'day' => 20, 'name' => 'Ngày Nhà giáo Việt Nam (20/11)', 'icon' => '🎓', 'description' => 'Tri ân thầy cô giáo, đối tác giáo dục/đào tạo'],
            ['month' => 12, 'day' => 22, 'name' => 'Ngày Thành lập Quân đội Nhân dân VN', 'icon' => '⭐', 'description' => 'Kỷ niệm ngày hội Quốc phòng toàn dân'],
            ['month' => 12, 'day' => 25, 'name' => 'Lễ Giáng sinh (Noel)', 'icon' => '🎄', 'description' => 'Chúc mừng Giáng sinh và chuẩn bị đón năm mới'],
        ];

        $lunarHolidaysByYear = [
            2026 => [
                ['month' => 2, 'day' => 16, 'name' => '29 Tết Nguyên Đán (Giao thừa)', 'icon' => '🏮', 'description' => 'Nghỉ Tết Nguyên Đán Bính Ngọ 2026'],
                ['month' => 2, 'day' => 17, 'name' => 'Mùng 1 Tết Nguyên Đán', 'icon' => '🧧', 'description' => 'Tết Nguyên Đán Bính Ngọ 2026'],
                ['month' => 2, 'day' => 18, 'name' => 'Mùng 2 Tết Nguyên Đán', 'icon' => '🧧', 'description' => 'Tết Nguyên Đán Bính Ngọ 2026'],
                ['month' => 2, 'day' => 19, 'name' => 'Mùng 3 Tết Nguyên Đán', 'icon' => '🧧', 'description' => 'Tết Nguyên Đán Bính Ngọ 2026'],
                ['month' => 4, 'day' => 26, 'name' => 'Giỗ Tổ Hùng Vương (10/3 ÂL)', 'icon' => '👑', 'description' => 'Ngày Giỗ Tổ Hùng Vương'],
                ['month' => 9, 'day' => 25, 'name' => 'Tết Trung Thu (15/8 ÂL)', 'icon' => '🥮', 'description' => 'Tết Trung Thu - Gửi quà bánh cho đối tác'],
            ],
            2027 => [
                ['month' => 2, 'day' => 5, 'name' => '30 Tết Nguyên Đán (Giao thừa)', 'icon' => '🏮', 'description' => 'Nghỉ Tết Nguyên Đán Đinh Mùi 2027'],
                ['month' => 2, 'day' => 6, 'name' => 'Mùng 1 Tết Nguyên Đán', 'icon' => '🧧', 'description' => 'Tết Nguyên Đán Đinh Mùi 2027'],
                ['month' => 2, 'day' => 7, 'name' => 'Mùng 2 Tết Nguyên Đán', 'icon' => '🧧', 'description' => 'Tết Nguyên Đán Đinh Mùi 2027'],
                ['month' => 2, 'day' => 8, 'name' => 'Mùng 3 Tết Nguyên Đán', 'icon' => '🧧', 'description' => 'Tết Nguyên Đán Đinh Mùi 2027'],
                ['month' => 4, 'day' => 16, 'name' => 'Giỗ Tổ Hùng Vương (10/3 ÂL)', 'icon' => '👑', 'description' => 'Ngày Giỗ Tổ Hùng Vương'],
                ['month' => 9, 'day' => 15, 'name' => 'Tết Trung Thu (15/8 ÂL)', 'icon' => '🥮', 'description' => 'Tết Trung Thu - Gửi quà bánh cho đối tác'],
            ],
        ];

        $holidays = $fixed;
        if (isset($lunarHolidaysByYear[$year])) {
            $holidays = array_merge($holidays, $lunarHolidaysByYear[$year]);
        }

        return $holidays;
    }

    /**
     * Lấy các sự kiện trong vòng số ngày chỉ định (mặc định 7 ngày tới - Ngày thành lập công ty khách hàng + Ngày lễ).
     *
     * @return array{
     *     events: list<array<string, mixed>>,
     *     total: int,
     *     today_count: int,
     *     company_count: int,
     *     holiday_count: int,
     *     notice_days: int
     * }
     */
    public function getUpcomingEvents(User $user, ?CarbonImmutable $asOf = null, int $noticeDays = self::NOTICE_DAYS): array
    {
        $noticeDays = max(1, $noticeDays);
        $now = ($asOf ?? CarbonImmutable::now(self::TIMEZONE))->startOfDay();
        $windowEnd = $now->addDays($noticeDays)->endOfDay();

        $events = [];

        // 1. Ngày kỷ niệm thành lập của các công ty khách hàng
        $customers = Customer::query()
            ->visibleTo($user)
            ->when(! $user->isManager(), fn ($query) => $query->where('owner_id', $user->id))
            ->whereNotNull('founded_date')
            ->where('status', 'active')
            ->with('owner:id,name')
            ->get();

        foreach ($customers as $customer) {
            $founded = CarbonImmutable::parse($customer->founded_date, self::TIMEZONE);

            // Tính ngày kỷ niệm của năm nay
            $anniversary = $this->getAnnualOccurrence($founded->month, $founded->day, $now->year);

            // Nếu ngày kỷ niệm năm nay đã qua thì tính cho năm sau
            if ($anniversary->isBefore($now)) {
                $anniversary = $this->getAnnualOccurrence($founded->month, $founded->day, $now->year + 1);
            }

            // Kiểm tra xem có rơi vào khoảng 7 ngày tới (từ hôm nay đến hôm nay + 7 ngày)
            if ($anniversary->greaterThanOrEqualTo($now) && $anniversary->lessThanOrEqualTo($windowEnd)) {
                $daysLeft = (int) $now->diffInDays($anniversary, false);
                $years = $anniversary->year - $founded->year;

                $yearsText = $years > 0 ? "Kỷ niệm {$years} năm thành lập" : 'Ngày thành lập công ty';

                $events[] = [
                    'id' => "company_{$customer->id}_{$anniversary->year}",
                    'category' => 'company_anniversary',
                    'category_label' => 'Ngày thành lập công ty',
                    'icon' => '🏢',
                    'title' => $customer->name,
                    'subtitle' => $yearsText,
                    'description' => "Ngày thành lập: {$founded->format('d/m/Y')}. Sắp tới ngày kỷ niệm {$years} năm.",
                    'event_date' => $anniversary->format('Y-m-d'),
                    'display_date' => $anniversary->format('d/m/Y'),
                    'days_left' => $daysLeft,
                    'is_today' => $daysLeft === 0,
                    'badge' => $this->formatBadge($daysLeft),
                    'customer' => [
                        'id' => $customer->id,
                        'code' => $customer->code,
                        'name' => $customer->name,
                        'href' => route('customers.show', $customer->id),
                        'phone' => $customer->phone,
                        'owner_name' => $customer->owner?->name,
                    ],
                    'suggested_task' => [
                        'title' => "Chúc mừng {$years} năm ngày thành lập: {$customer->name}",
                        'reference' => "customer:{$customer->id}",
                        'due_at' => $anniversary->setHour(9)->setMinute(0)->utc()->toIso8601String(),
                        'type' => 'meeting',
                    ],
                ];
            }
        }

        // 2. Các ngày lễ trong vòng 7 ngày tới
        $yearsToCheck = array_unique([$now->year, $windowEnd->year]);
        foreach ($yearsToCheck as $year) {
            $holidays = $this->getHolidays($year);
            foreach ($holidays as $h) {
                $holidayDate = $this->getAnnualOccurrence($h['month'], $h['day'], $year);

                if ($holidayDate->greaterThanOrEqualTo($now) && $holidayDate->lessThanOrEqualTo($windowEnd)) {
                    $daysLeft = (int) $now->diffInDays($holidayDate, false);

                    $events[] = [
                        'id' => "holiday_{$year}_{$h['month']}_{$h['day']}",
                        'category' => 'holiday',
                        'category_label' => 'Ngày lễ & Kỷ niệm',
                        'icon' => $h['icon'] ?? '🎈',
                        'title' => $h['name'],
                        'subtitle' => 'Ngày lễ quốc gia / sự kiện đặc biệt',
                        'description' => $h['description'] ?? '',
                        'event_date' => $holidayDate->format('Y-m-d'),
                        'display_date' => $holidayDate->format('d/m/Y'),
                        'days_left' => $daysLeft,
                        'is_today' => $daysLeft === 0,
                        'badge' => $this->formatBadge($daysLeft),
                        'customer' => null,
                        'suggested_task' => [
                            'title' => "Gửi thông báo & lời chúc dịp {$h['name']}",
                            'reference' => null,
                            'due_at' => $holidayDate->setHour(9)->setMinute(0)->utc()->toIso8601String(),
                            'type' => 'call',
                        ],
                    ];
                }
            }
        }

        // Sắp xếp các sự kiện theo ngày diễn ra gần nhất đến xa nhất (0 ngày -> 7 ngày)
        usort($events, function ($a, $b) {
            if ($a['days_left'] === $b['days_left']) {
                if ($a['category'] !== $b['category']) {
                    return $a['category'] === 'company_anniversary' ? -1 : 1;
                }

                return strcmp((string) $a['title'], (string) $b['title']);
            }

            return $a['days_left'] <=> $b['days_left'];
        });

        $todayCount = count(array_filter($events, fn ($e) => $e['is_today']));
        $companyCount = count(array_filter($events, fn ($e) => $e['category'] === 'company_anniversary'));
        $holidayCount = count(array_filter($events, fn ($e) => $e['category'] === 'holiday'));

        return [
            'events' => $events,
            'total' => count($events),
            'today_count' => $todayCount,
            'company_count' => $companyCount,
            'holiday_count' => $holidayCount,
            'notice_days' => $noticeDays,
        ];
    }

    /**
     * Lấy các sự kiện (kỷ niệm thành lập công ty khách hàng + ngày lễ) nằm trong khoảng ngày chỉ định.
     * Thường dùng cho Lịch trình (Calendar) theo tháng, tuần, ngày, agenda.
     *
     * @return list<array<string, mixed>>
     */
    public function getEventsInRange(User $user, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $start = $startDate->startOfDay();
        $end = $endDate->endOfDay();
        $now = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        $events = [];

        // 1. Ngày kỷ niệm thành lập công ty khách hàng
        $customers = Customer::query()
            ->visibleTo($user)
            ->when(! $user->isManager(), fn ($query) => $query->where('owner_id', $user->id))
            ->whereNotNull('founded_date')
            ->where('status', 'active')
            ->with('owner:id,name')
            ->get();

        $years = range($start->year, $end->year);

        foreach ($customers as $customer) {
            $founded = CarbonImmutable::parse($customer->founded_date, self::TIMEZONE);

            foreach ($years as $year) {
                $anniversary = $this->getAnnualOccurrence($founded->month, $founded->day, $year);

                if ($anniversary->greaterThanOrEqualTo($start) && $anniversary->lessThanOrEqualTo($end)) {
                    $daysLeft = (int) $now->diffInDays($anniversary, false);
                    $anniversaryYears = $anniversary->year - $founded->year;
                    $yearsText = $anniversaryYears > 0 ? "Kỷ niệm {$anniversaryYears} năm thành lập" : 'Ngày thành lập công ty';

                    $events[] = [
                        'id' => "company_{$customer->id}_{$anniversary->year}",
                        'category' => 'company_anniversary',
                        'category_label' => 'Ngày thành lập công ty',
                        'icon' => '🏢',
                        'title' => $customer->name,
                        'subtitle' => $yearsText,
                        'description' => "Ngày thành lập: {$founded->format('d/m/Y')}. Kỷ niệm {$anniversaryYears} năm.",
                        'event_date' => $anniversary->format('Y-m-d'),
                        'display_date' => $anniversary->format('d/m/Y'),
                        'days_left' => $daysLeft,
                        'is_today' => $daysLeft === 0,
                        'badge' => $this->formatBadge($daysLeft),
                        'customer' => [
                            'id' => $customer->id,
                            'code' => $customer->code,
                            'name' => $customer->name,
                            'href' => route('customers.show', $customer->id),
                            'phone' => $customer->phone,
                            'owner_name' => $customer->owner?->name,
                        ],
                        'suggested_task' => [
                            'title' => "Chúc mừng {$anniversaryYears} năm ngày thành lập: {$customer->name}",
                            'reference' => "customer:{$customer->id}",
                            'due_at' => $anniversary->setHour(9)->setMinute(0)->utc()->toIso8601String(),
                            'type' => 'meeting',
                        ],
                    ];
                }
            }
        }

        // 2. Ngày lễ trong khoảng thời gian
        foreach ($years as $year) {
            $holidays = $this->getHolidays($year);
            foreach ($holidays as $h) {
                $holidayDate = $this->getAnnualOccurrence($h['month'], $h['day'], $year);

                if ($holidayDate->greaterThanOrEqualTo($start) && $holidayDate->lessThanOrEqualTo($end)) {
                    $daysLeft = (int) $now->diffInDays($holidayDate, false);

                    $events[] = [
                        'id' => "holiday_{$year}_{$h['month']}_{$h['day']}",
                        'category' => 'holiday',
                        'category_label' => 'Ngày lễ & Kỷ niệm',
                        'icon' => $h['icon'] ?? '🎈',
                        'title' => $h['name'],
                        'subtitle' => 'Ngày lễ quốc gia / sự kiện đặc biệt',
                        'description' => $h['description'] ?? '',
                        'event_date' => $holidayDate->format('Y-m-d'),
                        'display_date' => $holidayDate->format('d/m/Y'),
                        'days_left' => $daysLeft,
                        'is_today' => $daysLeft === 0,
                        'badge' => $this->formatBadge($daysLeft),
                        'customer' => null,
                        'suggested_task' => [
                            'title' => "Gửi thông báo & lời chúc dịp {$h['name']}",
                            'reference' => null,
                            'due_at' => $holidayDate->setHour(9)->setMinute(0)->utc()->toIso8601String(),
                            'type' => 'call',
                        ],
                    ];
                }
            }
        }

        // Sắp xếp theo ngày diễn ra
        usort($events, function ($a, $b) {
            if ($a['event_date'] === $b['event_date']) {
                if ($a['category'] !== $b['category']) {
                    return $a['category'] === 'company_anniversary' ? -1 : 1;
                }

                return strcmp((string) $a['title'], (string) $b['title']);
            }

            return strcmp((string) $a['event_date'], (string) $b['event_date']);
        });

        return $events;
    }

    private function getAnnualOccurrence(int $month, int $day, int $year): CarbonImmutable
    {
        if ($month === 2 && $day === 29 && ! checkdate(2, 29, $year)) {
            $day = 28;
        }

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, self::TIMEZONE);
    }

    private function formatBadge(int $daysLeft): string
    {
        if ($daysLeft === 0) {
            return 'Hôm nay!';
        }
        if ($daysLeft === 1) {
            return 'Ngày mai';
        }

        return "Còn {$daysLeft} ngày";
    }
}
