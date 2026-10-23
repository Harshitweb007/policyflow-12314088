<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('agent_id', 64);
            $table->string('client_request_id', 64);
            $table->string('customer_name', 255);
            $table->date('date_of_birth');
            $table->string('destination', 32);
            $table->date('trip_start');
            $table->date('trip_end');
            $table->string('plan_code', 32);
            $table->unsignedInteger('sum_insured');
            $table->decimal('base_daily_rate', 8, 2);
            $table->unsignedSmallInteger('travel_days');
            $table->unsignedSmallInteger('customer_age');
            $table->string('age_band', 32);
            $table->decimal('age_multiplier', 8, 2);
            $table->decimal('destination_multiplier', 8, 2);
            $table->decimal('coverage_multiplier', 8, 2);
            $table->decimal('premium_amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('state', 32);
            $table->string('provider_reference', 64)->nullable();
            $table->string('policy_number', 64)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('last_error_code', 64)->nullable();
            $table->text('last_error_message')->nullable();
            $table->string('last_provider_summary', 255)->nullable();
            $table->string('mock_scenario', 64)->default('SUCCESS');
            $table->string('issuance_idempotency_key', 80)->unique();
            $table->timestamps();

            $table->unique(['agent_id', 'client_request_id'], 'policy_requests_agent_client_unique');
            $table->unique('policy_number');
            $table->unique('provider_reference');
            $table->index('state');
        });

        Schema::create('issuance_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')->constrained('policy_requests')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('idempotency_key', 80);
            $table->string('status', 32);
            $table->string('error_classification', 32)->default('NONE');
            $table->json('request_summary')->nullable();
            $table->json('response_summary')->nullable();
            $table->string('provider_reference', 64)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['policy_request_id', 'attempt_number'], 'issuance_attempts_request_number_unique');
            $table->index('idempotency_key');
            $table->index('status');
        });

        Schema::create('provider_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_id', 80)->unique();
            $table->string('provider_reference', 64)->nullable();
            $table->foreignId('policy_request_id')->nullable()->constrained('policy_requests')->nullOnDelete();
            $table->string('event_type', 32);
            $table->json('payload');
            $table->string('processing_outcome', 32);
            $table->timestamp('event_occurred_at')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('provider_reference');
        });

        Schema::create('policy_state_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')->constrained('policy_requests')->cascadeOnDelete();
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32);
            $table->string('reason', 255);
            $table->foreignId('issuance_attempt_id')->nullable()->constrained('issuance_attempts')->nullOnDelete();
            $table->foreignId('provider_event_id')->nullable()->constrained('provider_events')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('policy_request_id');
        });

        Schema::create('mock_insurer_policies', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 80)->unique();
            $table->string('provider_reference', 64)->unique();
            $table->string('policy_number', 64)->nullable()->unique();
            $table->string('policy_request_uuid', 36);
            $table->string('status', 32);
            $table->string('scenario', 64);
            $table->unsignedInteger('issue_call_count')->default(0);
            $table->json('request_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_state_history');
        Schema::dropIfExists('provider_events');
        Schema::dropIfExists('issuance_attempts');
        Schema::dropIfExists('mock_insurer_policies');
        Schema::dropIfExists('policy_requests');
    }
};
