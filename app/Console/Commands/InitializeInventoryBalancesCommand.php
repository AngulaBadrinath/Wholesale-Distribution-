<?php

namespace App\Console\Commands;

use App\Models\Warehouse;
use App\Services\Inventory\InventoryInitializationService;
use Illuminate\Console\Command;

class InitializeInventoryBalancesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:initialize
                            {--warehouse= : Specific warehouse code to initialize (defaults to canonical default warehouse)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Idempotently initialize baseline physical inventory records for catalog products';

    /**
     * Execute the console command.
     */
    public function handle(InventoryInitializationService $initializationService): int
    {
        $this->info('Starting physical inventory balance initialization...');

        $warehouseCode = $this->option('warehouse');
        $warehouse = null;

        if ($warehouseCode) {
            $warehouse = Warehouse::where('code', strtoupper(trim((string) $warehouseCode)))->first();
            if (! $warehouse) {
                $this->error("Warehouse with code '{$warehouseCode}' not found.");

                return self::FAILURE;
            }
        } else {
            $warehouse = $initializationService->getDefaultWarehouse();
        }

        $this->line("Target Warehouse: <comment>{$warehouse->name} ({$warehouse->code})</comment>");

        $stats = $initializationService->initializeCatalog($warehouse);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Target Warehouse Code', $stats['warehouse_code']],
                ['Total Catalog Products', $stats['total_products']],
                ['New Balances Initialized', $stats['initialized']],
                ['Existing Balances Preserved', $stats['already_existed']],
            ]
        );

        $this->info('Physical inventory initialization completed successfully.');

        return self::SUCCESS;
    }
}
