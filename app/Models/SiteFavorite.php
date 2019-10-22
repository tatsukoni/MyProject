<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteFavorite extends Model
{
    //
    protected $fillable = [
        'site_id',
        'created_at',
        'updated_at',
    ];
}
