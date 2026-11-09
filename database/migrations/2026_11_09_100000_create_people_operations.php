<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('viewer');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('name');
            $table->string('title');
            $table->string('team');
            $table->string('location');
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
        Schema::create('worker_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained();
            $table->string('phase');
            $table->string('name');
            $table->boolean('required')->default(true);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->unique(['worker_id', 'phase', 'name']);
        });
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category');
            $table->foreignId('worker_id')->nullable()->constrained();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });
        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('worker_id')->constrained();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action');
            $table->text('detail');
            $table->timestamp('created_at');
            $table->index(['organization_id', 'created_at']);
        });
        DB::unprepared("CREATE TRIGGER events_no_update BEFORE UPDATE ON activity_events BEGIN SELECT RAISE(ABORT, 'Activity events are append-only'); END");
        DB::unprepared("CREATE TRIGGER events_no_delete BEFORE DELETE ON activity_events BEGIN SELECT RAISE(ABORT, 'Activity events are append-only'); END");
        DB::unprepared("CREATE TRIGGER equipment_same_org_insert BEFORE INSERT ON equipment WHEN NEW.worker_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM workers WHERE workers.id = NEW.worker_id AND workers.organization_id = NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'Equipment organization mismatch'); END");
        DB::unprepared("CREATE TRIGGER equipment_same_org_update BEFORE UPDATE ON equipment WHEN NEW.worker_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM workers WHERE workers.id = NEW.worker_id AND workers.organization_id = NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'Equipment organization mismatch'); END");
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('worker_tasks');
        Schema::dropIfExists('workers');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('organizations');
    }
};
