<?php

namespace App\Http\Controllers;

use App\Transformers\BaseTransformer;
use Illuminate\Http\Request;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    public static $model = Post::class;
    public static $transformer = null;

    public function post(Request $request)
    {
        $this->authorizeUserAction('create');

        $data = $request->all();
        
        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $path = $image->store('posts', 'public');
            $data['image'] = $path;
        }

        $model = new static::$model;
        $this->restfulService->validateResource($model, $data);
        $resource = $this->restfulService->persistResource(new $model($data));
        $resource = $model::with($model::getItemWith())->where($model->getKeyName(), '=', $resource->getKey())->first();

        return $this->response->item($resource, $this->getTransformer())->setStatusCode(201);
    }

    public function put(Request $request, $uuid)
    {
        $model = static::$model::find($uuid);
        $data = $request->all();
        
        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $path = $image->store('posts', 'public');
            $data['image'] = $path;
            
            // Delete old image if exists
            if ($model && $model->image) {
                Storage::disk('public')->delete($model->image);
            }
        }

        if (!$model) {
            $this->authorizeUserAction('create');
            $model = new static::$model;
            $this->restfulService->validateResource($model, $data);
            $resource = $this->restfulService->persistResource(new $model($data));
            $resource->loadMissing($model::getItemWith());
            return $this->response->item($resource, $this->getTransformer())->setStatusCode(201);
        } else {
            $this->authorizeUserAction('update', $model);
            $this->restfulService->validateResourceUpdate($model, $data);
            $this->restfulService->persistResource($model->fill($data));
            return $this->response->item($model, $this->getTransformer())->setStatusCode(200);
        }
    }
    
    public function getBySlug($slug)
    {
        $post = static::$model::where('slug', $slug)
            ->where('active', true)
            ->first();
            
        if (!$post) {
            return response()->json(['error' => 'Post not found'], 404);
        }
        
        return $this->response->item($post, $this->getTransformer());
    }
    
    public function getPublicPosts()
    {
        $query = static::$model::where('active', true);
        
        // Filter by type if provided
        if (request()->has('type')) {
            $query->where('type', request()->input('type'));
        }
        
        $posts = $query->orderBy('created_at', 'desc')->get();
        
        return $this->response->collection($posts, $this->getTransformer());
    }
}
