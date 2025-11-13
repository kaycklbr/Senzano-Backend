<?php

namespace App\Models;

use App\Transformers\BaseTransformer;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Page extends BaseModel
{
    use HasFactory;

    public $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'title',
        'content',
        'slug',
        'image',
        'show_in_home',
        'show_in_footer',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean',
        'show_in_home' => 'boolean',
        'show_in_footer' => 'boolean',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($model->slug)) {
                $model->slug = \Illuminate\Support\Str::slug($model->title);
            }
        });
    }

    public function getValidationRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'slug' => 'nullable|string|unique:pages,slug,' . $this->id,
            'image' => 'nullable|string',
            'show_in_home' => 'boolean',
            'show_in_footer' => 'boolean',
            'active' => 'boolean'
        ];
    }

}
