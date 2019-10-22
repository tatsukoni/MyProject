<?php
namespace App\Repositories\Slack;

use Illuminate\Notifications\Notifiable;

class SlackRepository implements SlackRepositoryInterface
{
    use Notifiable;

    protected $slack;

    public function routeNotificationForSlack()
    {
        return env('SLACK_WEBHOOK_URL');
    }
}
