<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

class PropertyImportController extends Controller
{
    public function store(): JsonResponse
    {
        $output = new BufferedOutput();
        $exitCode = Artisan::call('properties:import', ['--json' => true], $output);
        $result = json_decode(trim($output->fetch()), true, 512, JSON_THROW_ON_ERROR);

        return response()->json($result, match ($exitCode) {
            0 => 200,
            2 => 409,
            default => 500,
        });
    }
}
