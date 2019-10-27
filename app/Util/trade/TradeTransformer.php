<?php

namespace App\Transformers\Admin;

use App\Http\Controllers\Components\Admin\AdminTradeState;
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
    public function transform(CurrentTrade $currentTrades)
    {
        $trade = Trade::with(['job', 'thread', 'contractor'])
            ->findOrFail($currentTrades->id);

        $groupState = AdminTradeState::getGroupStateByAdminTradeState($trade->state);
        $outsourcerId = $trade->job->jobRoles[0]->user_id;
    
        $wallId = Wall::getPersonalWallId($trade->job->id, $trade->contractor_id);
        $proposedPrice = Trade::getCurrentProposedPrice($trade->job->id, $trade->contractor_id);
        $quantity = Trade::getCurrentProposedQuantity($trade->job->id, $trade->contractor_id);
        $currentPaymentPrice = $trade->getCurrentPaymentPrice($proposedPrice, $quantity);
        $unreadCount = ThreadTrack::getUnreadCountOfWalls($outsourcerId, [$wallId]);

        return [
            'id' => $trade->id,
            'job_id' => $trade->job->id,
            'worker_id' => optional($trade->contractor)->id,
            'worker_name' => optional($trade->contractor)->username,
            'state_group_id' => $groupState['state_group_id'],
            'state_group_text' => $groupState['state_group_text'],
            'wall_id' => $wallId,
            'current_proposed_price' => $proposedPrice,
            'current_quantity' => $quantity,
            'current_payment_price' => $currentPaymentPrice,
            'unread_count' => isset($unreadCount[$wallId]) ? (int)$unreadCount[$wallId] : 0,
            'pinned_count' => count(Bookmark::ofPinMessages($wallId, $outsourcerId)->get())
        ];
    }
}
