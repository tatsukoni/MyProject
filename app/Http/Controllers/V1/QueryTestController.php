<?php

namespace App\Http\Controllers\V1;

use App\Transformers\QueryTestTransformer;
use App\Http\Controllers\Controller;
use App\Http\RestResponse;
use Illuminate\Http\Request;
use League\Fractal\Manager;

class QueryTestController extends Controller
{
    use RestResponse;

    private $fractal;
    private $transformer;

    public function __construct(Manager $fractal, QueryTestTransformer $queryTestTransformer)
    {
        $this->fractal = $fractal;
        $this->transformer = $queryTestTransformer;
    }

    public function query()
    {
        $sites = \DB::table('sites')
            ->select(['site_name', 'classification', 'comment', 'link'])
            ->join('site_details', 'sites.id', '=', 'site_details.site_id')
            ->orderBy('sites.created_at', 'asc')
            ->get();
        return $this->sendSuccess(200, $this->formatCollection($sites));
    }
}

