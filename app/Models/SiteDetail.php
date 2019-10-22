<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteDetails extends Model
{
    protected $fillable = [
        'site_id',
        'comment',
        'link'
    ];

    // protected $table = 'site_details';

    public function site()
    {
        return $this->belongsTo('App\Models\Site');
    }
}
