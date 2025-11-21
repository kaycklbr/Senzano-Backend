<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use App\Services\ImobziService;
use App\Services\ImoviewService;
use App\Transformers\BaseTransformer;
use Illuminate\Http\Request;
use App\Models\Property;

class PropertyController extends Controller
{
    /**
     * @var {{ model }} The primary model associated with this controller
     */
    public static $model = Property::class;

    /**
     * @var null|BaseTransformer The transformer this controller should use, if overriding the model & default
     */
    public static $transformer = null;

    public function index(Request $request)
    {
        if ($request->query('vrsync') == '1') {
            return \Cache::remember('properties_all', 86400, function () {
                return $this->getAllPropertiesData();
            });
        }
        
        return $this->getPropertiesWithFilters($request);
    }
    
    private function getAllPropertiesData() {
        $properties = Property::whereIn('status', ['available', 'Vago/Disponível'])->get();
        
        $stats = [
            'total' => $properties->count(),
            'imobzi' => $properties->where('crm_origin', 'imobzi')->count(),
            'imoview' => $properties->where('crm_origin', 'imoview')->count(),
        ];
        
        return response()->json([
            'data' => $properties,
            'stats' => $stats
        ]);
    }
    
    private function getPropertiesWithFilters(Request $request) {
        $query = Property::query();

        if ($request->filled('city')) {
            $cities = array_map('trim', explode(',', strtolower($request->city)));
            $query->whereIn(\DB::raw('TRIM(LOWER(city))'), $cities);
        }

        if ($request->filled('neighborhood')) {
            $neighborhoods = array_map('trim', explode(',', strtolower($request->neighborhood)));
            $query->whereIn(\DB::raw('TRIM(LOWER(neighborhood))'), $neighborhoods);
        }

        if ($request->filled('address')) {
            $addresses = array_map('trim', explode(',', strtolower($request->address)));
            $query->where(function($q) use ($addresses) {
                foreach ($addresses as $address) {
                    $q->orWhereRaw('TRIM(LOWER(address)) LIKE ?', ['%' . $address . '%']);
                }
            });
        }

        if ($request->filled('bedroom')) {
            $bedrooms = array_map('trim', explode(',', $request->bedroom));
            $query->whereIn('bedroom', $bedrooms);
        }

        if ($request->filled('property_type')) {
            $types = array_map('trim', explode(',', strtolower($request->property_type)));
            $query->whereIn(\DB::raw('TRIM(LOWER(property_type))'), $types);
        }

        if ($request->filled('destaque')) {
            $featured = $request->destaque;
            $query->where('destaque', $featured);
        }

        if ($request->filled('crm_origin')) {
            $query->where('crm_origin', $request->crm_origin);
        }

        if ($request->filled('destination')) {
            $query->where('destination', 'LIKE', '%' . $request->destination . '%');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('crm_code', 'LIKE', '%' . $search . '%')
                  ->orWhere('external_id', 'LIKE', '%' . $search . '%')
                  ->orWhere('title', 'LIKE', '%' . $search . '%');
            });
        }

        if ($request->filled('min_price')) {
            $query->where(function($q) use ($request) {
                $q->where('sale_value', '>=', $request->min_price);
            });
        }

        if ($request->filled('max_price')) {
            $query->where(function($q) use ($request) {
                $q->where('sale_value', '<=', $request->max_price);
            });
        }

        $query->whereIn('status', ['available', 'Vago/Disponível']);

        $result = $query->paginate(20);

        $stats = [
            'total' => Property::whereIn('status', ['available', 'Vago/Disponível'])->count(),
            'imobzi' => Property::where('crm_origin', 'imobzi')->whereIn('status', ['available', 'Vago/Disponível'])->count(),
            'imoview' => Property::where('crm_origin', 'imoview')->whereIn('status', ['available', 'Vago/Disponível'])->count(),
        ];

        $result = $result->toArray();
        $result['stats'] = $stats;

        return response()->json($result);
    }

    public function resume(Request $request){

        $imobziProperties = Property::where('destaque', 1)->where('crm_origin', 'imobzi')->orderByRaw('RAND()')->limit(9)->get();
        $imoviewProperties = Property::where('destaque', 1)->where('crm_origin', 'imoview')->orderByRaw('RAND()')->limit(9)->get();
        $destaqueProperties = Property::where('destaque', 1)->orderByRaw('RAND()')->limit(3)->get();
        $lancamentos = Post::where('type', 'lancamento')->where('active', 1)->orderByDesc('id')->get();
        $pages = Page::where('show_in_home', true)->where('active', 1)->get();


        return response()->json([
            'destaque' => $destaqueProperties,
            'venda' => $imobziProperties,
            'locacao' => $imoviewProperties,
            'pages' => $pages,
            'lancamentos' => $lancamentos
        ]);

    }

    public function findById(Request $request, $id){
        $property = Property::where('id', $id)->orWhere('slug', $id)->first();

        if(!$property){
            return response()->json([
                'message' => 'Não encontrado'
            ], 404);
        }

        if($property->crm_origin == 'imobzi'){
            $imobziService = new ImobziService();
            $imobziService->property_detail($property->external_id);
            $property = Property::where('id', $id)->orWhere('slug', $id)->first();
        }

        $similar = Property::where('crm_origin', $property->crm_origin)->orderByRaw('RAND()')->limit(3)->get();

        $property->similar = $similar;
        return response()->json($property);
    }

    public function filters(Request $request)
    {
        $baseQuery = Property::query();

        if ($request->filled('crm_origin')) {
            $baseQuery->where('crm_origin', $request->crm_origin);
        }

        // if ($request->filled('city')) {
        //     $cities = array_map('trim', explode(',', strtolower($request->city)));
        //     $baseQuery->whereIn(\DB::raw('TRIM(LOWER(city))'), $cities);
        // }

        // if ($request->filled('neighborhood')) {
        //     $neighborhoods = array_map('trim', explode(',', strtolower($request->neighborhood)));
        //     $baseQuery->whereIn(\DB::raw('TRIM(LOWER(neighborhood))'), $neighborhoods);
        // }

        $filters = [
            'cities' => (clone $baseQuery)->selectRaw('TRIM(city) as city')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->distinct()
                ->orderBy('city')
                ->pluck('city'),
            'bedrooms' => (clone $baseQuery)->whereNotNull('bedroom')
                ->distinct()
                ->orderBy('bedroom')
                ->pluck('bedroom'),
            'property_types' => (clone $baseQuery)->selectRaw('TRIM(property_type) as property_type')
                ->whereNotNull('property_type')
                ->where('property_type', '!=', '')
                ->distinct()
                ->orderBy('property_type')
                ->pluck('property_type')
        ];

        // Filtro cascata para bairros
        $neighborhoodQuery = (clone $baseQuery)->selectRaw('TRIM(neighborhood) as neighborhood')
            ->whereNotNull('neighborhood')
            ->where('neighborhood', '!=', '');

        if ($request->filled('city')) {
            $cities = array_map('trim', explode(',', strtolower($request->city)));
            $neighborhoodQuery->whereIn(\DB::raw('TRIM(LOWER(city))'), $cities);
        }

        $filters['neighborhoods'] = $neighborhoodQuery->distinct()->orderBy('neighborhood')->pluck('neighborhood');

        // Filtro cascata para ruas
        $addressQuery = (clone $baseQuery)->selectRaw('TRIM(address) as address')
            ->whereNotNull('address')
            ->where('address', '!=', '');

        if ($request->filled('neighborhood')) {
            $neighborhoods = array_map('trim', explode(',', strtolower($request->neighborhood)));
            $addressQuery->whereIn(\DB::raw('TRIM(LOWER(neighborhood))'), $neighborhoods);
        }

        $filters['addresses'] = $addressQuery->distinct()->orderBy('address')->pluck('address');

        return $filters;
    }

    public function note(Request $request) {
        $request->validate([
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'email' => 'required|email',
            'cellphone' => 'required|string',
            'message' => 'nullable|string',
            'type' => 'nullable|string',
        ]);

        if(!$request->has('property_id') && !$request->has('type')){
            return response()->json(['error' => 'Erro ao criar lead'], 500);
        }

        $property = $request->has('property_id') ? Property::find($request->property_id) : null;

        $message = $request->message;

        if(empty($message)){
            if($property){
                $message = "Imóvel:" . $property->title . "\nCódigo:". $property->crm_code  ."\n\nMensagem:\n".$message;
            }else{
                $message = "Tenho interesse em imóveis para " . ($request->type == 'venda' ? 'venda' : 'locação');
            }
        }

        $cellphone = preg_replace('/\D/', '', $request->cellphone);


        if(($property && $property->crm_origin == 'imobzi') || ($request->has('type') && $request->type == 'venda')){
            $imobziService = new ImobziService();

            $lead = $imobziService->saveLead(
                $request->firstname,
                $request->lastname,
                $request->email,
                $cellphone,
                '55',
                $message
            );


            if (!$lead) {
                return response()->json(['error' => 'Erro ao criar lead'], 500);
            }

        }else{
            $imoviewService = new ImoviewService();

            $lead = $imoviewService->saveLead(
                $request->firstname,
                $request->lastname,
                $request->email,
                $cellphone,
                '55',
                $message,
                $property ? $property->external_id : null
            );

        }

        return response()->json($lead);
    }

    public function toggleFeatured(Request $request, $id)
    {
        $property = Property::findOrFail($id);
        $property->destaque = !$property->destaque;
        $property->save();

        return response()->json([
            'success' => true,
            'destaque' => $property->destaque
        ]);
    }
}
