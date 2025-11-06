<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{ImobziService, ImoviewService};
use App\Models\Property;

class ImobziSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imobzi:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(ImobziService $imobzi)
    {
        $this->info('Iniciando sincronização dos imóveis Imobzi...');

        $properties = Property::where('crm_origin', 'imobzi')->get();
        foreach($properties as $p){
            $imobzi->property_detail($p->external_id);
        }

        $this->info('✅ Imóveis do Imobzi sincronizados.');


        $this->info('🎯 Sincronização concluída.');
        return Command::SUCCESS;
    }
}
