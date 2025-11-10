<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Property;
use Illuminate\Support\Str;

class ImoviewService
{
    protected string $baseUrl = 'https://api.imoview.com.br/Imovel/RetornarImoveisDisponiveis';

    public function import()
    {
        $page = 1;
        $total = 0;
        $apiExternalIds = [];

        do {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'chave' => env('IMOVIEW_KEY'),
            ])->post($this->baseUrl, [
                'numeroPagina' => $page,
                'numeroRegistros' => 20,
            ]);

            if ($response->failed()) {
                \Log::error('Erro ao consultar Imoview', ['body' => $response->body()]);
                break;
            }

            $data = $response->json();
            $properties = $data['lista'] ?? [];
            $total = $data['quantidade'] ?? 0;

            foreach ($properties as $item) {
                $apiExternalIds[] = $item['codigo'];

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
                        'destaque' => $item['destaque'] == 'Super destaque',
                        'city' => $item['cidade'] ?? null,
                        'state' => $item['estado'] ?? null,
                        'zipcode' => $item['cep'] ?? null,
                        'country' => 'Brasil',
                        'area_total' => $this->parseValue($item['arealote'] ?? null),
                        'area_useful' => $this->parseValue($item['areainterna'] ?? null),
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
            }

            $page++;
        } while (($page - 1) * 20 < $total);

        // Remove registros que não existem mais na API
        Property::where('crm_origin', 'imoview')
            ->whereNotIn('external_id', $apiExternalIds)
            ->delete();
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
