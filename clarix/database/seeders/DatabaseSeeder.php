<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskNote;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Units
        $unitA = Unit::create(['name' => 'Content Unit A']);
        $unitB = Unit::create(['name' => 'Research Unit B']);
        $unitC = Unit::create(['name' => 'Editorial Unit C']);

        // Admin
        $admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@clarix.test',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        // Project Managers
        $pm1 = User::create([
            'name'     => 'PM Alpha',
            'email'    => 'pm@clarix.test',
            'password' => Hash::make('password'),
            'role'     => 'pm',
            'unit_id'  => $unitA->id,
        ]);

        $pm2 = User::create([
            'name'     => 'PM Beta',
            'email'    => 'pm2@clarix.test',
            'password' => Hash::make('password'),
            'role'     => 'pm',
            'unit_id'  => $unitB->id,
        ]);

        // Writers
        $writer1 = User::create([
            'name'     => 'Writer One',
            'email'    => 'writer@clarix.test',
            'password' => Hash::make('password'),
            'role'     => 'writer',
        ]);

        $writer2 = User::create([
            'name'     => 'Writer Two',
            'email'    => 'writer2@clarix.test',
            'password' => Hash::make('password'),
            'role'     => 'writer',
        ]);

        $writer3 = User::create([
            'name'     => 'Writer Three',
            'email'    => 'writer3@clarix.test',
            'password' => Hash::make('password'),
            'role'     => 'writer',
        ]);

        // Tasks
        $task1 = Task::create([
            'title'       => 'Write Homepage Copy',
            'task_code'   => 'CA_001',
            'description' => 'Create engaging homepage copy for the Q3 campaign.',
            'unit_id'     => $unitA->id,
            'created_by'  => $pm1->id,
            'priority'    => 'high',
            'status'      => 'pending',
            'deadline'    => now()->addDays(14),
        ]);

        $task2 = Task::create([
            'title'       => 'Social Media Posts Pack',
            'task_code'   => 'CA_002',
            'description' => 'Prepare 20 social media posts for Instagram and LinkedIn.',
            'unit_id'     => $unitA->id,
            'created_by'  => $pm1->id,
            'priority'    => 'medium',
            'status'      => 'pending',
            'deadline'    => now()->addDays(7),
        ]);

        $task3 = Task::create([
            'title'       => 'Market Research Report',
            'task_code'   => 'RB_001',
            'description' => 'Compile competitive analysis for product launch.',
            'unit_id'     => $unitB->id,
            'created_by'  => $pm2->id,
            'priority'    => 'high',
            'status'      => 'in_progress',
            'deadline'    => now()->addDays(10),
        ]);

        // Assignments
        TaskAssignment::create([
            'task_id'     => $task1->id,
            'writer_id'   => $writer1->id,
            'assigned_by' => $admin->id,
            'status'      => 'pending',
        ]);

        TaskAssignment::create([
            'task_id'     => $task2->id,
            'writer_id'   => $writer2->id,
            'assigned_by' => $admin->id,
            'status'      => 'in_progress',
        ]);

        TaskAssignment::create([
            'task_id'     => $task3->id,
            'writer_id'   => $writer3->id,
            'assigned_by' => $admin->id,
            'status'      => 'in_progress',
        ]);

        // Notes
        TaskNote::create([
            'task_id'    => $task1->id,
            'note'       => 'Please ensure the tone matches our brand guidelines.',
            'created_by' => $pm1->id,
        ]);

        TaskNote::create([
            'task_id'    => $task3->id,
            'note'       => 'Focus on competitors in the SaaS space.',
            'created_by' => $pm2->id,
        ]);

        $this->call(PermissionSeeder::class);
    }
}
