<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class DemoDataResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:reset
                            {--force : Force reset without interactive confirmation}
                            {--with-images : Re-generate demo product images and evidence}
                            {--disk=s3 : Target storage disk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely reset and re-seed the pre-production testing environment';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // 1. Mandatory Environment Safety Guard
        if (App::environment('production')) {
            $this->error('CRITICAL SAFETY VIOLATION: demo:reset is strictly prohibited in production environments.');

            return 1;
        }

        if (! $this->option('force') && ! $this->confirm('This will wipe the current test database and re-seed all demo records. Proceed?')) {
            $this->info('Reset operation aborted.');

            return 0;
        }

        $this->info('Resetting environment...');
        $exitCode = $this->call('demo:seed', [
            '--fresh' => true,
            '--with-images' => (bool) $this->option('with-images'),
            '--disk' => $this->option('disk') ?: 's3',
        ]);

        if ($exitCode === 0) {
            $this->info('Demo environment reset successfully completed.');
        }

        return $exitCode;
    }
}
