<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignUserRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:assign-role 
                            {--user= : User ID or email}
                            {--role= : Role name to assign}
                            {--all : Assign role to all users without a role}
                            {--default : Assign default role (employee) to all users without a role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign roles to users. Use --user and --role for specific user, --all with --role for all users, or --default to assign employee role to users without roles.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('User Role Assignment Tool');
        $this->newLine();

        // Get all available roles
        $availableRoles = Role::pluck('name')->toArray();

        if (empty($availableRoles)) {
            $this->error('No roles found. Please run the RolesAndPermissionsSeeder first.');

            return Command::FAILURE;
        }

        $this->info('Available roles: '.implode(', ', $availableRoles));
        $this->newLine();

        // Option 1: Assign role to specific user
        if ($this->option('user') && $this->option('role')) {
            return $this->assignRoleToUser($this->option('user'), $this->option('role'));
        }

        // Option 2: Assign role to all users
        if ($this->option('all') && $this->option('role')) {
            return $this->assignRoleToAll($this->option('role'));
        }

        // Option 3: Assign default role to users without roles
        if ($this->option('default')) {
            return $this->assignDefaultRole();
        }

        // Interactive mode
        return $this->interactiveMode();
    }

    /**
     * Assign role to a specific user
     */
    private function assignRoleToUser($userIdentifier, $roleName)
    {
        // Find user by ID or email
        $user = is_numeric($userIdentifier)
            ? User::find($userIdentifier)
            : User::where('email', $userIdentifier)->first();

        if (! $user) {
            $this->error("User not found: {$userIdentifier}");

            return Command::FAILURE;
        }

        $role = Role::where('name', $roleName)->first();
        if (! $role) {
            $this->error("Role not found: {$roleName}");

            return Command::FAILURE;
        }

        $user->syncRoles([$role]);
        $this->info("✓ Assigned role '{$roleName}' to user: {$user->name} ({$user->email})");

        return Command::SUCCESS;
    }

    /**
     * Assign role to all users
     */
    private function assignRoleToAll($roleName)
    {
        $role = Role::where('name', $roleName)->first();
        if (! $role) {
            $this->error("Role not found: {$roleName}");

            return Command::FAILURE;
        }

        if (! $this->confirm("This will assign role '{$roleName}' to ALL users. Continue?")) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        $users = User::all();
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            $user->syncRoles([$role]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✓ Assigned role '{$roleName}' to {$users->count()} users.");

        return Command::SUCCESS;
    }

    /**
     * Assign default role (employee) to users without roles
     */
    private function assignDefaultRole()
    {
        $defaultRole = Role::firstOrCreate(['name' => 'employee']);

        $usersWithoutRoles = User::doesntHave('roles')->get();

        if ($usersWithoutRoles->isEmpty()) {
            $this->info('All users already have roles assigned.');

            return Command::SUCCESS;
        }

        $this->info("Found {$usersWithoutRoles->count()} users without roles.");

        if (! $this->confirm("Assign 'employee' role to these users?")) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($usersWithoutRoles->count());
        $bar->start();

        foreach ($usersWithoutRoles as $user) {
            $user->assignRole($defaultRole);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✓ Assigned 'employee' role to {$usersWithoutRoles->count()} users.");

        return Command::SUCCESS;
    }

    /**
     * Interactive mode for role assignment
     */
    private function interactiveMode()
    {
        $this->info('Interactive Mode');
        $this->newLine();

        // Select user
        $userIdentifier = $this->ask('Enter user ID or email (or type "all" for all users)');

        if ($userIdentifier === 'all') {
            $roleName = $this->choice('Select role to assign', Role::pluck('name')->toArray());

            return $this->assignRoleToAll($roleName);
        }

        // Find user
        $user = is_numeric($userIdentifier)
            ? User::find($userIdentifier)
            : User::where('email', $userIdentifier)->first();

        if (! $user) {
            $this->error("User not found: {$userIdentifier}");

            return Command::FAILURE;
        }

        $this->info("User found: {$user->name} ({$user->email})");
        $this->info('Current roles: '.($user->roles->pluck('name')->implode(', ') ?: 'None'));

        // Select role
        $roleName = $this->choice('Select role to assign', Role::pluck('name')->toArray());

        $user->syncRoles([$roleName]);
        $this->info("✓ Assigned role '{$roleName}' to user: {$user->name}");

        return Command::SUCCESS;
    }
}
