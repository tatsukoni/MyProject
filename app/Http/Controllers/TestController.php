<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Notifications\Slack;
use App\Repositories\Slack\SlackRepository;
use App\Exports\Export;
use Illuminate\Http\Request;
use Log;

class TestController extends Controller
{
    public function index(Site $site, SlackRepository $slackHock)
    {
        $sites = $site->with('siteDetail')->get();
        return view('index', [ 'sites' => $sites ]);
    }

    public function export(Site $site)
    {
        $sites = $site->with('siteDetail')->get();
        $view = \view('export.sites', ['sites' => $sites]);
        return \Excel::download(new Export($view), 'sites.csv');
    }
}
