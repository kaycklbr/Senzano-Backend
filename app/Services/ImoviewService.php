<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Property;
use Illuminate\Support\Str;

class ImoviewService
{
    protected string $baseUrl = 'https://api.imoview.com.br';

    public function import(PropertyImportReport $report): void
    {
        $page = 1;
        $total = 0;
        $apiExternalIds = [];
        $expectedTotal = null;

        do {
            $report->log("Consultando página {$page} do Imoview (até 20 imóveis).");
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'chave' => env('IMOVIEW_KEY'),
            ])->connectTimeout(10)->timeout(60)->post($this->baseUrl.'/Imovel/RetornarImoveisDisponiveis', [
                'numeroPagina' => $page,
                'numeroRegistros' => 20,
            ]);

            if ($response->failed()) {
                $report->log("Falha ao consultar a página {$page}: HTTP {$response->status()}. Remoção cancelada.", 'error');
                throw new \RuntimeException('Falha na consulta ao Imoview.');
            }

            $data = $response->json();
            if (! is_array($data) || ! isset($data['lista'], $data['quantidade'])
                || ! is_array($data['lista']) || ! array_is_list($data['lista'])
                || ! preg_match('/^\d+$/', (string) $data['quantidade'])) {
                $report->log("Resposta inválida na página {$page}. Remoção cancelada.", 'error');
                throw new \RuntimeException('Resposta inválida do Imoview.');
            }

            $properties = $data['lista'];
            $total = (int) $data['quantidade'];
            $expectedTotal ??= $total;
            $report->summary['pages']++;
            $report->summary['received'] += count($properties);

            if ($total !== $expectedTotal || (empty($properties) && count($apiExternalIds) < $total)) {
                $report->log("Listagem incompleta ou alterada durante a consulta na página {$page}. Remoção cancelada; execute novamente.", 'error');
                throw new \RuntimeException('Paginação inconsistente do Imoview.');
            }
            $report->log("Página {$page}: " . count($properties) . " imóveis recebidos; total informado: {$total}.");

            foreach ($properties as $item) {
                if (! is_array($item) || ! isset($item['codigo']) || ! is_scalar($item['codigo'])
                    || trim((string) $item['codigo']) === '' || isset($apiExternalIds[(string) $item['codigo']])) {
                    $report->log("Imóvel sem código válido ou duplicado na página {$page}. Remoção cancelada.", 'error');
                    throw new \RuntimeException('Código de imóvel inválido ou duplicado.');
                }
                $apiExternalIds[(string) $item['codigo']] = $item['codigo'];

                $area_total = $this->parseValue($item['arealote'] ?? null) ?: $this->parseValue($item['areaexterna'] ?? null);
                $area_useful = $this->parseValue($item['areaprincipal'] ?? null) ?: $this->parseValue($item['areainterna'] ?? null);

                $property = Property::updateOrCreate(
                    [
                        'external_id' => $item['codigo'],
                        'crm_origin' => 'imoview',
                    ],
                    [
                        'title' => $item['titulo'] ?? null,
                        'description' => $item['descricao'] ?? null,
                        'sale_value' => $this->parseValue($item['valor'] ?? null),
                        'rental_value' => ($item['finalidade'] ?? null) === 'Aluguel'
                            ? $this->parseValue($item['valor'] ?? null)
                            : null,
                        'condominio' => $this->parseValue($item['valorcondominio'] ?? null),
                        'iptu' => $this->parseValue($item['valoriptu'] ?? null),
                        'property_type' => $item['tipo'] ?? null,
                        'finality' => $item['finalidade'] ?? null,
                        'destination' => $item['destinacao'] ?? null,
                        'status' => $item['situacao'] ?? null,
                        'address' => $item['endereco'] ?? null,
                        'neighborhood' => $item['bairro'] ?? null,
                        // 'destaque' => $item['destaque'] == 'Super destaque',
                        'city' => $item['cidade'] ?? null,
                        'state' => $item['estado'] ?? null,
                        'zipcode' => $item['cep'] ?? null,
                        'country' => 'Brasil',
                        'area_total' => $area_total,
                        'area_useful' => $area_useful,
                        'bedroom' => $item['numeroquartos'] ?? null,
                        'bathroom' => $item['numerobanhos'] ?? null,
                        'suite' => $item['numerosuites'] ?? null,
                        'garage' => $item['numerovagas'] ?? null,
                        'slug' => Str::slug(($item['titulo'] ?? '') . ' ' . $item['codigo']),
                        'cover_photo' => $this->getPhotos($item),
                        'latitude' => $this->parseDecimal($item['latitude'] ?? null),
                        'longitude' => $this->parseDecimal($item['longitude'] ?? null),
                        'synced_at' => now(),
                    ]
                );

                $changes = array_diff_key($property->getChanges(), array_flip(['updated_at', 'synced_at']));
                $outcome = $property->wasRecentlyCreated ? 'created' : (empty($changes) ? 'unchanged' : 'updated');
                $report->summary[$outcome]++;
                $report->summary['synced']++;
            }

