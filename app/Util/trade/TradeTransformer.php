<?php

namespace App\Transformers\Admin;

use App\Http\Controllers\Components\TradeState;
use App\Models\Bookmark;
use App\Models\CurrentTrade;
use App\Models\ThreadTrack;
use App\Models\Trade;
use App\Models\Wall;
use League\Fractal;

/**
 * Laravel Fractal
 * Presentation and transformation layer for complex data output.
 *
 * @ref https://github.com/spatie/laravel-fractal
 */
class TradeTransformer extends Fractal\TransformerAbstract
{
    public function transform(CurrentTrade $trades)
    {
        $groupState = TradeState::getGroupStateByTradeState($trades->state);
        $outsourcerId = $trades->job->jobRoles[0]->user_id;

        $jobId = $trades->job->id;
        $workerId = optional($trades->contractor)->id; // nullとして返却される可能性がある
        // $workerIdがnullとして返却された場合を考慮する
        if (is_null($workerId)) {
            $wallId = null;
            $currentUnitPrice = null;
            $currentQuantity = null;
            $currentPrice = null;
        } else {
            $wallId = Wall::getPersonalWallId($jobId, $workerId);
            $currentUnitPrice = Trade::getCurrentProposedPrice($jobId, $workerId);
            $currentQuantity = Trade::getCurrentProposedQuantity($jobId, $workerId);
            $currentPrice = Trade::getCurrentPaymentPriceForWorker($jobId, $workerId);
        }

        $unreadCount = ThreadTrack::getUnreadCountOfWalls($outsourcerId, [$wallId]);

        return [
            'id' => $trades->id,
            'job_id' => $jobId,
            'worker_id' => $workerId,
            'worker_name' => optional($trades->contractor)->username,
            'state_group_id' => $groupState['state_group_id'],
            'state_group_text' => $groupState['state_group_text'],
            'wall_id' => $wallId,
            'current_unit_price' => $currentUnitPrice,
            'current_quantity' => $currentQuantity,
            'current_price' => $currentPrice,
            'unread_count' => isset($unreadCount[$wallId]) ? (int)$unreadCount[$wallId] : 0,
            'pinned_count' => count(Bookmark::ofPinMessages($wallId, $outsourcerId)->get())
        ];
    }
}
