<?php

namespace Tests\Unit\Transformers\Client;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Http\Controllers\Components\TradeState;
use App\Models\ActualWorkedTimeUser;
use App\Models\CurrentTrade;
use App\Models\DeferringFee;
use App\Models\Job;
use App\Models\JobRole;
use App\Models\Partner;
use App\Models\Rating;
use App\Models\RejectedReason;
use App\Models\Thread;
use App\Models\Trade;
use App\Models\User;
use App\Models\UserWorkableTime;
use App\Models\Wall;
use App\Models\WorkableTime;
use App\Transformers\Client\WorkerOfClosedTransformer;
use Carbon\Carbon;

class WorkerOfClosedTransformerTest extends TestCase
{
    use DatabaseTransactions;

    protected $lastTradeCreated;

    public function setUp()
    {
        parent::setUp();

        $this->lastTradeCreated = Carbon::now('Asia/Tokyo');
    }

    public function createTradeData(bool $deferrable = false)
    {
        $worker = factory(User::class)->states('worker')->create();
        // 後払いを考慮する場合
        if ($deferrable) {
            $deferringFee = factory(DeferringFee::class)->create();
            $client = factory(User::class)->states('client', 'deferrable')->create([
                'deferring_fee_id' => $deferringFee->id,
            ]);
            $job = factory(Job::class)->states('project', 'deferrable')->create();
        } else {
            $deferringFee = null;
            $client = factory(User::class)->states('client')->create();
            $job = factory(Job::class)->states('project')->create();
        }

        factory(JobRole::class)->states('outsourcer')->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);
        $wall = factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id,
            'owner_id' => $worker->id
        ]);

        return compact('worker', 'deferringFee', 'client', 'job', 'wall');
    }

    public function provideTestGetAdditionalParamsClosed()
    {
        return
        [
            '後払いを考慮・ワーカーがクライアントを評価済み' => [
                true,
                'closed'
            ],
            '後払いを考慮しない・ワーカーがクライアントを評価済み' => [
                false,
                'closed'
            ],
            '後払いを考慮しない・ワーカーがクライアントを評価しないまま取引自動終了' => [
                false,
                'auto_closed'
            ]
        ];
    }

    /**
     * @dataProvider provideTestGetAdditionalParamsClosed
     *
     * @param bool $isDeferrable
     * @param string $closeState
     *
     * 取引終了（正常終了）のテスト
     */
    public function testGetAdditionalParamsClosed($isDeferrable, $closeState) // 取引正常終了
    {
        // Arrange
        $tradeData = $this->createTradeData($isDeferrable);

        // 取引終了時のレコード
        $closedTrade = factory(Trade::class)->states('closed')->create([
            'job_id' => $tradeData['job']->id,
            'contractor_id' => $tradeData['worker']->id,
            'created' => $this->lastTradeCreated->format('Y-m-d H:i:s')
        ]);

        // 応募レコード
        $proposalTrade = factory(Trade::class)->states('proposal')->create([
            'job_id' => $tradeData['job']->id,
            'proposed_price' => 100,
            'quantity' => 10,
            'contractor_id' => $tradeData['worker']->id,
            'selected' => TradeState::ACTION_ACCEPT_PROPOSAL,
            'created' => $this->lastTradeCreated->subHours(3)->format('Y-m-d H:i:s')
        ]);

        // 納品レコード
        $deliverTrade = factory(Trade::class)->states('delivery')->create([
            'job_id' => $tradeData['job']->id,
            'contractor_id' => $tradeData['worker']->id,
            'selected' => TradeState::ACTION_ACCEPT_DELIVERY,
            'created' => $this->lastTradeCreated->subHours(2)->format('Y-m-d H:i:s')
        ]);

        // 作業時間
        $actualWorkedTime = factory(ActualWorkedTimeUser::class)->create([
            'trade_id' => $deliverTrade->id,
        ]);

        $expectedRatingPoint = $expectedRatingMsg = null;
        // ワーカーがクライアントを評価しないまま取引自動終了
        if ($closeState === 'auto_closed') {
            $finishTrade = factory(Trade::class)->states('finish')->create([
                'job_id' => $tradeData['job']->id,
                'contractor_id' => $tradeData['worker']->id,
                'selected' => TradeState::ACTION_AUTO_FINISH,
                'created' => $this->lastTradeCreated->subHours(1)->format('Y-m-d H:i:s')
            ]);
        }

        // ワーカーがクライアントを評価済み
        if ($closeState === 'closed') {
            $finishTrade = factory(Trade::class)->states('finish_by_contractor')->create([
                'job_id' => $tradeData['job']->id,
                'contractor_id' => $tradeData['worker']->id,
                'selected' => TradeState::ACTION_ACCEPT_FINISH,
                'created' => $this->lastTradeCreated->subHours(1)->format('Y-m-d H:i:s')
            ]);
            // ワーカーからの評価
            $ratingByWorkerPoint = factory(Rating::class)->create([
                'job_id' => $tradeData['job']->id,
                'user_id' => $tradeData['client']->id,
                'respondent' => $tradeData['worker']->id,
                'point' => random_int(1, 5)
            ]);
            // 評価メッセージ
            $ratingByWorkerMsg = factory(Thread::class)->create([
                'wall_id' => $tradeData['wall']->id,
                'trade_id' => $finishTrade->id,
                'message' => str_random(30)
            ]);

            $expectedRatingPoint = $ratingByWorkerPoint->point;
            $expectedRatingMsg = $ratingByWorkerMsg->message;
        }

        // 後払いを考慮するかどうか
        $expectPaymentPrice = $proposalTrade->proposed_price * $proposalTrade->quantity;
        if ($isDeferrable) {
            $deferringFee = $tradeData['deferringFee']->fee;
            $expectPaymentPrice = (int)round($expectPaymentPrice * $deferringFee / 100) + $expectPaymentPrice;
        }

        // Act
        $currentTrade = CurrentTrade::with('contractor')
            ->where('job_id', $tradeData['job']->id)
            ->where('contractor_id', $tradeData['worker']->id)
            ->first();

        $transformer = new WorkerOfClosedTransformer();
        $method = $this->unprotect($transformer, 'getAdditionalParams');
        $result = $method->invoke($transformer, $currentTrade);

        // Assert
        $this->assertSame(
            [
                'rating_by_worker_point' => $expectedRatingPoint,
                'rating_by_worker_msg' => $expectedRatingMsg,
                'unit_price' => $proposalTrade->proposed_price,
                'quantity' => $proposalTrade->quantity,
                'payment_price' => $expectPaymentPrice,
                'actual_worked_time_id' => $actualWorkedTime->actual_worked_time_id,
                'actual_minutes' => $actualWorkedTime->actual_minutes,
                'closed_datetime' => $currentTrade->created->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'reject_reason_id' => null,
                'reject_reason_txt' => null,
                'reject_reason_txt_detail' => null,
                'partner_candidate' => true,
                'trade_closed_detail' => WorkerOfClosedTransformer::TRADE_CLOSED_DETAIL[$closeState]
            ],
            $result
        );
    }

    public function provideTestNotProposal()
    {
        return
        [
            '応募お断り' => [
                'proposal_reject'
            ],
            '応募キャンセル' => [
                'proposal_cancel'
            ],
            '応募の返答期間終了' => [
                'proposal_auto_terminated'
            ],
        ];
    }

    /**
     * @dataProvider provideTestNotProposal
     *
     * @param string $proposalState
     *
     * 応募の段階で取引中止が決まった場合
     */
    public function testNotProposal($proposalState)
    {
        // Arrange
        $tradeData = $this->createTradeData();

        $expectRejectReasonId = $expectRejectReasonTxt = $expectRejectDetailTxt = null;
        // 応募お断り時
        if ($proposalState === 'proposal_reject') {
            $rejectReason = factory(RejectedReason::class)->states('project')->create([
                'id' => RejectedReason::PROJECT_REASON_OTHER,
                'reason' => 'その他',
            ]);
            $expectRejectReasonId = $rejectReason->id;
            $expectRejectReasonTxt = $rejectReason->reason;
            $expectRejectDetailTxt = str_random(30);
        }

        // 応募時レコード
        $proposalTrade = factory(Trade::class)->states($proposalState)->create([
            'job_id' => $tradeData['job']->id,
            'proposed_price' => 100,
            'contractor_id' => $tradeData['worker']->id,
            'reject_reason_id' => $expectRejectReasonId,
            'rejected_reason_other' => $expectRejectDetailTxt,
            'created' => $this->lastTradeCreated->subHours(1)->format('Y-m-d H:i:s')
        ]);

        // 途中終了レコード
        $terminatedTrade = factory(Trade::class)->states('terminated')->create([
            'job_id' => $tradeData['job']->id,
            'contractor_id' => $tradeData['worker']->id,
            'created' => $this->lastTradeCreated->format('Y-m-d H:i:s')
        ]);

        // Act
        $currentTrade = CurrentTrade::with('contractor')
            ->where('job_id', $tradeData['job']->id)
            ->where('contractor_id', $tradeData['worker']->id)
            ->first();

        $transformer = new WorkerOfClosedTransformer();
        $method = $this->unprotect($transformer, 'getAdditionalParams');
        $result = $method->invoke($transformer, $currentTrade);

        // Assert
        $this->assertSame(
            [
                'rating_by_worker_point' => null,
                'rating_by_worker_msg' => null,
                'unit_price' => $proposalTrade->proposed_price,
                'quantity' => null,
                'payment_price' => null,
                'actual_worked_time_id' => null,
                'actual_minutes' => null,
                'closed_datetime' => $currentTrade->created->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'reject_reason_id' => $expectRejectReasonId,
                'reject_reason_txt' => $expectRejectReasonTxt,
                'reject_reason_txt_detail' => $expectRejectDetailTxt,
                'partner_candidate' => false,
                'trade_closed_detail' => WorkerOfClosedTransformer::TRADE_CLOSED_DETAIL[$proposalState]
            ],
            $result
        );
    }

    public function provideTestCancel()
    {
        return
        [
            '発注中状態から取引中止' => [
                'cancel_working'
            ],
            '納品済み状態から取引中止' => [
                'cancel_delivered'
            ]
        ];
    }

    /**
     * @dataProvider provideTestCancel
     *
     * @param string $cancelState
     *
     * 応募承認後に取引中止となった場合
     */
    public function testCancel($cancelState)
    {
        // Arrange
        $tradeData = $this->createTradeData();

        // 応募時のレコード
        $proposalTrade = factory(Trade::class)->states('proposal')->create([
            'job_id' => $tradeData['job']->id,
            'proposed_price' => 100,
            'quantity' => 10,
            'contractor_id' => $tradeData['worker']->id,
            'selected' => TradeState::ACTION_ACCEPT_PROPOSAL,
            'created' => $this->lastTradeCreated->subHours(2)->format('Y-m-d H:i:s')
        ]);

        $expectedPaymentPrice = '未納品';
        $expectedPartnerCandidate = false;
        $expectedActualWorkedTimeId = $expectedActualMinutes = null;
        // 納品済の時
        if ($cancelState === 'cancel_delivered') {
            $expectedPaymentPrice = $proposalTrade->proposed_price * $proposalTrade->quantity;
            $expectedPartnerCandidate = true;

            // 納品レコード
            $deliverTrade = factory(Trade::class)->states('delivery')->create([
                'job_id' => $tradeData['job']->id,
                'contractor_id' => $tradeData['worker']->id,
                'selected' => TradeState::ACTION_ACCEPT_DELIVERY,
                'created' => $this->lastTradeCreated->subHours(1)->format('Y-m-d H:i:s')
            ]);
    
            // 作業時間
            $actualWorkedTime = factory(ActualWorkedTimeUser::class)->create([
                'trade_id' => $deliverTrade->id,
            ]);
            $expectedActualWorkedTimeId = $actualWorkedTime->actual_worked_time_id;
            $expectedActualMinutes = $actualWorkedTime->actual_minutes;
        }

        // 途中終了レコード
        $terminatedTrade = factory(Trade::class)->states('terminated')->create([
            'job_id' => $tradeData['job']->id,
            'contractor_id' => $tradeData['worker']->id,
            'created' => $this->lastTradeCreated->format('Y-m-d H:i:s')
        ]);

        // Act
        $currentTrade = CurrentTrade::with('contractor')
            ->where('job_id', $tradeData['job']->id)
            ->where('contractor_id', $tradeData['worker']->id)
            ->first();

        $transformer = new WorkerOfClosedTransformer();
        $method = $this->unprotect($transformer, 'getAdditionalParams');
        $result = $method->invoke($transformer, $currentTrade);

        // Assert
        $this->assertSame(
            [
                'rating_by_worker_point' => null,
                'rating_by_worker_msg' => null,
                'unit_price' => $proposalTrade->proposed_price,
                'quantity' => $proposalTrade->quantity,
                'payment_price' => $expectedPaymentPrice,
                'actual_worked_time_id' => $expectedActualWorkedTimeId,
                'actual_minutes' => $expectedActualMinutes,
                'closed_datetime' => $currentTrade->created->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'reject_reason_id' => null,
                'reject_reason_txt' => null,
                'reject_reason_txt_detail' => null,
                'partner_candidate' => $expectedPartnerCandidate,
                'trade_closed_detail' => WorkerOfClosedTransformer::TRADE_CLOSED_DETAIL[$cancelState]
            ],
            $result
        );
    }

    // パートナー申請できるかどうかが適切に返却されることを確認
    public function testIsPartnerCandidate()
    {
        // Arrange
        $applyClient = factory(User::class)->states('client')->create([
            'limit_of_partner' => 30
        ]);
        $notApplyClient = factory(User::class)->states('client')->create([ // パートナー上限超え
            'limit_of_partner' => 30
        ]);
        $candidateWorker = factory(User::class)->states('worker')->create();
        $notCandidateWorker1 = factory(User::class)->states('worker')->create(); // パートナー申請中
        $notCandidateWorker2 = factory(User::class)->states('worker')->create(); // パートナー承認済み
        $notCandidateWorker3 = factory(User::class)->states('worker')->create(); // 検収OKのデータがない
        $job = factory(Job::class)->states('project')->create();

        factory(JobRole::class)->create([
            'job_id' => $job->id,
            'user_id' => $applyClient->id,
        ]);

        // パートナー上限超え
        for ($index = 1; $index <= 31; $index++) {
            factory(Partner::class)->create([
                'outsourcer_id' => $notApplyClient->id,
                'contractor_id' => $index,
                'state' => 'accepted',
            ]);
        }

        // パートナー申請中
        factory(Partner::class)->create([
            'outsourcer_id' => $applyClient->id,
            'contractor_id' => $notCandidateWorker1->id,
            'state' => 'applied',
        ]);
        // パートナー承認済み
        factory(Partner::class)->create([
            'outsourcer_id' => $applyClient->id,
            'contractor_id' => $notCandidateWorker2->id,
            'state' => 'accepted',
        ]);

        // プロジェクトの検収OKデータ作成
        factory(Trade::class)->states('delivery', 'delivery_accept')->create([
            'job_id' => $job->id,
            'contractor_id' => $candidateWorker->id,
        ]);
        factory(Trade::class)->states('delivery', 'delivery_accept')->create([
            'job_id' => $job->id,
            'contractor_id' => $notCandidateWorker1->id,
        ]);
        factory(Trade::class)->states('delivery', 'delivery_accept')->create([
            'job_id' => $job->id,
            'contractor_id' => $notCandidateWorker2->id,
        ]);

        // Act
        $transformer = new WorkerOfClosedTransformer();
        $method = $this->unprotect($transformer, 'isPartnerCandidate');
        $expectedTrueResult = $method->invoke($transformer, $candidateWorker, $applyClient);
        $expectedFalseResult1 = $method->invoke($transformer, $candidateWorker, $notApplyClient); // パートナー上限超え
        $expectedFalseResult2 = $method->invoke($transformer, $notCandidateWorker1, $applyClient); // パートナー申請中
        $expectedFalseResult3 = $method->invoke($transformer, $notCandidateWorker2, $applyClient); // パートナー承認済み
        $expectedFalseResult4 = $method->invoke($transformer, $notCandidateWorker3, $applyClient); // 検収OKのデータがない

        // Assert
        $this->assertTrue($expectedTrueResult);
        $this->assertFalse($expectedFalseResult1);
        $this->assertFalse($expectedFalseResult2);
        $this->assertFalse($expectedFalseResult3);
        $this->assertFalse($expectedFalseResult4);
    }
}
