<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $seen = [];
            $updates = [];

            // Rà toàn bộ tài khoản, kể cả đã xóa mềm, trước khi thay đổi bất kỳ email nào.
            foreach (DB::table('users')->select(['id', 'email'])->lazyById() as $user) {
                $normalized = User::normalizeLoginEmail($user->email);
                if (isset($seen[$normalized])) {
                    throw new RuntimeException('Login emails conflict after normalization for user IDs '.$seen[$normalized].' and '.$user->id.'. Resolve the duplicate accounts before migrating.');
                }
                $seen[$normalized] = $user->id;

                if ($user->email !== $normalized) {
                    $updates[$user->id] = $normalized;
                }
            }

            foreach ($updates as $id => $email) {
                DB::table('users')->where('id', $id)->update(['email' => $email]);
            }

            if (DB::getDriverName() === 'sqlite') {
                DB::statement('CREATE UNIQUE INDEX users_email_normalized_unique ON users (LOWER(TRIM(email)))');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Email đã chuẩn hóa tiếp tục dùng được khi rollback chỉ mục bổ sung.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS users_email_normalized_unique');
        }
    }
};
