<?php

use Dingo\Api\Routing\Router;
use Illuminate\Http\Request;
use Specialtactics\L5Api\Http\Middleware\CheckUserRole;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
 * Welcome route - link to any public API documentation here
 */
Route::get('/', function () {
    echo 'Welcome to our API';
});

/** @var \Dingo\Api\Routing\Router $api */
$api = app('Dingo\Api\Routing\Router');
$api->version('v1', ['middleware' => ['api']], function (Router $api) {
    /*
     * Authentication
     */
    $api->group(['prefix' => 'auth'], function (Router $api) {
        $api->group(['prefix' => 'jwt'], function (Router $api) {
            $api->get('/token', 'App\Http\Controllers\Auth\AuthController@token');
        });
    });

    $api->get('/resume', 'App\Http\Controllers\PropertyController@resume');
    $api->get('/properties', 'App\Http\Controllers\PropertyController@index');
    $api->get('/properties/filters', 'App\Http\Controllers\PropertyController@filters');
    $api->post('/properties/note', 'App\Http\Controllers\PropertyController@note');
    $api->get('/properties/{id}', 'App\Http\Controllers\PropertyController@findById');
    
    // Public routes
    $api->get('/page/{slug}', 'App\Http\Controllers\PageController@getBySlug');
    $api->get('/post/{slug}', 'App\Http\Controllers\PostController@getBySlug');
    $api->get('/posts', 'App\Http\Controllers\PostController@getPublicPosts');

    /*
     * Authenticated routes
     */
    $api->group(['middleware' => ['api.auth']], function (Router $api) {
        /*
         * Authentication
         */
        $api->group(['prefix' => 'auth'], function (Router $api) {
            $api->group(['prefix' => 'jwt'], function (Router $api) {
                $api->get('/refresh', 'App\Http\Controllers\Auth\AuthController@refresh');
                $api->delete('/token', 'App\Http\Controllers\Auth\AuthController@logout');
            });

            $api->get('/me', 'App\Http\Controllers\Auth\AuthController@getUser');
        });

        /*
         * Users
         */
        $api->group(['prefix' => 'users', 'middleware' => 'check_role:admin'], function (Router $api) {
            $api->get('/', 'App\Http\Controllers\UserController@getAll');
            $api->get('/{uuid}', 'App\Http\Controllers\UserController@get');
            $api->post('/', 'App\Http\Controllers\UserController@post');
            $api->put('/{uuid}', 'App\Http\Controllers\UserController@put');
            $api->patch('/{uuid}', 'App\Http\Controllers\UserController@patch');
            $api->delete('/{uuid}', 'App\Http\Controllers\UserController@delete');
        });

        /*
         * Roles
         */
        $api->group(['prefix' => 'roles'], function (Router $api) {
            $api->get('/', 'App\Http\Controllers\RoleController@getAll');
        });

        /*
         * Posts
         */
        $api->group(['prefix' => 'posts'], function (Router $api) {
            $api->get('/', 'App\Http\Controllers\PostController@getAll');
            $api->get('/{uuid}', 'App\Http\Controllers\PostController@get');
            $api->post('/', 'App\Http\Controllers\PostController@post');
            $api->put('/{uuid}', 'App\Http\Controllers\PostController@put');
            $api->patch('/{uuid}', 'App\Http\Controllers\PostController@patch');
            $api->delete('/{uuid}', 'App\Http\Controllers\PostController@delete');
        });

        /*
         * Settings
         */
        $api->group(['prefix' => 'settings'], function (Router $api) {
            $api->get('/', 'App\Http\Controllers\SettingController@getAll');
            $api->get('/{id}', 'App\Http\Controllers\SettingController@get');
            $api->post('/', 'App\Http\Controllers\SettingController@post');
            $api->put('/{id}', 'App\Http\Controllers\SettingController@put');
            $api->patch('/{id}', 'App\Http\Controllers\SettingController@patch');
            $api->delete('/{id}', 'App\Http\Controllers\SettingController@delete');
        });

        /*
         * Pages
         */
        $api->group(['prefix' => 'pages'], function (Router $api) {
            $api->get('/', 'App\Http\Controllers\PageController@getAll');
            $api->get('/{uuid}', 'App\Http\Controllers\PageController@get');
            $api->post('/', 'App\Http\Controllers\PageController@post');
            $api->put('/{uuid}', 'App\Http\Controllers\PageController@put');
            $api->patch('/{uuid}', 'App\Http\Controllers\PageController@patch');
            $api->delete('/{uuid}', 'App\Http\Controllers\PageController@delete');
        });
        
        /*
         * Properties
         */
        $api->patch('/properties/{id}/toggle-featured', 'App\Http\Controllers\PropertyController@toggleFeatured');
    });
});