            $report->log("Página {$page} sincronizada. Acumulado: {$report->summary['synced']} imóveis sincronizados.");
            $page++;
        } while (($page - 1) * 20 < $total);

        if (count($apiExternalIds) !== $total) {
            $report->log('O total de códigos únicos recebidos difere do total informado pelo Imoview. Remoção cancelada.', 'error');
            throw new \RuntimeException('Listagem incompleta do Imoview.');
        }

        $report->log('Consulta completa. Removendo somente imóveis do Imoview ausentes na listagem recebida.');
        $report->summary['removed'] = Property::where('crm_origin', 'imoview')
            ->whereNotIn('external_id', array_values($apiExternalIds))
            ->delete();
        $report->log("Remoção concluída: {$report->summary['removed']} imóveis indisponíveis removidos.");
    }

    public function property_detail($external_id)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'chave' => env('IMOVIEW_KEY'),
        ])->get($this->baseUrl . '/Imovel/RetornarDetalhesImovelDisponivel', [
            'codigoImovel' => $external_id
        ]);

        if ($response->failed()) {
            \Log::error('Erro ao consultar detalhes Imoview', ['external_id' => $external_id, 'body' => $response->body()]);
            return null;
        }

        $data = $response->json();
        $item = $data['imovel'] ?? null;

        if (!$item) {
            return null;
        }

        $area_total = $this->parseValue($item['arealote'] ?? null) ?: $this->parseValue($item['areaexterna'] ?? null);
        $area_useful = $this->parseValue($item['areaprincipal'] ?? null) ?: $this->parseValue($item['areainterna'] ?? null);

        Property::updateOrCreate(
            [
                'external_id' => $item['codigo'],
                'crm_origin' => 'imoview',
            ],
            [
                'title' => $item['titulo'] ?? null,
                'description' => $item['descricao'] ?? null,
                'sale_value' => $this->parseValue($item['valor']),
                'rental_value' => $item['finalidade'] === 'Aluguel'
                    ? $this->parseValue($item['valor'])
                    : null,
                'condominio' => $this->parseValue($item['valorcondominio']),
                'iptu' => $this->parseValue($item['valoriptu']),
                'property_type' => $item['tipo'] ?? null,
                'finality' => $item['finalidade'] ?? null,
                'destination' => $item['destinacao'] ?? null,
                'status' => $item['situacao'] ?? null,
                'address' => $item['endereco'] ?? null,
                'neighborhood' => $item['bairro'] ?? null,
                // 'destaque' => $item['destaque'] == 'Super destaque',
                'city' => $item['cidade'] ?? null,
                'state' => $item['estado'] ?? null,
                'zipcode' => $item['cep'] ?? null,
                'country' => 'Brasil',
                'area_total' => $area_total,
                'area_useful' => $area_useful,
                'bedroom' => $item['numeroquartos'] ?? null,
                'bathroom' => $item['numerobanhos'] ?? null,
                'suite' => $item['numerosuites'] ?? null,
                'garage' => $item['numerovagas'] ?? null,
                'slug' => Str::slug($item['titulo'] . ' ' . $item['codigo']),
                'cover_photo' => $this->getPhotos($item),
                'latitude' => $this->parseDecimal($item['latitude'] ?? null),
                'longitude' => $this->parseDecimal($item['longitude'] ?? null),
            ]
        );

        return $item;
    }

    public function saveLead($firstname, $lastname, $email, $cellphone, $countryCode = '55', $content = '', $codigoimovel = null)
    {
        $data = [
            "nome" => $firstname . ' ' . $lastname,
            "telefone" => $countryCode . $cellphone,
            "email" => $email,
            "finalidade" => 1,
            "midia" => "Site Senzano",
            "anotacoes" => $content,
        ];

        if($codigoimovel){
            $data['codigoimovel'] = $codigoimovel;
        }

        $leadResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'chave' => env('IMOVIEW_KEY')
        ])->post("{$this->baseUrl}/Lead/IncluirLead", $data);

        if ($leadResponse->failed()) {
            \Log::error('Erro ao criar lead no Imobzi', ['body' => $leadResponse->body()]);
            return null;
        }

        $leadData = $leadResponse->json();

        return $leadData;
    }

    private function parseValue($value)
    {
        if (empty($value)) return null;
        return floatval(str_replace(',', '.', preg_replace('/[^\d,]/', '', str_replace('.', '', $value))));
    }

    private function parseDecimal($value)
    {
        if (empty($value) || trim($value) === '') return null;
        return floatval($value);
    }

    private function getPhotos($item)
    {
        $photos = [];

        if (!empty($item['urlfotoprincipal'])) {
            $photos[] = $item['urlfotoprincipal'];
        }

        if (isset($item['fotos']) && is_array($item['fotos'])) {
            foreach ($item['fotos'] as $foto) {
                if (isset($foto['url']) && !empty($foto['url'])) {
                    $photos[] = $foto['url'];
                }
            }
        }

        return !empty($photos) ? implode(',', array_unique($photos)) : null;
    }
}
