<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('DRAFT')->index();
            $table->string('signing_order_mode', 16)->default('PARALLEL');
            $table->boolean('send_via_email')->default(true);
            $table->string('original_filename');
            $table->string('original_pdf_path');
            $table->string('completed_pdf_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->char('original_hash_sha256', 64);
            $table->char('signed_hash_sha256', 64)->nullable();
            $table->char('completed_hash_sha256', 64)->nullable();
            $table->unsignedInteger('file_size');
            $table->unsignedInteger('total_pages');
            $table->unsignedSmallInteger('expiry_days')->default(14);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('processing_failed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();
        });

        Schema::create('document_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->decimal('width_pt', 10, 2);
            $table->decimal('height_pt', 10, 2);
            $table->unsignedSmallInteger('rotation_degrees')->default(0);
            $table->string('thumbnail_path')->nullable();

            $table->unique(['document_id', 'page_number']);
        });

        Schema::create('signers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->char('access_token_hash', 64)->nullable()->unique();
            $table->text('access_token_encrypted')->nullable();
            $table->string('passcode_hash')->nullable();
            $table->unsignedSmallInteger('passcode_attempts')->default(0);
            $table->string('color_tag', 7);
            $table->unsignedSmallInteger('signing_order')->default(1);
            $table->boolean('is_owner')->default(false);
            $table->string('status', 16)->default('PENDING');
            $table->text('decline_reason')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip_address', 45)->nullable();
            $table->text('signed_user_agent')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'signing_order']);
        });

        Schema::create('document_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('signer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->decimal('x_ratio', 6, 4);
            $table->decimal('y_ratio', 6, 4);
            $table->decimal('width_ratio', 6, 4);
            $table->decimal('height_ratio', 6, 4);
            $table->string('field_type', 16);
            $table->string('label', 100)->nullable();
            $table->boolean('is_required')->default(true);
            $table->text('field_value')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'page_number']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('signer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 32);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('document_fields');
        Schema::dropIfExists('signers');
        Schema::dropIfExists('document_pages');
        Schema::dropIfExists('documents');
    }
};
