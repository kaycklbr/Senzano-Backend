<?php

namespace App\Http\Controllers;

use App\Transformers\BaseTransformer;
use Illuminate\Http\Request;
use App\Models\Page;

class PageController extends Controller
{
    public static $model = Page::class;
    public static $transformer = null;
}
