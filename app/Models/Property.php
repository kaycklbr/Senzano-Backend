<?php

namespace App\Models;

use App\Transformers\BaseTransformer;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Property extends BaseModel
{
    use HasFactory;

    /**
     * @var string Primary key of the resource
     */
    public $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = true;

    /**
     * The "type" of the auto-incrementing ID.
     */
    protected $keyType = 'int';

    /**
     * @var null|array What relations should one model of this entity be returned with, from a relevant controller
     */
    public static ?array $itemWith = [];

    /**
     * @var null|array What relations should a collection of models of this entity be returned with, from a relevant controller
     * If left null, then $itemWith will be used
     */
    public static ?array $collectionWith = null;

    /**
     * @var null|BaseTransformer The transformer to use for this model, if overriding the default
     */
    public static $transformer = null;

    /**
     * @var array The attributes that are mass assignable.
     */
    protected $fillable = [
        'crm_origin',
        'crm_code',
        'external_id',
        'code',
        'title',
        'slug',
        'description',
        'sale_value',
        'rental_value',
        'condominio',
        'destaque',
        'iptu',
        'property_type',
        'finality',
        'status',
        'address',
        'address_complement',
        'neighborhood',
        'city',
        'state',
        'zipcode',
        'country',
        'area_total',
        'area_useful',
        'bedroom',
        'bathroom',
        'suite',
        'garage',
        'cover_photo',
        'videos',
        'latitude',
        'longitude',
    ];

    /**
     * @var array The attributes that should be hidden for arrays and API output
     */
    protected $hidden = [];

    /**
     * Return the validation rules for this model
     *
     * @return array Rules
     */
    public function getValidationRules(): array
    {
        return [];
    }

}
