<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'price',
        'colors',
        'sizes',
        'gender',
        'style_category',
        'thumbnail',
        'is_active',
    ];

    protected $casts = [
        'colors'    => 'array',  
        'sizes'     => 'array',  
        'is_active' => 'boolean',
        'price'     => 'float',
    ];

    
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}