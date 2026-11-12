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
        return response()->json([
            'configured' => filled(Setting::where('key', Setting::IMOVIEW_KEY)->first()?->value),
        ]);
    }

    public function putImoview(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:4096', 'not_regex:/[\r\n]/'],
        ]);

        Setting::updateOrCreate(
            ['key' => Setting::IMOVIEW_KEY],
            ['type' => 'text', 'value' => trim($data['key'])]
        );

        return response()->json(['configured' => true, 'message' => 'Chave do Imoview salva com sucesso.']);
    }

    public function getConfig()
    {
        $settings = Setting::where('key', '!=', Setting::IMOVIEW_KEY)->get()->pluck('value', 'key')->toArray();

        $footer_pages = Page::where('active', 1)->where('show_in_footer', 1)->get(['title', 'slug']);
        $settings['footer_pages'] = $footer_pages;
        return response()->json($settings);
    }
}
