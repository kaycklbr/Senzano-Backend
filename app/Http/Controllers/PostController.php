<?php

namespace App\Http\Controllers;

use App\Transformers\BaseTransformer;
use Illuminate\Http\Request;
use App\Models\Post;

class PostController extends Controller
{
    public static $model = Post::class;
    public static $transformer = null;
}
