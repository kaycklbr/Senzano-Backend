<?php

namespace App\Http\Controllers;

use App\Transformers\BaseTransformer;
use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    public static $model = Setting::class;
    public static $transformer = null;
}
