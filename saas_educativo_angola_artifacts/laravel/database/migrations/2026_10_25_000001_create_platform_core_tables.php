<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration inicial das tabelas nucleares.
 *
 * Nota: esta migration é um ponto de partida. O esquema SQL de referência
 * inclui mais tabelas e restrições PostgreSQL compostas; manter uma única
 * fonte de verdade para as migrations do projecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('slug', 120)->unique();
            $table->string('institution_type', 30);
            $table->string('approval_status', 30)->default('pending')->index();
            $table->string('operational_status', 20)->default('inactive')->index();
            $table->string('email', 254)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('tax_identifier', 80)->nullable();
            $table->text('address')->nullable();
            $table->string('municipality', 120)->nullable();
            $table->string('province', 120)->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('institution_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('institution_type', 30);
            $table->string('proposed_name', 180);
            $table->string('applicant_name', 180);
            $table->string('applicant_email', 254);
            $table->string('applicant_phone', 40)->nullable();
            $table->string('tax_identifier', 80)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('municipality', 120)->nullable();
            $table->jsonb('submitted_documents')->default('[]');
            $table->string('status', 30)->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('submitted_at')->useCurrent();
            $table->timestampsTz();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 60)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 19, 2);
            $table->char('currency', 3)->default('AOA');
            $table->string('billing_interval', 20);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('next_billing_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();
            $table->index(['institution_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('institution_applications');
        Schema::dropIfExists('institutions');
    }
};
