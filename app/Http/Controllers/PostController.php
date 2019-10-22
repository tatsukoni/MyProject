<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Http\Requests\PostRequest;
use Exception;
use Illuminate\Support\Facades\DB;
use Log;

class PostController extends Controller
{
    //
    public function index()
    {
        return view('form');
    }

    public function store(PostRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $site = new Site();
                $site->site_name = $request->input('name');
                $site->classification = $request->input('kind');
                $site->save();

                $site->siteDetail()->create([
                    'link' => $request->input('link'),
                    'comment' => $request->input('comment')
                ]);
            });
            
            return redirect()->route('index');
        }catch(Exception $e) {
            Log::info($e);
        }
    }
}
