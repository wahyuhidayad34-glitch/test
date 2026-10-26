<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengganti purchasing_tables.sql (file .sql TIDAK dijalankan oleh `php artisan migrate`
 * dan memakai sintaks khusus MariaDB). Migration ini jalan di MySQL, MariaDB, maupun SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('payment_terms', 50)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();          // FIX: model Vendor memakai SoftDeletes
            $table->index('name');
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('pr_number', 50)->unique();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->string('department', 100)->nullable();
            $table->date('pr_date')->nullable()->index();
            $table->date('required_date')->nullable();
            $table->enum('status', [
                'draft', 'submitted', 'approved_manager', 'approved_finance',
                'approved', 'rejected', 'converted_to_po',
            ])->default('draft')->index();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pr_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('unit', 50);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 15, 2);
            $table->text('specification')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 50)->unique();
            $table->foreignId('pr_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->date('po_date')->nullable()->index();
            $table->date('delivery_date')->nullable();
            $table->enum('status', ['draft', 'sent', 'confirmed', 'partial_received', 'received', 'closed', 'cancelled'])
                  ->default('draft')->index();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('payment_terms', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('po_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('unit', 50);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 15, 2);
            $table->unsignedInteger('received_qty')->default(0);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('gr_number', 50)->unique();
            $table->foreignId('po_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->date('receipt_date')->nullable();
            $table->foreignId('received_by_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['draft', 'received', 'partial', 'rejected'])->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('gr_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gr_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('po_item_id')->constrained('po_items')->restrictOnDelete();
            $table->unsignedInteger('quantity_received')->default(0);
            $table->unsignedInteger('quantity_accepted')->default(0);
            $table->unsignedInteger('quantity_rejected')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('po_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->enum('status', ['draft', 'received', 'verified', 'approved', 'paid', 'rejected'])
                  ->default('draft')->index();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('po_item_id')->constrained('po_items')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 15, 2);
            $table->timestamps();
        });

        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->enum('document_type', ['pr', 'po', 'invoice']);
            $table->unsignedBigInteger('document_id');
            $table->unsignedInteger('approval_level');
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('comments')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
            // satu baris per level per dokumen
            $table->unique(['document_type', 'document_id', 'approval_level'], 'approval_doc_level_unique');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('action', 100)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent()->index();

            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs', 'approval_workflows', 'invoice_items', 'invoices', 'gr_items',
            'goods_receipts', 'po_items', 'purchase_orders', 'purchase_request_items',
            'purchase_requests', 'vendors',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
