<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Khách hàng (doanh nghiệp)
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();                 
            $table->string('name');                               
            $table->string('short_name', 100)->nullable();        
            $table->string('name_key')->nullable()->index();      
            $table->string('tax_code', 20)->nullable()->unique(); 
            $table->string('company_type', 20)->nullable();       
            $table->string('parent_group')->nullable();           
            $table->string('parent_country', 60)->nullable();     
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('province', 60)->nullable();
            $table->string('industrial_zone', 120)->nullable();   
            $table->string('industry', 30)->nullable();
            $table->string('industry_detail')->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            $table->decimal('ppe_budget_estimate', 15, 0)->nullable(); 
            $table->text('current_suppliers')->nullable();             
            
            $table->string('source_channel', 30)->nullable();
            $table->string('referred_by')->nullable();
            
            $table->string('tier', 1)->nullable();                    
            $table->string('lifecycle_stage', 30)->default('prospect');
            $table->timestamp('lifecycle_stage_at')->nullable();
            $table->string('status', 20)->default('active');
            
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('procurement_method', 20)->nullable();      
            $table->string('payment_terms', 100)->nullable();
            $table->decimal('credit_limit', 15, 0)->nullable();
            $table->boolean('vendor_registered')->default(false);      
            $table->date('vendor_registered_at')->nullable();
            $table->json('required_certificates')->nullable();        
            
            $table->boolean('marketing_consent')->default(false);
            $table->json('consent_channels')->nullable();
            $table->timestamp('consent_at')->nullable();
            
            $table->unsignedTinyInteger('data_completeness')->default(0);
            $table->timestamp('last_contacted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index(['owner_id', 'status']);
            $table->index('lifecycle_stage');
            $table->index('tier');
            $table->index('industry');
        });

        Schema::create('plants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('province', 60)->nullable();
            $table->string('industrial_zone', 120)->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            $table->json('hazard_areas')->nullable();     
            $table->string('receiving_hours', 100)->nullable();
            $table->text('delivery_notes')->nullable();   
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('title', 120)->nullable();
            $table->string('department', 120)->nullable();
            $table->string('buying_role', 20)->default('other');   
            $table->string('influence', 20)->nullable();           
            $table->string('attitude', 20)->nullable();           
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('zalo', 30)->nullable();
            $table->string('preferred_channel', 20)->nullable();
            $table->string('language', 10)->nullable();           
            $table->date('birthday')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->date('left_at')->nullable();                  
            $table->boolean('marketing_opt_out')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'left_at']);
            $table->index('phone');
            $table->index('email');
        });

        
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('color', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('customer_tag', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_id', 'tag_id']);
        });

        Schema::create('customer_owner_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 30);                 
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('customer_owner_histories');
        Schema::dropIfExists('customer_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('plants');
        Schema::dropIfExists('customers');
    }
};