<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // Null staff_id = the whole business is closed that day (e.g. a
            // public holiday). A specific staff_id blocks only that one
            // staff member (e.g. a day off) while the rest of the business
            // stays open.
            $table->foreignId('staff_id')->nullable()->constrained('staff')->cascadeOnDelete();
            $table->date('date');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'date']);
            $table->index(['staff_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_closures');
    }
};
