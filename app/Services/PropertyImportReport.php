<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class PropertyImportReport
{
    public string $id;
    public array $summary = [
        'received' => 0,
        'synced' => 0,
        'created' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'removed' => 0,
        'pages' => 0,
        'errors' => 0,
    ];

    private array $logs = [];
    private float $startedAt;
    private string $startedAtIso;
    private ?LoggerInterface $logger = null;

    public function __construct()
    {
        $this->id = (string) Str::uuid();
        $this->startedAt = microtime(true);
        $this->startedAtIso = now()->toIso8601String();
    }

    public function log(string $message, string $level = 'info'): void
    {
        $this->logs[] = [
            'timestamp' => now()->toIso8601String(),
            'level' => $level,
            'message' => $message,
        ];

        $this->logger()->log($level, $message, ['import_id' => $this->id]);
    }

    public function logger(): LoggerInterface
    {
        return $this->logger ??= Log::build([
            'driver' => 'single',
            'path' => storage_path("logs/property-imports/{$this->id}.log"),
            'level' => 'debug',
        ]);
    }

    public function finish(string $status, string $message): array
    {
        $this->log($message, $status === 'completed' ? 'info' : 'warning');
        $s = $this->summary;
        $this->log("Resumo: {$s['received']} recebidos; {$s['synced']} sincronizados "
            . "({$s['created']} criados, {$s['updated']} atualizados, {$s['unchanged']} sem alterações); "
            . "{$s['removed']} removidos; {$s['pages']} páginas; {$s['errors']} erros.");

        return [
            'success' => $status === 'completed',
            'status' => $status,
            'message' => $message,
            'import_id' => $this->id,
            'source' => 'imoview',
            'started_at' => $this->startedAtIso,
            'finished_at' => now()->toIso8601String(),
            'duration_seconds' => round(microtime(true) - $this->startedAt, 2),
            'summary' => $this->summary,
            'logs' => $this->logs,
        ];
    }
}
