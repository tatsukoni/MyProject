<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Closure;
use Log;
use App\Notifications\Slack;
use App\Repositories\Slack\SlackRepository AS SlackPepo;

class HeaderDumper
{
    protected $slackHock;

    public function __construct(SlackPepo $slackHock)
    {
        $this->slackHock = $slackHock;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request instanceof Request) {
            $this->slackHock->notify(new Slack(strval($request->headers)));
        }
        
        $response = $next($request);

        if ($response instanceof Response) {
            $this->slackHock->notify(new Slack(strval($response->headers)));
        }

        return $response;
    }
}
