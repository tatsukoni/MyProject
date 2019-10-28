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
    public function transform(CurrentTrade $currentTrade)
    {
        $groupState = TradeState::getGroupState($currentTrade->state);
        $outsourcerId = $currentTrade->job->jobRoles[0]->user_id;
    
        $wallId = Wall::getPersonalWallId($currentTrade->job->id, $currentTrade->contractor_id);
        $proposedPrice = Trade::getCurrentProposedPrice($currentTrade->job->id, $currentTrade->contractor_id);
        $quantity = Trade::getCurrentProposedQuantity($currentTrade->job->id, $currentTrade->contractor_id);
        $currentPaymentPrice = $currentTrade->getCurrentPaymentPrice($proposedPrice, $quantity);
        $unreadCount = ThreadTrack::getUnreadCountOfWalls($outsourcerId, [$wallId]);

        return [
            'id' => $currentTrade->id,
            'job_id' => $currentTrade->job->id,
            'worker_id' => optional($currentTrade->contractor)->id,
            'worker_name' => optional($currentTrade->contractor)->username,
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
