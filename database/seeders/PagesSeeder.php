<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PagesSeeder extends BaseSeeder
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
        $pages = [
            [
                'title' => 'Política de Privacidade',
                'slug' => 'politica-de-privacidade',
                'content' => '<h1>Política de Privacidade</h1><p>Esta política de privacidade descreve como coletamos, usamos e protegemos suas informações pessoais.</p>',
                'active' => true
            ],
            [
                'title' => 'Termos de Uso',
                'slug' => 'termos-de-uso',
                'content' => '<h1>Termos de Uso</h1><p>Estes termos de uso estabelecem as regras para utilização de nossos serviços.</p>',
                'active' => true
            ]
        ];

        foreach ($pages as $page) {
            \App\Models\Page::updateOrCreate(
                ['slug' => $page['slug']],
                $page
            );
        }
    }
}
