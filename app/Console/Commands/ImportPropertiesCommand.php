<?php

namespace App\Console\Commands;

use App\Services\ImoviewService;
use App\Services\PropertyImportReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ImportPropertiesCommand extends Command
{
    protected $signature = 'properties:import {--json : Retorna o relatório da execução em JSON}';

    protected $description = 'Sincroniza os imóveis do Imoview e remove os indisponíveis após uma consulta completa';

    public function handle(ImoviewService $imoview): int
    {
        $report = new PropertyImportReport();
        $lock = null;
        $acquired = false;

        try {
            // The panel, scheduler and CLI must all share the same lock.
            $lock = Cache::lock('properties:import', 86400);
            $acquired = $lock->get();

            if (! $acquired) {
                $result = $report->finish('conflict', 'Já existe uma importação em andamento. Aguarde a conclusão antes de tentar novamente.');
                $exitCode = 2;
            } else {
                set_time_limit(0);
                $report->log('Iniciando sincronização dos imóveis do Imoview.');
                $imoview->import($report);
                $result = $report->finish('completed', 'Importação concluída com sucesso.');
                $exitCode = Command::SUCCESS;
            }
        } catch (Throwable $exception) {
            $report->summary['errors']++;
            // Technical details stay on the server, outside the panel response.
            $report->logger()->error('Falha técnica na importação.', ['exception' => $exception]);
            $result = $report->finish('failed', 'A importação falhou. Os registros já sincronizados foram mantidos; a remoção de imóveis indisponíveis não foi concluída.');
            $exitCode = Command::FAILURE;
        } finally {
            if ($acquired) {
                $lock->release();
            }
        }

        if ($this->option('json')) {
            $this->output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            foreach ($result['logs'] as $entry) {
                $this->line("[{$entry['timestamp']}] {$entry['level']}: {$entry['message']}");
            }
        }

        return $exitCode;
    }
}
