<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\Property;

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
            Property::updateOrCreate(
                [
                    'external_id' => $item['property_id'],
                    'crm_origin' => 'imobzi',
                ],
                [
                    'title' => "{$item['property_type']} em {$item['neighborhood']}",
                    "crm_code" => $item['code'] ?? null,
                    'description' => $item['address'] ?? null,
                    'sale_value' => $item['sale_value'] ?? null,
                    'rental_value' => $item['rental_value'] ?? null,
                    'property_type' => $item['property_type'] ?? null,
                    'finality' => $item['finality'] ?? null,
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
                    'cover_photo' => $this->getPhotos($item),
                    'latitude' => $this->parseDecimal($item['latitude'] ?? null),
                    'longitude' => $this->parseDecimal($item['longitude'] ?? null),
                ]
            );
        }

        // Remove registros que não existem mais na API
        Property::where('crm_origin', 'imobzi')
            ->whereNotIn('external_id', $apiExternalIds)
            ->delete();
    }

    public function property_detail($propertyId)
    {
        $cacheKey = "imobzi_property_{$propertyId}";

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
                'title' => "{$item['property_type']} em {$item['neighborhood']}",
                'description' => $item['site_description'] ?? null,
                "crm_code" => $item['code'] ?? null,
                'sale_value' => $item['sale_value'] ?? null,
                'rental_value' => $item['rental_value'] ?? null,
                'property_type' => $item['property_type'] ?? null,
                'finality' => $item['finality'] ?? null,
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
                'latitude' => $this->parseDecimal($item['latitude'] ?? null),
                'longitude' => $this->parseDecimal($item['longitude'] ?? null),
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
}
