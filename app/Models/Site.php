<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $fillable = [
        'site_name',
        'classification'
    ];

    public function siteDetail()
    {
        return $this->hasOne('App\Models\SiteDetails', 'site_id');
    }
}
