<?php

namespace App\Transformers\Client;

use App\Domain\User\Thumbnail;
use App\Http\Controllers\Components\TradeState;
use App\Models\CurrentTrade;
use App\Models\Job;
use App\Models\ThreadTrack;
use App\Models\User;
use League\Fractal;

/**
 * Laravel Fractal
 * Presentation and transformation layer for complex data output.
 *
 * @ref https://github.com/spatie/laravel-fractal
 */
class ThreadTrackTransformer extends Fractal\TransformerAbstract
{
    private $includesTime = false;
    private $needState = false;

    public function setIncludesTime($includesTime)
    {
        $this->includesTime = $includesTime;
    }

    public function setNeedState($needState)
    {
        $this->needState = $needState;
    }

    /**
     * @param ThreadTrack $threadTrack
     * @return array
     */
    public function transform(ThreadTrack $threadTrack)
    {
        $clientId = Job::find($threadTrack->job_id)->outsourcer[0]->id;
        $workerId = $this->getWorkerId($threadTrack, $clientId);
        $worker = User::find($workerId);

        // 管理画面で使う場合のみ時間を含める
        if ($this->includesTime) {
            $format = 'Y/m/d H:i:s';
        } else {
            $format = 'Y/m/d H:i';
        }

        $state = $stateGroupId = null;
        // $stateと$stateGroupIdを含めたい場合
        // $currentTradeがnullかどうか判定する必要がありそう（下記だと、$currentTradeがnullだがneed_stateで1を指定された場合にエラーが返ってきてしまう
        // $currentTradeを条件分岐より先に記述して、nullでないことを条件判断に追加すると良い
        if ($this->needState) {
            $currentTrade = CurrentTrade::where('job_id', $threadTrack->job_id)
                ->where('contractor_id', $workerId)->first();
            $state = $currentTrade->state;
            $stateGroup = TradeState::getGroupState($currentTrade->state, false);
            $stateGroupId = $stateGroup['state_group_id'];
        }

        return [
            'id' => (int) $threadTrack->thread_track_id,
            'job_id' => $threadTrack->job_id,
            'job_name' => $threadTrack->name,
            'message' => $this->getMessage($threadTrack, $clientId),
            'worker_id' => is_null($worker) ? null : (int) $workerId,
            'worker_name' => is_null($worker) ?
                User::RESIGNED_USER_NAME:
                $worker->username,
            'thumbnail_url' => is_null($worker) ?
                (new Thumbnail())->generateNoImageUrl() :
                $worker->thumbnailUrl,
            'modified' => $threadTrack->modified->setTimezone('Asia/Tokyo')->format($format),
            'state_group_id' => $stateGroupId,
            'state' => $state
        ];
    }

    /**
     * クライアントユーザIDとスレッド投稿ユーザIDから、
     * threads.messageとcomments.commentのどちらの未読か判断し、メッセージを返却
     * 評価コメントの場合は、メッセージを隠す
     *
     * @param  ThreadTrack $threadTrack
     * @param  integer $userId client user Id
     * @return string
     */
    private function getMessage($threadTrack, $userId)
    {
        $ratingState = [
            TradeState::STATE_FINISH,
            TradeState::STATE_FINISH_BY_CONTRACTOR
        ];
        if (in_array($threadTrack->trade_state, $ratingState)) {
            // 評価コメントの場合、メッセージを隠す
            $message = '評価しました';
        } elseif ($userId == $threadTrack->thread_user_id) {
            $message = $threadTrack->comment;
        } else {
            $message = $threadTrack->message;
        }

        return $message;
    }

    /**
     * クライアントIDとスレッド投稿ユーザIDから、
     * threads.user_idとcomments.user_idのどちらがワーカーか判断し、user_idを返却
     *
     * @param  ThreadTrack $threadTrack
     * @param  integer $userId client user Id
     * @return integer
     */
    private function getWorkerId($threadTrack, $userId)
    {
        if ($userId == $threadTrack->thread_user_id) {
            $workerId = $threadTrack->comment_user_id;
        } else {
            $workerId = $threadTrack->thread_user_id;
        }

        return $workerId;
    }
}
