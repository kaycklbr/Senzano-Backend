<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PropertyImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null, 'cache.default' => 'array']);
        (require database_path('migrations/2025_11_11_195833_create_settings_table.php'))->up();
        Setting::create(['key' => Setting::IMOVIEW_KEY, 'type' => 'text', 'value' => 'test-imoview-key']);
        Http::preventStrayRequests();
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            foreach ((new Property())->getFillable() as $field) {
                $table->string($field)->nullable();
            }
            $table->timestamps();
            $table->unique(['crm_origin', 'external_id']);
        });
    }

    private function fakeCrm($callback = null): void
    {
        Http::swap(new Factory());
        Http::preventStrayRequests();
        Http::fake($callback);
    }

    public function test_route_requires_authentication(): void
    {
        $this->fakeCrm();
        $this->postJson('/api/properties/import')->assertUnauthorized();
        Http::assertNothingSent();
    }

    private function item(int $code, string $title = 'Casa'): array
    {
        return ['codigo' => $code, 'titulo' => $title, 'valor' => '1.000,00', 'finalidade' => 'Venda'];
    }

    private function runImport(): array
    {
        $this->app['api.auth']->setUser(new \App\Models\User());
        $response = $this->postJson('/api/properties/import');

        return [$response->status(), $response->json()];
    }

    public function test_counts_created_updated_unchanged_and_removed_without_touching_other_crms(): void
    {
        $this->fakeCrm(['*' => Http::response(['lista' => [$this->item(1), $this->item(2)], 'quantidade' => 2])]);
        [$status, $first] = $this->runImport();
        $this->assertSame(200, $status);
        $this->assertSame(2, $first['summary']['created']);

        Property::create(['crm_origin' => 'imoview', 'external_id' => 'obsolete']);
        Property::create(['crm_origin' => 'imobzi', 'external_id' => 'other']);
        $this->fakeCrm(['*' => Http::response(['lista' => [$this->item(1, 'Casa atualizada'), $this->item(2), $this->item(3)], 'quantidade' => 3])]);
        [$status, $second] = $this->runImport();

        $this->assertSame(200, $status);
        $this->assertSame(['received' => 3, 'synced' => 3, 'created' => 1, 'updated' => 1, 'unchanged' => 1, 'removed' => 1, 'pages' => 1, 'errors' => 0], $second['summary']);
        $this->assertNotSame($first['import_id'], $second['import_id']);
        $this->assertCount(count($first['logs']), $second['logs']);
        $this->assertSame(1, Property::where('crm_origin', 'imobzi')->count());
        $this->assertNotNull(Property::where('external_id', '1')->first()->synced_at);
        $this->assertFileExists(storage_path("logs/property-imports/{$second['import_id']}.log"));
    }

    public function test_failed_page_preserves_existing_properties_and_returns_partial_logs(): void
    {
        Property::create(['crm_origin' => 'imoview', 'external_id' => 'obsolete']);
        $items = array_map(fn ($code) => $this->item($code), range(1, 20));
        $this->fakeCrm(['*' => Http::sequence()->push(['lista' => $items, 'quantidade' => 21])->push(['error' => 'private upstream body'], 500)]);
        [$status, $report] = $this->runImport();

        $this->assertSame(500, $status);
        $this->assertFalse($report['success']);
        $this->assertSame(20, $report['summary']['synced']);
        $this->assertSame(0, $report['summary']['removed']);
        $this->assertSame(1, $report['summary']['errors']);
        $this->assertSame(21, Property::count());
        $this->assertStringContainsString('HTTP 500', json_encode($report['logs']));
        $this->assertStringNotContainsString('private upstream body', json_encode($report));

        $this->fakeCrm(['*' => Http::response(['lista' => [], 'quantidade' => 0])]);
        [$status] = $this->runImport();
        $this->assertSame(200, $status, 'The lock must be released after failure.');
    }

    public function test_incomplete_or_invalid_responses_never_remove_properties(): void
    {
        Property::create(['crm_origin' => 'imoview', 'external_id' => 'obsolete']);
        foreach ([['message' => 'API error'], ['lista' => [], 'quantidade' => 1], ['lista' => [$this->item(1), $this->item(1)], 'quantidade' => 2], ['lista' => [$this->item(2)], 'quantidade' => 2]] as $payload) {
            $this->fakeCrm(['*' => Http::response($payload)]);
            [$status, $report] = $this->runImport();
            $this->assertSame(500, $status);
            $this->assertSame(0, $report['summary']['removed']);
            $this->assertTrue(Property::where('external_id', 'obsolete')->exists());
        }
    }

    public function test_successful_pagination_and_empty_catalog(): void
    {
        $items = array_map(fn ($code) => $this->item($code), range(1, 20));
        $this->fakeCrm(['*' => Http::sequence()->push(['lista' => $items, 'quantidade' => 21])->push(['lista' => [$this->item(21)], 'quantidade' => 21])]);
        [$status, $report] = $this->runImport();
        $this->assertSame(200, $status);
        $this->assertSame(21, $report['summary']['synced']);
        $this->assertSame(2, $report['summary']['pages']);
        Http::assertSent(fn ($request) => $request['numeroPagina'] === 2);

        Property::create(['crm_origin' => 'imobzi', 'external_id' => 'other']);
        $this->fakeCrm(['*' => Http::response(['lista' => [], 'quantidade' => 0])]);
        [$status, $report] = $this->runImport();
        $this->assertSame(200, $status);
        $this->assertSame(21, $report['summary']['removed']);
        $this->assertSame(1, Property::count());
    }

    public function test_concurrent_import_is_rejected_without_calling_the_crm(): void
    {
        $this->fakeCrm();
        $lock = Cache::lock('properties:import', 60);
        $lock->get();
        try {
            [$status, $report] = $this->runImport();
            $this->assertSame(409, $status);
            $this->assertSame('conflict', $report['status']);
            $this->assertSame(0, $report['summary']['synced']);
            Http::assertNothingSent();
            $this->assertFalse(Cache::lock('properties:import', 60)->get());
        } finally {
            $lock->release();
        }
    }

    public function test_missing_key_stops_import_without_using_the_environment(): void
    {
        Setting::where('key', Setting::IMOVIEW_KEY)->delete();
        putenv('IMOVIEW_KEY=unused-environment-key');
        $this->fakeCrm();
        try {
            [$status, $report] = $this->runImport();
            $this->assertSame(500, $status);
            $this->assertStringContainsString('Configure e salve a chave', json_encode($report['logs']));
            $this->assertSame(0, $report['summary']['removed']);
            Http::assertNothingSent();
        } finally {
            putenv('IMOVIEW_KEY');
        }
    }

    public function test_saved_key_authenticates_import_details_and_leads(): void
    {
        $this->fakeCrm(['*' => Http::response(['lista' => [], 'quantidade' => 0])]);
        [$status] = $this->runImport();
        $this->assertSame(200, $status);
        $service = app(\App\Services\ImoviewService::class);
        $service->property_detail(123);
        $service->saveLead('Teste', 'Local', 'test@example.com', '11999999999');
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/Imovel/RetornarImoveisDisponiveis') && $request->hasHeader('chave', 'test-imoview-key'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/Imovel/RetornarDetalhesImovelDisponivel') && $request->hasHeader('chave', 'test-imoview-key'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/Lead/IncluirLead') && $request->hasHeader('chave', 'test-imoview-key'));
    }

    public function test_cli_output_includes_readable_totals(): void
    {
        $this->fakeCrm(['*' => Http::response(['lista' => [], 'quantidade' => 0])]);
        $this->assertSame(0, Artisan::call('properties:import'));
        $output = Artisan::output();
        $this->assertStringContainsString('0 sincronizados', $output);
        $this->assertStringContainsString('0 removidos', $output);
    }
}
