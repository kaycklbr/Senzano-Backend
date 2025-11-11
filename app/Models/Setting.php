<?php

namespace App\Models;

use App\Transformers\BaseTransformer;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Setting extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type'
    ];

    protected $casts = [
        'value' => 'string',
    ];

    public function getValueAttribute($value)
    {
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
