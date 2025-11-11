<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends BaseSeeder
{
    /**
     * Run fake seeds - for non production environments
     *
     * @return mixed
     */
    public function runFake() {

    }

    /**
     * Run seeds to be ran only on production environments
     *
     * @return mixed
     */
    public function runProduction() {

    }

    /**
     * Run seeds to be ran on every environment (including production)
     *
     * @return mixed
     */
    public function runAlways() {
        $settings = [
            ['key' => 'site_name', 'value' => 'Senzano', 'type' => 'text'],
            ['key' => 'contact_email', 'value' => 'contato@senzano.com', 'type' => 'text'],
            ['key' => 'contact_phone', 'value' => '(11) 99999-9999', 'type' => 'text'],
            ['key' => 'contact_address', 'value' => 'Endereço da empresa', 'type' => 'text'],
            ['key' => 'social_facebook', 'value' => 'https://facebook.com/senzano', 'type' => 'text'],
            ['key' => 'social_instagram', 'value' => 'https://instagram.com/senzano', 'type' => 'text'],
            ['key' => 'social_linkedin', 'value' => 'https://linkedin.com/company/senzano', 'type' => 'text'],
            ['key' => 'social_youtube', 'value' => 'https://youtube.com/senzano', 'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            \App\Models\Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
