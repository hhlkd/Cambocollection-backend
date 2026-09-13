<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rebuild payment_settings with the columns the application actually uses.
     *
     * Earlier migrations created this table with mismatched schemas
     * (key/value/type and bank_name/account_name/...), neither of which match
     * PaymentController/PaymentSetting. This drops and recreates it correctly.
     * The table only holds a single row of admin payment config, so dropping is safe.
     */
    public function up(): void
    {
        Schema::dropIfExists('payment_settings');

        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('khqr_image')->nullable();
            $table->string('aba_holder_name')->nullable();
            $table->string('aba_account')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
