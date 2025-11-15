<?php

namespace App\Http\Controllers;

use App\Transformers\BaseTransformer;
use Illuminate\Http\Request;
use App\Models\Page;
use Illuminate\Support\Facades\Storage;

class PageController extends Controller
{
    public static $model = Page::class;
    public static $transformer = null;

    public function post(Request $request)
    {
        $this->authorizeUserAction('create');

        $data = $request->all();

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $path = $image->store('pages', 'public');
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

        // Convert boolean fields

        return response()->json(var_dump($data));

        $data['active'] = $request->input('active') === '1' || $request->input('active') === true;
        $data['show_in_home'] = $request->input('show_in_home') === '1' || $request->input('show_in_home') === true;
        $data['show_in_footer'] = $request->input('show_in_footer') === '1' || $request->input('show_in_footer') === true;

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $path = $image->store('pages', 'public');
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
        $page = static::$model::where('slug', $slug)
            ->where('active', true)
            ->first();

        if (!$page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        return $this->response->item($page, $this->getTransformer());
    }
}
