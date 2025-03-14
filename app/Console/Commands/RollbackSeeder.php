<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Seeders\UserSeeder;

class RollbackSeeder extends Command
{
    protected $signature = 'seeder:rollback {seeder}';
    protected $description = 'Rollback a specific seeder';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $seederName = $this->argument('seeder');
        $seederClass = "Database\\Seeders\\{$seederName}";

        if (class_exists($seederClass)) {
            $seeder = new $seederClass();
            $seeder->rollback();
            $this->info("Rollback for {$seederName} completed.");
        } else {
            $this->error("Seeder class {$seederName} not found.");
        }
    }
}
