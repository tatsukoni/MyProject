<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Exception;
use Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeleteController extends Controller
{
    public function index($id, Site $site)
    {
        $site = $site->with('siteDetail')->findOrFail($id);
        return view('delete', ['site' => $site]);
    }

    public function destroy(Request $request)
    {
        try{
            DB::transaction(function () use ($request) {
                $site = Site::findOrFail($request->id);
                $site->delete();
                $site->siteDetail()->delete();
            });
            return redirect()->route('index');
        } catch(Exception $e) {
            Log::info($e);
        }
    }
}
