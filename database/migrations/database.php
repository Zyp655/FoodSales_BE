<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email', 100)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('user'); 
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('category', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique()->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 100)->unique();
            $table->string('password'); 
            $table->string('image')->nullable();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        
        Schema::create('product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->onDelete('cascade'); 
            $table->foreignId('category_id')->nullable()->constrained('category')->onDelete('set null'); 
            $table->string('name', 200);
            $table->string('image')->nullable();
            $table->decimal('price_per_kg', 8, 2);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('interaction_count')->default(0);
            $table->timestamps();
        });

        Schema::create('cart', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('product')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->timestamps();
            $table->primary(['user_id', 'product_id']);
        });

        Schema::create('order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('sellers')->onDelete('cascade');
            $table->decimal('total_amount', 10, 2);
            $table->string('status', 50)->default('Pending');
            $table->text('delivery_address');
            $table->timestamps();
        });

        Schema::create('order_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('order')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('product')->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('price_at_purchase', 8, 2); 
            $table->timestamps();
        });

        Schema::create('transaction', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('order')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 50);
            $table->string('status', 50);
            $table->text('qr_data')->nullable(); 
            $table->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
        $table->id();
        $table->morphs('tokenable'); 
        $table->string('name')->nullable();
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
});
    }

    
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('transaction');
        Schema::dropIfExists('order_item');
        Schema::dropIfExists('order');
        Schema::dropIfExists('cart');
        Schema::dropIfExists('product');
        Schema::dropIfExists('sellers');
        Schema::dropIfExists('category');
        Schema::dropIfExists('users'); 
    }
};