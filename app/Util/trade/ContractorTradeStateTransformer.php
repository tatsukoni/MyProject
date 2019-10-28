<?php
namespace App\Transformers\Admin;

use App\Http\Controllers\Components\TradeState;
use App\Models\CurrentTrade;
use App\Models\Trade;
use App\Models\Wall;
use League\Fractal;

class ContractorTradeStateTransformer extends Fractal\TransformerAbstract
{
    public function transform(CurrentTrade $currentTrade)
    {
        $stateText = TradeState::STATE_TEXT_WORKER;
        $stateGroup = TradeState::getGroupState($currentTrade->state);
        $wallId = Wall::getPersonalWallId($currentTrade->job_id, $currentTrade->contractor_id);

        $proposedPrice = Trade::getCurrentProposedPrice($currentTrade->job_id, $currentTrade->contractor_id);
        $quantity = Trade::getCurrentProposedQuantity($currentTrade->job_id, $currentTrade->contractor_id);
        $currentPaymentPrice = $currentTrade->getCurrentPaymentPrice($proposedPrice, $quantity);

        $data = [
            'id' => $currentTrade->job_id,
            'client_id' => $currentTrade->job->outsourcer[0]->id,
            'client_name' => $currentTrade->job->outsourcer[0]->username,
            'worker_id' => optional($currentTrade->contractor)->id,
            'worker_name' => optional($currentTrade->contractor)->username,
            'is_deferrable' => $currentTrade->job->outsourcer[0]->deferrable,
            'deferring_fee_rate' => optional($currentTrade->job->outsourcer[0]->deferringFee)->fee,
            'wall_id' => $wallId,
            'state_group_id' => $stateGroup['state_group_id'],
            'state_group_txt' => $stateGroup['state_group_text'],
            'state_id' => $currentTrade->state,
            'state_txt' => empty($stateText[$currentTrade->state]) ?
                null :
                $stateText[$currentTrade->state],
            'expire_date' => $currentTrade->getExpireDate(),
            'current_proposed_price' => $proposedPrice,
            'current_quantity' => $quantity,
            'current_payment_price' => $currentPaymentPrice
        ];
        return $data;
    }
}
