<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payload_decoder_profile_product');
        Schema::create('payload_decoder_profile_product', function (Blueprint $table): void {
            $table->ulid('payload_decoder_profile_id');
            $table->ulid('product_id');
            $table->primary(['payload_decoder_profile_id', 'product_id'], 'payload_profile_product_primary');
            $table->foreign('payload_decoder_profile_id', 'payload_profile_decoder_fk')->references('id')->on('payload_decoder_profiles')->cascadeOnDelete();
            $table->foreign('product_id', 'payload_profile_product_fk')->references('id')->on('products')->cascadeOnDelete();
        });

        DB::table('payload_decoder_profiles')->whereNotNull('product_id')->orderBy('id')->each(function (object $profile): void {
            DB::table('payload_decoder_profile_product')->insertOrIgnore([
                'payload_decoder_profile_id' => $profile->id,
                'product_id' => $profile->product_id,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payload_decoder_profile_product');
    }
};
