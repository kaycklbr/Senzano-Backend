<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Transformers\BaseTransformer;
use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    public static $model = Setting::class;
    public static $transformer = null;

    public function getImoview()
    {
        $key = Setting::where('key', Setting::IMOVIEW_KEY)->first()?->value;

        return response()->json($this->imoviewConfiguration($key));
    }

    public function putImoview(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:4096', 'not_regex:/[\r\n]/'],
        ]);

        $key = trim($data['key']);
        Setting::updateOrCreate(
            ['key' => Setting::IMOVIEW_KEY],
            ['type' => 'text', 'value' => $key]
        );

        return response()->json([
            ...$this->imoviewConfiguration($key),
            'message' => 'Chave do Imoview salva com sucesso.',
        ]);
    }

    private function imoviewConfiguration(?string $key): array
    {
        $configured = filled($key);

        return [
            'configured' => $configured,
            'masked_key' => $configured ? mb_substr($key, -5).'*****' : null,
        ];
    }

    public function getConfig()
    {
        $settings = Setting::where('key', '!=', Setting::IMOVIEW_KEY)->get()->pluck('value', 'key')->toArray();

        $footer_pages = Page::where('active', 1)->where('show_in_footer', 1)->get(['title', 'slug']);
        $settings['footer_pages'] = $footer_pages;
        return response()->json($settings);
    }
}
