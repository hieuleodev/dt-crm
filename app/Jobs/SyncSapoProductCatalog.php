<?php

namespace App\Jobs;

use App\Services\Sapo\SapoProductCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Throwable;
use UnexpectedValueException;

class SyncSapoProductCatalog implements ShouldQueue
{
    use Queueable;

    public const RESULT_CACHE_KEY = 'sapo:catalog-sync-result';

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct()
    {
        $this->onConnection('sapo')->onQueue('sapo');
    }

    /**
     * Worker đọc và lưu từng trang để lượt bấm trên web trả về ngay.
     */
    public function handle(SapoProductCatalog $catalog): void
    {
        $count = $catalog->sync();
        $this->recordResult('succeeded', "Đã đồng bộ {$count} phiên bản sản phẩm từ Sapo.");
    }

    public function failed(Throwable $exception): void
    {
        // Giữ thông báo nghiệp vụ cho giao diện và tránh trả nội dung kỹ thuật của API.
        $message = match (true) {
            $exception instanceof ConnectionException => 'Không kết nối được Sapo. Hãy kiểm tra mạng và domain cửa hàng.',
            $exception instanceof UnexpectedValueException => 'Sapo trả dữ liệu sai định dạng. Lượt đồng bộ chưa hoàn tất.',
            $exception instanceof RequestException => match ($exception->response->status()) {
                401 => 'Sapo từ chối xác thực. Hãy kiểm tra API Key và API Secret hoặc kết nối lại OAuth.',
                403 => 'Ứng dụng Sapo chưa được cấp quyền read_products.',
                429 => 'Sapo đang giới hạn tần suất gọi API. Hãy chờ rồi thử lại.',
                default => "Sapo trả về lỗi HTTP {$exception->response->status()}.",
            },
            default => 'Lượt đồng bộ Sapo thất bại. Hãy thử lại.',
        };

        $this->recordResult('failed', $message);
    }

    private function recordResult(string $state, string $message): void
    {
        Cache::store('database')->put(self::RESULT_CACHE_KEY, [
            'state' => $state,
            'message' => $message,
        ], now()->addDay());
    }
}
