<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{ImobziService, ImoviewService};

class ImportPropertiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'properties:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(ImobziService $imobzi, ImoviewService $imoview)
    {
        $this->info('Iniciando importação dos imóveis...');

        $imobzi->import();
        $this->info('✅ Imóveis do Imobzi importados.');

        $imoview->import();
        $this->info('✅ Imóveis do Imoview importados.');

        $this->info('🎯 Importação concluída.');
        return Command::SUCCESS;
    }
}
