<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImoviewSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        (require database_path('migrations/2025_11_11_195833_create_settings_table.php'))->up();
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->boolean('active');
            $table->boolean('show_in_footer');
        });
    }

    public function test_credentials_endpoints_require_authentication(): void
    {
        $this->getJson('/api/settings/imoview')->assertUnauthorized();
        $this->putJson('/api/settings/imoview', ['key' => 'test-key'])->assertUnauthorized();
        $this->assertSame(0, Setting::count());
    }

    public function test_editor_creates_and_replaces_the_encrypted_option_without_returning_the_secret(): void
    {
        $this->app['api.auth']->setUser(new User());
        $this->getJson('/api/settings/imoview')->assertOk()->assertExactJson(['configured' => false]);
        $response = $this->putJson('/api/settings/imoview', ['key' => '  first-test-key  ']);
        $response->assertOk()->assertJsonPath('configured', true);
        $this->assertStringNotContainsString('first-test-key', $response->getContent());
        $setting = Setting::where('key', Setting::IMOVIEW_KEY)->firstOrFail();
        $this->assertSame('first-test-key', $setting->value);
        $this->assertArrayNotHasKey('value', $setting->toArray());
        $this->assertNotSame('first-test-key', DB::table('settings')->value('value'));
        $this->getJson('/api/settings/imoview')->assertExactJson(['configured' => true]);
        $this->putJson('/api/settings/imoview', ['key' => 'replacement-test-key'])->assertOk();
        $this->assertSame(1, Setting::count());
        $this->assertSame('replacement-test-key', $setting->fresh()->value);
    }

    public function test_invalid_keys_cannot_overwrite_a_working_key(): void
    {
        $this->app['api.auth']->setUser(new User());
        Setting::create(['key' => Setting::IMOVIEW_KEY, 'type' => 'text', 'value' => 'saved-test-key']);
        foreach (['', '   ', "header\ninjection", str_repeat('x', 4097)] as $invalid) {
            $this->putJson('/api/settings/imoview', ['key' => $invalid])->assertStatus(422);
            $this->assertSame('saved-test-key', Setting::where('key', Setting::IMOVIEW_KEY)->first()->value);
        }
    }

    public function test_public_configuration_excludes_the_key_but_keeps_public_options(): void
    {
        Setting::create(['key' => Setting::IMOVIEW_KEY, 'type' => 'text', 'value' => 'private-test-key']);
        Setting::create(['key' => 'address', 'type' => 'text', 'value' => 'Public address']);
        $this->assertSame('Public address', Setting::where('key', 'address')->first()->toArray()['value']);
        $this->getJson('/api/public/config')->assertOk()->assertJsonPath('address', 'Public address')
            ->assertJsonMissingPath('imoview_key')->assertDontSee('private-test-key');
    }
}
