<?php

namespace App\Console\Commands;

use App\Models\DebtDocument;
use App\Models\ShopDocument;
use Illuminate\Console\Command;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ScanShopDocuments extends Command
{
    protected $signature = 'shops:scan-documents {--cases : Scan private debt application evidence}';

    protected $description = 'Scan quarantined shop documents with ClamAV';

    public function handle(): int
    {
        $binary = config('filesystems.shop_clamscan_binary');
        if (! is_string($binary) || ! is_file($binary)) {
            $this->error('Configure SHOP_CLAMSCAN_BINARY with the absolute path to clamscan.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $failed = false;
        $model = $this->option('cases') ? DebtDocument::class : ShopDocument::class;
        foreach ($model::where('status', 'quarantined')->orderBy('updated_at')->orderBy('id')->limit(100)->get() as $document) {
            $path = $disk->path($document->storage_key);
            $status = $this->scan($binary, $path, $document->sha256);
            if ($status === 'quarantined') {
                $failed = true;
                $this->error("Incomplete scan for document {$document->id}.");
            }
            $model::whereKey($document->id)->where('status', 'quarantined')->update(['status' => $status]);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function scan(string $binary, string $path, string $expectedDigest): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return 'quarantined';
        }
        $digest = hash_file('sha256', $path);
        if ($digest === false) {
            return 'quarantined';
        }
        if ($digest !== $expectedDigest) {
            return 'rejected';
        }

        try {
            $result = Process::timeout(90)->run([$binary, '--no-summary', '--alert-encrypted=yes', '--alert-exceeds-max=yes', $path]);
        } catch (ProcessTimedOutException) {
            return 'quarantined';
        }

        return match ($result->exitCode()) {
            0 => 'available',
            1 => 'rejected',
            default => 'quarantined',
        };
    }
}
