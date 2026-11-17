<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('studio_mfa_secret')->nullable();
            $table->json('studio_mfa_recovery_hashes')->nullable();
            $table->unsignedBigInteger('studio_mfa_last_counter')->nullable();
            $table->timestamp('studio_mfa_enabled_at')->nullable();
        });
        Schema::table('studio_templates', function (Blueprint $table): void {
            $table->string('approval_status', 16)->default('none');
            $table->unsignedInteger('approval_requested_revision')->nullable();
            $table->foreignId('approval_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approval_requested_at')->nullable();
            $table->text('approval_rejection_reason')->nullable();
            $table->index(['company_id','approval_status']);
        });
    }

    public function down(): void
    {
        Schema::table('studio_templates', function (Blueprint $table): void {
            $table->dropIndex(['company_id','approval_status']);
            $table->dropConstrainedForeignId('approval_requested_by');
            $table->dropColumn(['approval_status','approval_requested_revision','approval_requested_at','approval_rejection_reason']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['studio_mfa_secret','studio_mfa_recovery_hashes','studio_mfa_last_counter','studio_mfa_enabled_at']);
        });
    }
};
