<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\DataProvider\FavoriteRepositoryInterface;

class TestfavoriteController extends Controller
{
    protected $favorite;

    public function __construct(FavoriteRepositoryInterface $favorite)
    {
        $this->favorite = $favorite;
    }

    public function store(Request $request)
    {
        $siteId = $request->input('site_id');
        $this->favorite->switch($siteId);

        return response()->json(
            200,
            [
                'message' => 'success'
            ],
            [],
            JSON_PRETTY_PRINT
        );
    }
}
