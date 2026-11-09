<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\Organization;
use App\Models\User;
use App\Services\PeopleOperations;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(fn () => $this->seedWorkspace());
    }

    private function seedWorkspace(): void
    {
        $org = Organization::create(['name' => 'Northstar Studio']);
        $other = Organization::create(['name' => 'Seabrook Collective']);
        $admin = User::create(['organization_id' => $org->id, 'name' => 'Alex River', 'email' => 'admin@crewbase.test', 'password' => 'DemoPass!2026', 'role' => 'admin']);
        User::create(['organization_id' => $org->id, 'name' => 'Jamie Ash', 'email' => 'viewer@crewbase.test', 'password' => 'DemoPass!2026', 'role' => 'viewer']);
        $foreign = User::create(['organization_id' => $other->id, 'name' => 'Casey Stone', 'email' => 'other@crewbase.test', 'password' => 'DemoPass!2026', 'role' => 'admin']);
        $ops = app(PeopleOperations::class);
        $mira = $ops->create($admin, ['name' => 'Mira Solis', 'title' => 'Product designer', 'team' => 'Design', 'location' => 'Jakarta · Hybrid']);
        $ops->transition($admin, $mira->id, 1, 'onboarding');
        $theo = $ops->create($admin, ['name' => 'Theo Park', 'title' => 'Platform engineer', 'team' => 'Engineering', 'location' => 'Singapore · Remote']);
        $theo = $ops->transition($admin, $theo->id, 1, 'onboarding');
        foreach ($theo->tasks()->where('phase', 'onboarding')->get() as $task) {
            $theo = $ops->completeTask($admin, $theo->id, $theo->version, $task->id);
        }
        $theo = $ops->transition($admin, $theo->id, $theo->version, 'active');
        $ops->create($admin, ['name' => 'Hana Vale', 'title' => 'Customer success lead', 'team' => 'Experience', 'location' => 'Bandung · Remote']);
        $ops->create($foreign, ['name' => 'Rowan Test', 'title' => 'Private org fixture', 'team' => 'Research', 'location' => 'Demo only']);
        Equipment::create(['organization_id' => $org->id, 'code' => 'CB-101', 'name' => 'Fieldbook 14', 'category' => 'Laptop']);
        $monitor = Equipment::create(['organization_id' => $org->id, 'code' => 'CB-102', 'name' => 'Studio Display 27', 'category' => 'Monitor']);
        Equipment::create(['organization_id' => $org->id, 'code' => 'CB-103', 'name' => 'Access Key', 'category' => 'Security key']);
        Equipment::create(['organization_id' => $other->id, 'code' => 'CB-X01', 'name' => 'Private org equipment', 'category' => 'Laptop']);
        $ops->assign($admin, $theo->id, $theo->version, $monitor->id);
    }
}
