<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProductImage;
use App\Models\ReturnRequest;
use App\Services\Storage\StorageManagerService;
use Illuminate\Console\Command;

class StorageAuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:audit {--disk=s3 : The disk to audit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform non-destructive storage audit to detect broken DB references or missing S3 assets';

    /**
     * Execute the console command.
     */
    public function handle(StorageManagerService $storageManager): int
    {
        $disk = $this->option('disk') ?: 's3';
        $this->info("Auditing storage references on disk [{$disk}]...");

        $stats = [
            'payment_evidence' => ['total' => 0, 'exists' => 0, 'missing' => 0],
            'delivery_signatures' => ['total' => 0, 'exists' => 0, 'missing' => 0],
            'delivery_pod' => ['total' => 0, 'exists' => 0, 'missing' => 0],
            'product_images' => ['total' => 0, 'exists' => 0, 'missing' => 0],
            'invoice_pdfs' => ['total' => 0, 'exists' => 0, 'missing' => 0],
            'return_evidence' => ['total' => 0, 'exists' => 0, 'missing' => 0],
        ];

        // 1. Audit Payment Evidence
        $payments = Payment::whereNotNull('evidence_object_key')->get();
        foreach ($payments as $p) {
            $stats['payment_evidence']['total']++;
            if ($storageManager->exists($p->evidence_object_key, $disk)) {
                $stats['payment_evidence']['exists']++;
            } else {
                $stats['payment_evidence']['missing']++;
                $this->warn("Missing Payment Evidence: ID={$p->id}, Key={$p->evidence_object_key}");
            }
        }

        // 2. Audit Delivery Signatures & POD
        $deliveries = Delivery::whereNotNull('recipient_signature_path')->orWhereNotNull('pod_evidence_path')->get();
        foreach ($deliveries as $d) {
            if ($d->recipient_signature_path) {
                $stats['delivery_signatures']['total']++;
                if ($storageManager->exists($d->recipient_signature_path, $disk)) {
                    $stats['delivery_signatures']['exists']++;
                } else {
                    $stats['delivery_signatures']['missing']++;
                    $this->warn("Missing Delivery Signature: ID={$d->id}, Key={$d->recipient_signature_path}");
                }
            }
            if ($d->pod_evidence_path) {
                $stats['delivery_pod']['total']++;
                if ($storageManager->exists($d->pod_evidence_path, $disk)) {
                    $stats['delivery_pod']['exists']++;
                } else {
                    $stats['delivery_pod']['missing']++;
                    $this->warn("Missing Delivery POD: ID={$d->id}, Key={$d->pod_evidence_path}");
                }
            }
        }

        // 3. Audit Product Images
        $images = ProductImage::all();
        foreach ($images as $img) {
            $stats['product_images']['total']++;
            if ($storageManager->exists($img->object_key, $disk)) {
                $stats['product_images']['exists']++;
            } else {
                $stats['product_images']['missing']++;
                $this->warn("Missing Product Image: ID={$img->id}, Key={$img->object_key}");
            }
        }

        // 4. Audit Invoices
        $invoices = Invoice::whereNotNull('pdf_path')->get();
        foreach ($invoices as $inv) {
            $stats['invoice_pdfs']['total']++;
            if ($storageManager->exists($inv->pdf_path, $disk)) {
                $stats['invoice_pdfs']['exists']++;
            } else {
                $stats['invoice_pdfs']['missing']++;
            }
        }

        // 5. Audit Returns
        $returns = ReturnRequest::all();
        foreach ($returns as $r) {
            if (! empty($r->evidence_photos) && is_array($r->evidence_photos)) {
                foreach ($r->evidence_photos as $photoKey) {
                    $stats['return_evidence']['total']++;
                    if ($storageManager->exists($photoKey, $disk)) {
                        $stats['return_evidence']['exists']++;
                    } else {
                        $stats['return_evidence']['missing']++;
                        $this->warn("Missing Return Photo: Return ID={$r->id}, Key={$photoKey}");
                    }
                }
            }
        }

        $this->newLine();
        $this->table(
            ['Domain Area', 'Total Referenced', 'Verified in Storage', 'Missing'],
            collect($stats)->map(fn ($data, $key) => [
                ucwords(str_replace('_', ' ', $key)),
                $data['total'],
                $data['exists'],
                $data['missing'],
            ])->toArray()
        );

        $totalMissing = collect($stats)->sum('missing');
        if ($totalMissing > 0) {
            $this->warn("Storage audit found {$totalMissing} missing references.");
        } else {
            $this->info("Storage audit completed. All DB references match existing storage objects.");
        }

        return 0;
    }
}
