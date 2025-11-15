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

    public function getConfig()
    {
        $settings = Setting::pluck('value', 'key');

        $footer_pages = Page::select(['title', 'slug'])->where('active', 1)->where('show_in_footer', 1)->get();
        $settings['footer_pages'] = $footer_pages;
        return response()->json($settings);
    }
}
