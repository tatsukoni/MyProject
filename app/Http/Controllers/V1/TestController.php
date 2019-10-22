<?php

namespace App\Http\Controllers\V1;

use App\Transformers\TestTransformer;
use App\Http\Controllers\Controller;
use App\Http\RestResponse;
use App\Models\Site;
use App\Notifications\Slack;
use App\Repositories\Slack\SlackRepository AS SlackPepo;
use App\Exports\Export;
use Illuminate\Http\Request;
use Log;
use League\Fractal\Manager;

class TestController extends Controller
{
    use RestResponse;

    private $fractal;
    private $transformer;

    public function __construct(Manager $fractal, TestTransformer $testTransformer)
    {
        $this->fractal = $fractal;
        $this->transformer = $testTransformer;
    }

    public function index(Site $site, SlackPepo $slackHock)
    {
        $sites = $site->with('siteDetail')->get();
        return $this->sendSuccess(200, $this->formatCollection($sites));
    }

    public function show(Site $site)
    {
        // この形だと、取得したコレクションのデータが全て出力される（任意の形に整形することができない）
        $sites = $site->with('siteDetail')->get();
        return $sites->toArray();
    }

    public function export(Site $site)
    {
        $sites = $site->with('siteDetail')->get();
        $view = \view('export.sites', ['sites' => $sites]);
        return \Excel::download(new Export($view), 'sites.csv');
    }
}
