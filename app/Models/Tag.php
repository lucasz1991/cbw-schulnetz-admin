<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'slug'];

    public function products()
    {
        return Product::query()->whereRaw('JSON_CONTAINS(tags, ?)', [json_encode($this->id)]);
    }
}
