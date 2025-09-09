<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Central\Tenant;
use App\Models\Company;
use App\Models\Central\MasterPermission;

class CreateTenant extends Command
{
    protected $signature = 'tenants:create
        {--name= : Tenant name}
        {--email= : Tenant email}
        {--phone= : Tenant phone}
        {--address= : Tenant address}
        {--domain= : Tenant domain}
        {--db-host= : DB host (default from env)}
        {--db-port= : DB port (default 3306)}
        {--db-user= : DB username (default from env)}
        {--db-password= : DB password (default from env)}
        {--migrate : Run tenant migrations}
        {--company-name= : Create default company after migrations}
        {--company-email= : Default company email}
        {--seed-permissions : Sync master permissions to tenant}';

    protected $description = 'Create a tenant with a multi-database setup';

    public function handle()
    {
        $name = $this->option('name');
        $email = $this->option('email');

        if (!$name || !$email) {
            $this->error('Name and email are required!');
            return 1;
        }

        $id = (string) Str::uuid();
        $dbName = 'tenant_' . str_replace('-', '', $id);

        // DB credentials
        $host = $this->option('db-host') ?: env('DB_HOST', '127.0.0.1');
        $port = $this->option('db-port') ?: env('DB_PORT', '3306');
        $username = $this->option('db-user') ?: env('DB_USERNAME');
        $password = $this->option('db-password') ?: env('DB_PASSWORD');

        $this->info("Creating tenant record...");
        
        // Create tenant with ALL data at once
        $tenant = Tenant::create([
            'id' => $id,
        ]);

        $tenant = Tenant::create([
            'id' => $id,
            'data' => [
                'name' => $name,
                'email' => $email,
                'phone' => $this->option('phone'),
                'address' => $this->option('address'),
                'status' => true,
                'tenancy_db_name' => $dbName,
                'tenancy_db_host' => $host,
                'tenancy_db_port' => $port,
                'tenancy_db_user' => $username,
                'tenancy_db_password' => $password,
            ],
        ]);
        dd($tenant);

        // Add domain if provided
        if ($domain = $this->option('domain')) {
            $tenant->domains()->create(['domain' => $domain]);
        }

        $this->info("Creating tenant database: {$dbName}");
        try {
            $pdo = new \PDO(
                "mysql:host={$host};port={$port}",
                $username,
                $password,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->info("✓ Database '{$dbName}' created successfully");

        } catch (\Exception $e) {
            $this->error("Failed to create database: " . $e->getMessage());
            return 1;
        }

        // Run migrations if requested
        if ($this->option('migrate')) {
            $this->info("Running tenant migrations...");
            $this->call('tenants:migrate', ['--tenants' => [$tenant->id]]);
            $this->info("✓ Tenant migrations completed");

            // Create default company
            // if ($companyName = $this->option('company-name')) {
            //     $companyEmail = $this->option('company-email') ?: $email;
            //     $this->createDefaultCompany($tenant, $companyName, $companyEmail);
            // }

            // Seed permissions
            if ($this->option('seed-permissions')) {
                $this->syncPermissionsToTenant($tenant);
            }
        }

        $this->info("🎉 Tenant '{$name}' created successfully!");
        $this->displayTenantInfo($tenant);

        return 0;
    }

    // private function createDefaultCompany(Tenant $tenant, string $name, string $email)
    // {
    //     $this->info("Creating default company '{$name}'...");

    //     tenancy()->initialize($tenant);

    //     try {
    //         $company = Company::create([
    //             'name' => $name,
    //             'email' => $email,
    //             'phone' => $tenant->phone,
    //             'address' => $tenant->address,
    //             'status' => true,
    //             'settings' => [
    //                 'is_default' => true,
    //                 'created_by_system' => true,
    //             ],
    //         ]);
    //         $this->info("✓ Default company created: {$company->name} (ID: {$company->id})");
    //     } catch (\Exception $e) {
    //         $this->warn("Failed to create default company: " . $e->getMessage());
    //     } finally {
    //         tenancy()->end();
    //     }
    // }

    private function syncPermissionsToTenant(Tenant $tenant)
    {
        $this->info("Syncing master permissions to tenant...");

        tenancy()->initialize($tenant);

        try {
            $masterPermissions = MasterPermission::all();
            $count = 0;

            foreach ($masterPermissions as $perm) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $perm->name, 'guard_name' => $perm->guard_name],
                    ['created_at' => now(), 'updated_at' => now()]
                );
                $count++;
            }

            $this->info("✓ Synced {$count} permissions");
        } catch (\Exception $e) {
            $this->warn("Failed to sync permissions: " . $e->getMessage());
        } finally {
            tenancy()->end();
        }
    }

    private function displayTenantInfo(Tenant $tenant)
    {
        $this->table(['Field', 'Value'], [
            ['ID', $tenant->id],
            ['Name', $tenant->name],
            ['Email', $tenant->email],
            ['Phone', $tenant->phone ?? 'Not provided'],
            ['Address', $tenant->address ?? 'Not provided'],
            ['Database', $tenant->data['tenancy_db_name'] ?? 'N/A'],
            ['Domain', $tenant->domains->first()?->domain ?? 'Not provided'],
        ]);
    }
}
