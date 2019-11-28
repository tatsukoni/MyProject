<?php

namespace App\Transformers\Client;

use App\Http\Controllers\Components\TradeState;
use App\Models\CurrentTrade;
use App\Models\Partner;
use App\Models\Rating;
use App\Models\RejectedReason;
use App\Models\Trade;
use App\Models\User;

/**
 * 「state_group_id=5（終了）」指定時に、WorkersControllerが使用するTransformer
 */
class WorkerOfClosedTransformer extends AbstractWorkerTransformer
{
    const STATE_GROUP_ID = TradeState::GROUP_CLOSED;

    const TRADE_CLOSED_DETAIL = [
        'closed' => 'お疲れさまでした',
        'rejectProposal' => '応募お断り',
        'cancelProposal' => '応募キャンセル',
        'autoTerminated' => '応募の返答期間終了',
        'terminatedProposal' => '取引中止依頼による',
        'terminatedDeliver' => '取引中止依頼による',
        'exception' => '該当なし'
    ];

    protected function getAdditionalParams(CurrentTrade $trade): array
    {
        $jobId = $trade->job_id;
        $worker = $trade->contractor;
        $outsourcer = $trade->job->outsourcer->first();

        // 最後に提出した応募情報
        $lastProposal = Trade::where('state', TradeState::STATE_PROPOSAL)
            ->where('job_id', $jobId)
            ->where('contractor_id', $worker->id)
            ->orderBy('id', 'DESC')
            ->first();
        
        // 最新の納品
        $lastDeliver = Trade::where('state', TradeState::STATE_DELIVERY)
            ->where('job_id', $jobId)
            ->where('contractor_id', $worker->id)
            ->orderBy('id', 'DESC')
            ->first();

        // 取引終了状況を取得する
        $tradeClosedState = $this->getTradeClosedState($trade, $lastProposal, $lastDeliver);

        // 現在の取引単価,数量
        $unitPrice = Trade::getCurrentProposedPrice($jobId, $worker->id);
        $quantity = $paymentPrice = null; // パターンによってはnullで返却する必要がある
        if ($tradeClosedState === 'closed' || $tradeClosedState === 'terminatedDeliver') {
            $quantity = Trade::getCurrentProposedQuantity($jobId, $worker->id);
            $paymentPrice = $unitPrice * $quantity;
            if ($trade->job->deferrable) {
                // 後払いの場合、後払い手数料を含める
                $deferringFee = $outsourcer->deferringFee->generateDeferringFee($paymentPrice);
                $paymentPrice = $paymentPrice + $deferringFee;
            }
        }
        
        // 作業時間
        $actualTime = $acceptedDatetime = null;
        if ($tradeClosedState === 'closed' || $tradeClosedState === 'terminatedDeliver') { // 時間返す場合のステータスのみ
            $actualTime = $lastDeliver->actualWorkedTimeUser;
        }

        // 評価情報
        $ratingInfo = Rating::getRatingInfo($trade, optional($outsourcer)->id, false);

        // 応募お断り理由を取得
        $rejectReasonTxt = $rejectDetailTxt = null;
        if ($tradeClosedState === 'rejectProposal') {
            $rejectReason = $this->getReasonTxt($lastProposal);
            $rejectReasonTxt = $rejectReason['reason'];
            $rejectDetailTxt = $rejectReason['detail'];
        } 

        // 該当ユーザーがパートナー申請可能かどうかを判断する
        $partnerCandidate = $this->isPartnerCandidate($worker, $outsourcer);

        return [
            'rating_by_worker_point' => $ratingInfo['rating_by_worker_point'],
            'rating_by_worker_msg' => $ratingInfo['rating_by_worker_msg'],
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'payment_price' => $paymentPrice,
            'actual_worked_time_id' => optional($actualTime)->actual_worked_time_id,
            'actual_minutes' => optional($actualTime)->actual_minutes,
            'finished_datetime' => $trade->modified->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'), // TODO: 修正する「取引が終了した日時にする」
            'reject_reason_id' => $lastProposal->reject_reason_id,
            'reject_reason_txt' => $rejectReasonTxt,
            'reject_reason_txt_detail' => $rejectDetailTxt,
            'partnerCandidate' => $partnerCandidate,
            'tradeClosedDetail' => self::TRADE_CLOSED_DETAIL($tradeClosedState)
        ];
    }

    protected function getStateGroupId(): int
    {
        return self::STATE_GROUP_ID;
    }

    /**
     * 応募お断り理由を返す
     */
    private function getReasonTxt($lastProposal): array
    {
        $reasons = ['reason' => null, 'detail' => null];

        // お断り理由がない場合
        if (is_null($lastProposal->reject_reason_id)) {
            return $reasons;
        }

        $rejectReason = RejectedReason::withTrashed()->findOrFail($lastProposal->reject_reason_id);
        $reasons['reason'] = $rejectReason->reason;

        // その他以外はコメントが無い
        if (! in_array($lastProposal->reject_reason_id, RejectedReason::REQUIRE_MESSAGE_IDS)) {
            return $reasons;
        }

        $reasons['detail'] = $lastProposal->rejected_reason_other;
        return $reasons;
    }

    /**
     * 取引終了ステータスの詳細を返す
     */
    private function getTradeClosedState($trade, $lastProposal, $lastDeliver = null): string
    {
        // 取引終了
        if ($trade->state === TradeState::STATE_CLOSED) {
            return 'closed';
        }

        // 取引中止
        if ($lastProposal->selected === TradeState::ACTION_REJECT_PROPOSAL) { // 応募お断り
            return 'rejectProposal';
        }
        if ($lastProposal->selected === TradeState::ACTION_CANCEL_PROPOSAL) { // 応募キャンセル
            return 'cancelProposal';
        }
        if ($lastProposal->selected === TradeState::ACTION_AUTO_TERMINATED) { // 応募の返答期間終了
            return 'autoTerminated';
        }
        if ($lastProposal->selected === TradeState::ACTION_ACCEPT_PROPOSAL) { // 発注後に取引中止終了
            return 'terminatedProposal';
        }
        if (! is_null($lastDeliver)) {
            return 'terminatedDeliver';
        }

        return 'exception'; // 該当なしの場合（例外）
    }

    /**
     * パートナー申請が可能かどうかを返す
     */
    private function isPartnerCandidate($worker, $outsourcer): bool
    {
        $PartnerCandidate = true;

        // 対象ワーカーからブロックされている場合
        $isBlocked = $worker->blockUsers()
            ->where('user_id', $outsourcer->id)
            ->exists();
        if ($isBlocked) {
            $PartnerCandidate = false;
        }

        // すでにパートナー申請済である場合
        $isPartner = $outsourcer->partnerUsers()
            ->wherePivotIn('state', [Partner::STATE_APPLIED, Partner::STATE_ACCEPTED])
            ->wherePivot('contractor_id', $worker->id)
            ->exists();
        if ($isPartner) {
            $PartnerCandidate = false;
        }

        // パートナー候補に該当しない場合
        $jobs = $outsourcer->getMethods()->getJobActiveJobRole($outsourcer->id);
        $isCandidate = User::OfPartnerCandidatesList($outsourcer->id, $jobs)
            ->where('users.id', $workerId)
            ->exists();
        if (! $isCandidate) {
            $PartnerCandidate = false;
        }

        return $PartnerCandidate;
    }
}
