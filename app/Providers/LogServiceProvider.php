<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;
use Monolog\Handler\SlackHandler;
use Monolog\Logger;

class LogServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }

    private function getSlackHandler($logLevel, $channel)
    {
        $slackHandler = new SlackHandler(
            config('logging.channels.slack.token'),
            $channel,
            $username = 'tatsukoni',
            $useAttachment = true,
            $iconEmoji = null,
            $logLevel,
            $bubble = true,
            $useShortAttachment = true,
            $includeContextAndExtra = true,
            $excludeFields = array()
        );
        return $slackHandler;
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $monolog = \Log::getMonolog();

        $monolog->pushHandler($this->getSlackHandler(Logger::ERROR, config('logging.channels.slack.channel')));
    }
}
