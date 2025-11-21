<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class VrSyncController extends Controller
{
    public function generateXml(Request $request)
    {
        $properties = \Cache::remember('vrsync_xml', 86400, function () {
            return Property::whereIn('status', ['available', 'Vago/Disponível'])->get();
        });

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><ListingDataFeed xmlns="http://www.vivareal.com/schemas/1.0/VRSync" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.vivareal.com/schemas/1.0/VRSync http://xml.vivareal.com/vrsync.xsd"></ListingDataFeed>');

        // Header
        $header = $xml->addChild('Header');
        $header->addChild('Provider', 'Senzano Empreendimentos');
        $header->addChild('Email', 'contato@senzano.com.br');
        $header->addChild('ContactName', 'Senzano');
        $header->addChild('PublishDate', now()->toISOString());
        $header->addChild('Telephone', '67-99877 7583');

        // Listings
        $listings = $xml->addChild('Listings');

        foreach ($properties as $property) {
            $listing = $listings->addChild('Listing');

            $listing->addChild('ListingID', $property->external_id ?: $property->id);
            $listing->addChild('Title', htmlspecialchars($property->title));

            // TransactionType baseado no crm_origin
            if ($property->crm_origin === 'imobzi') {
                $listing->addChild('TransactionType', 'For Sale');
            } elseif ($property->crm_origin === 'imoview') {
                $listing->addChild('TransactionType', 'For Rent');
            }

            $listing->addChild('PublicationType', $property->destaque ? 'PREMIUM' : 'STANDARD');
            $listing->addChild('DetailViewUrl', "https://dev.senzanoempreendimentos.com.br/imovel/{$property->slug}");

            // Media
            if ($property->cover_photo) {
                $media = $listing->addChild('Media');
                $photos = explode(',', $property->cover_photo);
                foreach ($photos as $index => $photo) {
                    $item = $media->addChild('Item', htmlspecialchars(trim($photo)));
                    $item->addAttribute('medium', 'image');
                    $item->addAttribute('caption', 'img' . ($index + 1));
                    if ($index === 0) {
                        $item->addAttribute('primary', 'true');
                    }
                }
            }

            // Details
            $details = $listing->addChild('Details');
            $details->addChild('UsageType', $property->finality === 'commercial' ? 'Commercial' : 'Residential');
            $details->addChild('PropertyType', $property->finality === 'commercial' ? 'Commercial / Office' : 'Residential / ' . $property->property_type);

            $description = $details->addChild('Description');
            $description[0] = strip_tags($property->description ?: $property->title);

            if ($property->sale_value > 0) {
                $details->addChild('ListPrice', $property->sale_value)->addAttribute('currency', 'BRL');
            }
            if ($property->rental_value > 0) {
                $rentalPrice = $details->addChild('RentalPrice', $property->rental_value);
                $rentalPrice->addAttribute('currency', 'BRL');
                $rentalPrice->addAttribute('period', 'Monthly');
            }

            if ($property->area_useful) {
                $livingArea = $details->addChild('LivingArea', $property->area_useful);
                $livingArea->addAttribute('unit', 'square metres');
            }
            if ($property->area_total) {
                $lotArea = $details->addChild('LotArea', $property->area_total);
                $lotArea->addAttribute('unit', 'square metres');
            }
            if ($property->condominio) {
                $details->addChild('PropertyAdministrationFee', $property->condominio)->addAttribute('currency', 'BRL');
            }
            if ($property->iptu) {
                $details->addChild('YearlyTax', $property->iptu)->addAttribute('currency', 'BRL');
            }

            if ($property->bedroom) $details->addChild('Bedrooms', $property->bedroom);
            if ($property->bathroom) $details->addChild('Bathrooms', $property->bathroom);
            if ($property->suite) $details->addChild('Suites', $property->suite);
            if ($property->garage) {
                $garage = $details->addChild('Garage', $property->garage);
                $garage->addAttribute('type', 'Parking Space');
            }

            // Location
            $location = $listing->addChild('Location');
            $location->addAttribute('displayAddress', 'All');

            $country = $location->addChild('Country', 'Brasil');
            $country->addAttribute('abbreviation', 'BR');

            $state = $location->addChild('State', $property->state ?: 'MS');
            $state->addAttribute('abbreviation', 'MS');

            $location->addChild('City', $property->city ?: 'Campo Grande');
            if ($property->neighborhood) $location->addChild('Neighborhood', htmlspecialchars($property->neighborhood));
            if ($property->address) $location->addChild('Address', htmlspecialchars($property->address));
            if ($property->address_complement) $location->addChild('Complement', htmlspecialchars($property->address_complement));
            if ($property->zipcode) $location->addChild('PostalCode', $property->zipcode);
            if ($property->latitude) $location->addChild('Latitude', $property->latitude);
            if ($property->longitude) $location->addChild('Longitude', $property->longitude);

            // ContactInfo
            $contact = $listing->addChild('ContactInfo');
            $contact->addChild('Name', 'Senzano Empreendimentos');
            $contact->addChild('Email', 'contato@senzano.com.br');
            $contact->addChild('Website', 'https://dev.senzanoempreendimentos.com.br');
            $contact->addChild('Telephone', '(67) 99841-0528');
        }

        return response($xml->asXML(), 200, ['Content-Type' => 'application/xml']);
    }
}
