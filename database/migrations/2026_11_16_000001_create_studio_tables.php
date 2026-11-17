<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('studio_companies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('studio_company_id')->nullable()->constrained('studio_companies')->restrictOnDelete();
            $table->string('studio_role', 24)->default('viewer');
            $table->boolean('studio_active')->default(false);
            $table->index(['studio_company_id', 'studio_role']);
        });

        Schema::create('studio_employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('studio_companies')->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->uuid('external_employee_uuid')->nullable();
            $table->string('employee_code', 48);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->string('department', 120)->nullable();
            $table->string('branch', 120)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('phone', 30)->nullable();
            $table->date('hire_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('sss_number')->nullable();
            $table->text('philhealth_number')->nullable();
            $table->text('pagibig_mid')->nullable();
            $table->text('tin')->nullable();
            $table->text('emergency_name')->nullable();
            $table->text('emergency_relationship')->nullable();
            $table->text('emergency_phone')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'employee_code']);
            $table->unique(['company_id', 'external_employee_uuid']);
            $table->index(['company_id', 'last_name', 'first_name']);
        });

        Schema::create('studio_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('studio_companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->decimal('width_mm', 6, 2)->default(85.60);
            $table->decimal('height_mm', 6, 2)->default(53.98);
            $table->json('draft_layout');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('published_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'archived_at']);
        });

        Schema::create('studio_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('studio_companies')->restrictOnDelete();
            $table->foreignId('template_id')->constrained('studio_templates')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('layout');
            $table->decimal('width_mm', 6, 2);
            $table->decimal('height_mm', 6, 2);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->unique(['template_id', 'version']);
        });

        Schema::create('studio_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('studio_companies')->restrictOnDelete();
            $table->foreignId('template_id')->constrained('studio_templates')->restrictOnDelete();
            $table->string('kind', 32);
            $table->string('path');
            $table->string('mime', 32);
            $table->timestamps();
            $table->index(['template_id', 'kind']);
        });

        Schema::create('studio_card_issues', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('studio_companies')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('studio_employees')->restrictOnDelete();
            $table->foreignId('version_id')->constrained('studio_template_versions')->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->json('display_snapshot');
            $table->string('photo_path_snapshot')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'status']);
        });

        Schema::create('studio_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('studio_companies')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 90);
            $table->string('subject_type', 90);
            $table->unsignedBigInteger('subject_id');
            $table->json('field_names')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_audits');
        Schema::dropIfExists('studio_card_issues');
        Schema::dropIfExists('studio_assets');
        Schema::dropIfExists('studio_template_versions');
        Schema::dropIfExists('studio_templates');
        Schema::dropIfExists('studio_employees');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('studio_company_id');
            $table->dropColumn(['studio_role', 'studio_active']);
        });
        Schema::dropIfExists('studio_companies');
    }
};
