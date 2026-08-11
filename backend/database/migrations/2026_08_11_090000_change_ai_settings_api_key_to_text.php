<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * api_key sẽ được mã hóa (cast 'encrypted' trong AiSetting) — ciphertext dài hơn
     * giới hạn 255 ký tự của cột string, nên cần đổi sang text trước. Dữ liệu cũ đang
     * lưu plaintext cũng được mã hóa lại ngay trong migration này để khớp với cast mới
     * (đọc trực tiếp qua DB, không qua Eloquent, để tránh áp cast giải mã lên giá trị
     * còn là plaintext).
     */
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->text('api_key')->nullable()->change();
        });

        DB::table('ai_settings')
            ->whereNotNull('api_key')
            ->where('api_key', '!=', '')
            ->get(['id', 'api_key'])
            ->each(function ($row) {
                DB::table('ai_settings')->where('id', $row->id)->update([
                    'api_key' => Crypt::encryptString($row->api_key),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('ai_settings')
            ->whereNotNull('api_key')
            ->where('api_key', '!=', '')
            ->get(['id', 'api_key'])
            ->each(function ($row) {
                try {
                    $plain = Crypt::decryptString($row->api_key);
                } catch (\Throwable $e) {
                    return;
                }
                DB::table('ai_settings')->where('id', $row->id)->update([
                    'api_key' => $plain,
                ]);
            });

        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('api_key')->nullable()->change();
        });
    }
};
