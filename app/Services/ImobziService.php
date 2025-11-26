<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\Property;
use Illuminate\Support\Str;

class ImobziService
{
    protected string $baseUrl = 'https://api.imobzi.app/v1';

    public function import()
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Imobzi-Secret' => env('IMOBZI_SECRET'),
        ])->get("{$this->baseUrl}/properties?show_map=true");

        if ($response->failed()) {
            \Log::error('Erro ao consultar Imobzi', ['body' => $response->body()]);
            return;
        }

        $apiExternalIds = [];

        foreach ($response->json('properties_map', []) as $item) {
            $apiExternalIds[] = $item['property_id'];

            $exists = Property::where('external_id', $item['property_id'])->first();

            $title = "{$item['property_type']} em {$item['neighborhood']}";

            $dataToSave = [
                "crm_code" => $item['code'] ?? null,
                'sale_value' => $item['sale_value'] ?? null,
                'rental_value' => $item['rental_value'] ?? null,
                'property_type' => $item['property_type'] ?? null,
                'finality' => $item['finality'] ?? null,
                'destination' => $item['finality'] ?? null,
                'destaque' => $item['stage'] == 'launch',
                'status' => $item['status'] ?? null,
                'address' => $item['address'] ?? null,
                'address_complement' => $item['address_complement'] ?? null,
                'neighborhood' => $item['neighborhood'] ?? null,
                'city' => $item['city'] ?? null,
                'state' => $item['state'] ?? null,
                'zipcode' => $item['zipcode'] ?? null,
                'country' => $item['country'] ?? 'Brasil',
                'area_total' => $item['lot_area'] ?? null,
                'area_useful' => $item['useful_area'] ?? null,
                'bedroom' => $item['bedroom'] ?? null,
                'bathroom' => $item['bathroom'] ?? null,
                'suite' => $item['suite'] ?? null,
                'garage' => $item['garage'] ?? null,
                'slug' => Str::slug($title . ' ' . $item['property_id']),
                'videos' => $this->getVideos($item),
                'latitude' => $this->parseDecimal($item['latitude'] ?? null),
                'longitude' => $this->parseDecimal($item['longitude'] ?? null),
            ];

            if(!$exists){
                $dataToSave['title'] = $title;
                $dataToSave['cover_photo'] = $this->getPhotos($item);
                $dataToSave['description'] = $item['address'] ?? null;

            }

            if(!!$exists){
                $dataToSave['synced_at'] = now();
            }
            Property::updateOrCreate(
                [
                    'external_id' => $item['property_id'],
                    'crm_origin' => 'imobzi',
                ],
                $dataToSave
            );
        }

        // Remove registros que não existem mais na API
        Property::where('crm_origin', 'imobzi')
            ->whereNotIn('external_id', $apiExternalIds)
            ->delete();
    }

    public function property_detail($propertyId, $nocache = false)
    {
        $cacheKey = "imobzi_property_{$propertyId}";

        if($nocache){
            Cache::delete($cacheKey);
        }

        $item = Cache::remember($cacheKey, 86400, function () use ($propertyId) {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Imobzi-Secret' => env('IMOBZI_SECRET'),
            ])->get("{$this->baseUrl}/property/$propertyId");

            if ($response->failed()) {
                \Log::error('Erro ao consultar Imobzi', ['body' => $response->body()]);
                return null;
            }

            return $response->json();
        });


        if (!$item) {
            return null;
        }

        Property::updateOrCreate(
            [
                'external_id' => $propertyId,
                'crm_origin' => 'imobzi',
            ],
            [
                'title' => $item['site_title'],
                'description' => $item['site_description'] ?? null,
                "crm_code" => $item['code'] ?? null,
                'sale_value' => $item['sale_value'] ?? null,
                'rental_value' => $item['rental_value'] ?? null,
                'property_type' => $item['property_type'] ?? null,
                'finality' => $item['finality'] ?? null,
                'destination' => $item['finality'] ?? null,
                'status' => $item['status'] ?? null,
                'address' => $item['address'] ?? null,
                'address_complement' => $item['address_complement'] ?? null,
                'neighborhood' => $item['neighborhood'] ?? null,
                'city' => $item['city'] ?? null,
                'state' => $item['state'] ?? null,
                'zipcode' => $item['zipcode'] ?? null,
                'destaque' => $item['stage'] == 'launch',
                'country' => $item['country'] ?? 'Brasil',
                'area_total' => $item['lot_area'] ?? null,
                'area_useful' => $item['useful_area'] ?? null,
                'bedroom' => $item['bedroom'] ?? null,
                'bathroom' => $item['bathroom'] ?? null,
                'suite' => $item['suite'] ?? null,
                'garage' => $item['garage'] ?? null,
                'cover_photo' => $this->getPhotos($item),
                'videos' => $this->getVideos($item),
                'latitude' => $this->parseDecimal($item['latitude'] ?? null),
                'longitude' => $this->parseDecimal($item['longitude'] ?? null),
                'synced_at' => now()
            ]
        );

        return $item;

    }

    private function parseDecimal($value)
    {
        if (empty($value) || trim($value) === '') return null;
        return floatval($value);
    }

    private function getPhotos($item)
    {
        $photos = [];

        if (isset($item['cover_photo']['url'])) {
            $photos[] = $item['cover_photo']['url'];
        }

        if (isset($item['photos']['photos']) && is_array($item['photos']['photos'])) {
            foreach ($item['photos']['photos'] as $photo) {
                if (isset($photo['url'])) {
                    $photos[] = $photo['url'];
                }
            }
        }

        return !empty($photos) ? implode(',', array_unique($photos)) : null;
    }

    private function getVideos($item)
    {
        $videos = [];

        if (isset($item['multimidias']) && is_array($item['multimidias'])) {
            foreach ($item['multimidias'] as $multimidia) {
                if (isset($multimidia['category']) && $multimidia['category'] === 'videos' && isset($multimidia['url'])) {
                    $videos[] = $multimidia['url'];
                }
            }
        }

        return !empty($videos) ? implode(',', array_unique($videos)) : null;
    }

    public function saveLead($firstname, $lastname, $email, $cellphone, $countryCode = '55', $content = null)
    {
        $leadResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Imobzi-Secret' => env('IMOBZI_SECRET'),
        ])->post("{$this->baseUrl}/leads", [
            'lead' => [
                'cellphone' => [
                    'number' => $cellphone,
                    'country_code' => $countryCode
                ],
                'firstname' => $firstname,
                'email' => $email,
                'lastname' => $lastname,
                'fullname' => "$firstname $lastname"
            ]
        ]);

        if ($leadResponse->failed()) {
            \Log::error('Erro ao criar lead no Imobzi', ['body' => $leadResponse->body()]);
            return null;
        }

        $leadData = $leadResponse->json();
        $leadId = $leadData['db_id'];

        if ($content) {
            $timelineResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Imobzi-Secret' => env('IMOBZI_SECRET'),
            ])->post("{$this->baseUrl}/timeline", [
                'timeline' => [
                    'parent_id' => $leadId,
                    'parent_type' => 'lead',
                    'links_timeline' => [],
                    'type' => 'note',
                    'content' => $content,
                    'users_mentioned' => []
                ]
            ]);

            if ($timelineResponse->failed()) {
                \Log::error('Erro ao criar timeline no Imobzi', ['body' => $timelineResponse->body()]);
            }
        }

        return $leadData;
    }

    public function saveDeal($firstname, $lastname, $email, $cellphone, $countryCode = '55', $content = null, $property = null)
    {
        $contactResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Imobzi-Secret' => env('IMOBZI_SECRET'),
        ])->get("{$this->baseUrl}/contact/exists?email=$email&phone_number=$cellphone");


        $contactResponse = $contactResponse->json();

        if(!isset($contactResponse['contact']['db_id'])){
            $contactResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Imobzi-Secret' => env('IMOBZI_SECRET'),
            ])->post("{$this->baseUrl}/persons", [
                'person' => [
                    'active' => true,
                    'code' => 0,
                    'db_id' => 0,
                    'field_values' => [
                        [
                            'field_id' => 'phone',
                            'value' => [
                                [
                                    'alpha2Code' => 'br',
                                    'number' => $cellphone,
                                    'country_code' => $countryCode,
                                    'type' => 'main_phone'
                                ],
                            ]
                        ],
                    ],
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'fullname' => "$firstname $lastname",
                    'email' => $email,
                    'type' => 'person'
                ]
            ]);

            if ($contactResponse->failed()) {
                \Log::error('Erro ao criar contato no Imobzi', ['body' => $contactResponse->body()]);
                return null;
            }

            $contactId = $contactResponse['db_id'];
        }else{
            $contactId = $contactResponse['contact']['db_id'];
        }

        if ($content) {
            $dealResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Imobzi-Secret' => env('IMOBZI_SECRET'),
            ])->post("{$this->baseUrl}/deals", [
                'deal' => [
                    'contact_id' => strval($contactId),
                    'contact_type' => 'person',
                    'title' => "$firstname $lastname",
                    'description' => $content,
                    'interest' => 'buy',
                    'status' => 'in progress',
                    'value' => $property ? $property->sale_value : 0
                ]
            ]);

            if ($dealResponse->failed()) {
                \Log::error('Erro ao criar negócio no Imobzi', ['body' => $dealResponse->body()]);
            }
        }

        return $contactResponse;
    }
}
