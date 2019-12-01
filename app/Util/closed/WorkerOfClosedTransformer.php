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
        'auto_closed' => '期限内に評価をいただけませんでした',
        'proposal_reject' => '以下の理由で応募をお断りしたため、取引が終了となりました。',
        'proposal_cancel' => '応募が取り消しになったため、取引が終了となりました。',
        'proposal_auto_terminated' => '応募の返答期間が終了したため、取引が終了となりました。',
        'cancel_working' => '取引中止が成立したため、取引が終了となりました。',
        'cancel_delivered' => '取引中止が成立したため、取引が終了となりました。',
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

        // 現在の取引単価,数量,予定支払い額
        $unitPrice = $quantity = $paymentPrice = null;
        if ($tradeClosedState !== 'exception') {
            $unitPrice = Trade::getCurrentProposedPrice($jobId, $worker->id);
        }
        if ($trade->state === TradeState::STATE_CLOSED
            || $tradeClosedState === 'cancel_working'
            || $tradeClosedState === 'cancel_delivered'
        ) {
            $quantity = Trade::getCurrentProposedQuantity($jobId, $worker->id);
        }

        if ($tradeClosedState === 'cancel_working') {
            $paymentPrice = '未納品';
        }
        if ($trade->state === TradeState::STATE_CLOSED || $tradeClosedState === 'cancel_delivered') {
            $paymentPrice = $unitPrice * $quantity;
            if ($trade->job->deferrable) {
                // 後払いの場合、後払い手数料を含める
                $deferringFee = $outsourcer->deferringFee->generateDeferringFee($paymentPrice);
                $paymentPrice = $paymentPrice + $deferringFee;
            }
        }
        
        // 作業時間
        $actualWorkedTimeId = $actualMinutes = null;
        if ($trade->state === TradeState::STATE_CLOSED || $tradeClosedState === 'cancel_delivered') {
            $actualWorkedTimeId = optional($lastDeliver->actualWorkedTimeUser)->actual_worked_time_id;
            $actualMinutes = optional($lastDeliver->actualWorkedTimeUser)->actual_minutes;
        }

        // 評価情報
        $ratingInfo = Rating::getRatingInfo($trade, optional($outsourcer)->id, false);

        // 応募お断り理由を取得
        $rejectReasonId = $rejectReasonTxt = $rejectDetailTxt = null;
        if ($tradeClosedState === 'proposal_reject') {
            $rejectReason = $this->getReasonTxt($lastProposal);
            $rejectReasonId = $lastProposal->reject_reason_id;
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
            'actual_worked_time_id' => $actualWorkedTimeId,
            'actual_minutes' => $actualMinutes,
            'closed_datetime' => $trade->created->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'),
            'reject_reason_id' => $rejectReasonId,
            'reject_reason_txt' => $rejectReasonTxt,
            'reject_reason_txt_detail' => $rejectDetailTxt,
            'partner_candidate' => $partnerCandidate,
            'trade_closed_detail' => self::TRADE_CLOSED_DETAIL[$tradeClosedState]
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
        if (is_null($lastProposal)) {
            return 'exception'; // 例外（応募時の取引レコードが存在しないのは例外的）
        }

        // 取引終了
        if ($trade->state === TradeState::STATE_CLOSED
            && ! is_null($lastDeliver)
        ) {
            $lastTrade = Trade::where('state', TradeState::STATE_FINISH)
                ->where('selected', TradeState::ACTION_AUTO_FINISH)
                ->where('job_id', $trade->job_id)
                ->where('contractor_id', $trade->contractor->id)
                ->orderBy('id', 'DESC')
                ->first();

            // $lastTradeがnullの場合は、ワーカーがクライアントを評価済み
            // $lastTradeがnullでない場合は、ワーカーがクライアントを評価しないまま取引自動終了
            return (is_null($lastTrade)) ? 'closed' : 'auto_closed';
        }

        // 発注前に取引中止
        $closedStates = [
            TradeState::ACTION_REJECT_PROPOSAL => 'proposal_reject',
            TradeState::ACTION_CANCEL_PROPOSAL => 'proposal_cancel',
            TradeState::ACTION_AUTO_TERMINATED => 'proposal_auto_terminated'
        ];
        foreach ($closedStates as $statesKey => $statesValue) {
            if ($lastProposal->selected === $statesKey) {
                return $statesValue;
            }
        }
        
        // 発注後に取引中止
        // $lastDeliverがnullの場合は、納品前に取引中止が成立
        // $lastDeliverがnullでない場合は、納品後に取引中止が成立
        if ($lastProposal->selected === TradeState::ACTION_ACCEPT_PROPOSAL) {
            return is_null($lastDeliver) ? 'cancel_working' : 'cancel_delivered';
        }

        return 'exception'; // 例外（いずれにも該当しない）
    }

    /**
     * パートナー申請が可能かどうかを返す
     */
    private function isPartnerCandidate($worker, $outsourcer): bool
    {
        $partnerCandidate = true;

        // パートナー上限を超えていないか確認
        $numberOfPartners = $outsourcer->partnerUsers()
            ->wherePivotIn('state', [Partner::STATE_APPLIED, Partner::STATE_ACCEPTED])
            ->count();
        if ($numberOfPartners >= $outsourcer->limit_of_partner) {
            return false;
        }

        // 対象ワーカーからブロックされている場合
        $isBlocked = $worker->blockUsers()
            ->where('user_id', $outsourcer->id)
            ->exists();
        if ($isBlocked) {
            $partnerCandidate = false;
        }

        // すでにパートナー申請済である場合
        $isPartner = $outsourcer->partnerUsers()
            ->wherePivotIn('state', [Partner::STATE_APPLIED, Partner::STATE_ACCEPTED])
            ->wherePivot('contractor_id', $worker->id)
            ->exists();
        if ($isPartner) {
            $partnerCandidate = false;
        }

        // パートナー候補に該当しない場合
        $jobs = $outsourcer->getMethods()->getJobActiveJobRole($outsourcer->id);
        $isCandidate = User::OfPartnerCandidatesList($outsourcer->id, $jobs)
            ->where('users.id', $worker->id)
            ->exists();
        if (! $isCandidate) {
            $partnerCandidate = false;
        }

        return $partnerCandidate;
    }
}
