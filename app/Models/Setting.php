<?php

namespace App\Models;

use App\Transformers\BaseTransformer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Crypt;

class Setting extends BaseModel
{
    use HasFactory;

    public const IMOVIEW_KEY = 'imoview_key';

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

    protected $fillable = [
        'key',
        'value',
        'type'
    ];

    protected $casts = [
        'value' => 'string',
    ];

    public function getHidden(): array
    {
        return $this->key === self::IMOVIEW_KEY
            ? array_merge(parent::getHidden(), ['value'])
            : parent::getHidden();
    }

    public function getValueAttribute($value)
    {
        if ($this->key === self::IMOVIEW_KEY && filled($value)) {
            return Crypt::decryptString($value);
        }

        if ($this->type === 'json') {
            return json_decode($value, true);
        }
        if ($this->type === 'boolean') {
            return (bool) $value;
        }
        return $value;
    }

    public function setValueAttribute($value)
    {
        if ($this->key === self::IMOVIEW_KEY) {
            $this->attributes['value'] = filled($value) ? Crypt::encryptString(trim($value)) : null;
            return;
        }

        if ($this->type === 'json') {
            $this->attributes['value'] = json_encode($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    public function getValidationRules(): array
    {
        return [
            'key' => 'required|string|unique:settings,key,' . $this->id,
            'value' => 'nullable',
            'type' => 'required|in:text,json,boolean'
        ];
    }

}
