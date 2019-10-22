<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Exception;
use Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PutController extends Controller
{
    public function index($id, Site $site)
    {
        $site = $site->with('siteDetail')->findOrFail($id);
        return view('update', ['site' => $site]);
    }

    public function update(Request $request)
    {
        try{
            DB::transaction(function () use ($request) {
                $site = Site::findOrFail($request->id);

                $site->update([
                    'site_name' => $request->name,
                    'classification' => $request->kind
                ]);

                $site->siteDetail()->update([
                    'link' => $request->link,
                    'comment' => $request->comment
                ]);
            });
            return redirect()->route('index');
        } catch(Exception $e) {
            Log::info($e);
        }
    }
}
