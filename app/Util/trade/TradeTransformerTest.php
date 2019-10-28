<?php

namespace Tests\Unit\Transformers;

use App\Http\Controllers\Components\TradeState;
use App\Transformers\Admin\TradeTransformer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

use App\Models\Bookmark;
use App\Models\CurrentTrade;
use App\Models\DeferringFee;
use App\Models\Job;
use App\Models\JobRole;
use App\Models\Trade;
use App\Models\Thread;
use App\Models\ThreadTrack;
use App\Models\User;
use App\Models\Wall;

class TradeTransformerTest extends TestCase
{
    use DatabaseTransactions;

    public function getRecord()
    {
        return CurrentTrade::with([
            'job',
            'contractor',
            'thread',
            'job.jobRoles' => function ($query) {
                $query->where('role_id', JobRole::OUTSOURCER);
            }
        ])->first();
    }

    public function createRecordData($deferrable = false)
    {
        $admin = factory(User::class)->states('admin')->create();
        $worker = factory(User::class)->states('worker')->create();
    
        if ($deferrable) { // 後払いの場合
            $deferringFee = factory(DeferringFee::class)->create();
            $client = factory(User::class)->states('client', 'deferrable')->create([
                'deferring_fee_id' => $deferringFee->id,
            ]);
            $job = factory(Job::class)->states('deferrable')->create();
        } else {
            $deferringFee = null;
            $client = factory(User::class)->states('client', 'prepaid')->create();
            $job = factory(Job::class)->create();
        }
    
        $jobRole = factory(JobRole::class)->states('outsourcer')->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);
        $wall = factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id,
            'owner_id' => $worker->id
        ]);

        return compact('worker', 'client', 'deferringFee', 'job', 'wall');
    }

    // 取引ステータスが「選考」の場合
    public function provideResponseProposal()
    {
        return
        [
            '取引ステータスが「選考」' => [
                TradeState::STATE_PROPOSAL,
                TradeState::GROUP_PROPOSAL,
                '選考中'
            ],
            'カウント対象のデータがある場合' => [
                TradeState::STATE_PROPOSAL,
                TradeState::GROUP_PROPOSAL,
                '選考中',
                true
            ],
        ];
    }

    /**
     * @dataProvider provideResponseProposal
     * @param $state
     * @param $stateGroupId
     * @param $stateGroupText
     * @param false $count
     */
    public function testResponseProposal(
        $state,
        $stateGroupId,
        $stateGroupText,
        $count = false
    ) {
        // Arrange
        $recordData = $this->createRecordData();
        $trade = factory(Trade::class)->create([
            'job_id' => $recordData['job']->id,
            'contractor_id' => $recordData['worker']->id,
            'state' => $state,
            'proposed_price' => 10,
            'created' => Carbon::now('Asia/Tokyo')->format('Y-m-d H:i:s')
        ]);
        $thread = factory(Thread::class)->create([
            'user_id' => $recordData['client']->id,
            'trade_id' => $trade->id,
            'wall_id' => $recordData['wall']->id
        ]);
        $threadTrack = factory(ThreadTrack::class)->create([
            'user_id' => $count ? $recordData['client']->id : $recordData['worker']->id,
            'foreign_key' => $thread->id,
            'wall_id' => $recordData['wall']->id
        ]);
        $bookMark = factory(Bookmark::class)->states('thread')->create([
            'user_id' => $count ? $recordData['client']->id : $recordData['worker']->id,
            'foreign_key' => $thread->id,
        ]);

        $data = $this->getRecord();

        // Act
        $result = (new TradeTransformer())->transform($data);

        // Assert
        $this->assertEquals(
            [
                'id' => $trade->id,
                'job_id' => $recordData['job']->id,
                'worker_id' => $recordData['worker']->id,
                'worker_name' => $recordData['worker']->username,
                'state_group_id' => $stateGroupId,
                'state_group_text' => $stateGroupText,
                'wall_id' => $recordData['wall']->id,
                'current_proposed_price' => $trade->proposed_price,
                'current_quantity' => 0,
                'current_payment_price' => 0,
                'unread_count' => $count ? 1 : 0,
                'pinned_count' => $count ? 1 : 0
            ],
            $result
        );
    }

    // 「応募OK」以降の取引ステータスの場合
    public function provideResponseAfterProposal()
    {
        return
        [
            '取引ステータスが「再発注の検討中」' => [
                TradeState::STATE_RE_PROPOSAL,
                TradeState::GROUP_REPROPOSAL,
                '継続発注待ち',
                false
            ],
            '取引ステータスが「作業」' => [
                TradeState::STATE_WORK,
                TradeState::GROUP_WORK,
                '作業中',
                false
            ],
            '取引ステータスが「単価変更交渉」' => [
                TradeState::STATE_NEGOTIATION_BY_CONTRACTOR,
                TradeState::GROUP_NEGOTIATION,
                '単価変更依頼中',
                false,
                TradeState::ACTION_NEGOTIATE
            ],
            '取引ステータスが「納品数変更交渉」' => [
                TradeState::STATE_QUANTITY_BY_OUTSOURCER,
                TradeState::GROUP_QUANTITY,
                '数量変更依頼中',
                false,
                TradeState::ACTION_QUANTITY
            ],
            '取引ステータスが「取引中止要請」' => [
                TradeState::STATE_CANCEL_BY_CONTRACTOR,
                TradeState::GROUP_CANCEL,
                '取引中止依頼中',
                false,
                TradeState::ACTION_CANCEL
            ],
            '取引ステータスが「納品」' => [
                TradeState::STATE_DELIVERY,
                TradeState::GROUP_DELIVERY,
                '納品中',
                false
            ],
            '取引ステータスが「評価」' => [
                TradeState::STATE_FINISH,
                TradeState::GROUP_FINISH,
                '評価中',
                false
            ],
            '取引ステータスが「取引完結（正常終了）」' => [
                TradeState::STATE_CLOSED,
                TradeState::GROUP_CLOSED,
                '取引終了',
                true
            ],
            '取引ステータスが「取引の終了（受注を断った場合など）」' => [
                TradeState::STATE_TERMINATED,
                TradeState::GROUP_TERMINATED,
                '取引中止',
                true
            ],
        ];
    }

    /**
     * @dataProvider provideResponseAfterProposal
     * @param $state
     * @param $stateGroupId
     * @param $stateGroupText
     * @param false $stateFinish
     */
    public function testResponseAfterProposal(
        $state,
        $stateGroupId,
        $stateGroupText,
        $count,
        $stateFinish = false
    ) {
        // Arrange
        $recordData = $this->createRecordData();
        $proposedTrade = factory(Trade::class)->states('order')->create([
            'job_id' => $recordData['job']->id,
            'contractor_id' => $recordData['worker']->id,
            'proposed_price' => 100,
            'quantity' => 10,
            'created' => Carbon::now('Asia/Tokyo')->format('Y-m-d H:i:s')
        ]);
        $trade = factory(Trade::class)->create([
            'job_id' => $recordData['job']->id,
            'contractor_id' => $recordData['worker']->id,
            'state' => $state,
            'created' => Carbon::now('Asia/Tokyo')->addSeconds(2)->format('Y-m-d H:i:s')
        ]);
        // 取引のステータスが"終了"以外の場合はthreadを作成する
        if (! $stateFinish) {
            $thread = factory(Thread::class)->create([
                'user_id' => $recordData['client']->id,
                'trade_id' => $trade->id,
                'wall_id' => $recordData['wall']->id
            ]);
            $threadTrack = factory(ThreadTrack::class)->create([
                'user_id' => $recordData['worker']->id,
                'foreign_key' => $thread->id,
                'wall_id' => $recordData['wall']->id
            ]);
            $bookMark = factory(Bookmark::class)->states('thread')->create([
                'user_id' => $recordData['worker']->id,
                'foreign_key' => $thread->id,
            ]);
        }

        $paymentPrice = $proposedTrade->proposed_price * $proposedTrade->quantity;
        $data = $this->getRecord();

        // Act
        $result = (new TradeTransformer())->transform($data);

        // Assert
        $this->assertEquals(
            [
                'id' => $trade->id,
                'job_id' => $recordData['job']->id,
                'worker_id' => $recordData['worker']->id,
                'worker_name' => $recordData['worker']->username,
                'state_group_id' => $stateGroupId,
                'state_group_text' => $stateGroupText,
                'wall_id' => $recordData['wall']->id,
                'current_proposed_price' => $proposedTrade->proposed_price,
                'current_quantity' => $proposedTrade->quantity,
                'current_payment_price' => $paymentPrice,
                'unread_count' => 0,
                'pinned_count' => 0
            ],
            $result
        );
    }

    // 後払いの場合
    public function testResponseDeferredPayment()
    {
        // Arrange
        $deferrable = true;
        $recordData = $this->createRecordData($deferrable);
        $proposedTrade = factory(Trade::class)->states('order')->create([
            'job_id' => $recordData['job']->id,
            'contractor_id' => $recordData['worker']->id,
            'proposed_price' => 100,
            'quantity' => 10,
            'created' => Carbon::now('Asia/Tokyo')->format('Y-m-d H:i:s')
        ]);
        $trade = factory(Trade::class)->states('work')->create([
            'job_id' => $recordData['job']->id,
            'contractor_id' => $recordData['worker']->id,
            'created' => Carbon::now('Asia/Tokyo')->addSeconds(2)->format('Y-m-d H:i:s')
        ]);
        $thread = factory(Thread::class)->create([
            'user_id' => $recordData['client']->id,
            'trade_id' => $trade->id,
            'wall_id' => $recordData['wall']->id
        ]);
        $threadTrack = factory(ThreadTrack::class)->create([
            'user_id' => $recordData['worker']->id,
            'foreign_key' => $thread->id,
            'wall_id' => $recordData['wall']->id
        ]);
        $bookMark = factory(Bookmark::class)->states('thread')->create([
            'user_id' => $recordData['worker']->id,
            'foreign_key' => $thread->id,
        ]);

        $paymentPrice = $proposedTrade->proposed_price * $proposedTrade->quantity;
        $deferringFee = $recordData['deferringFee']->fee;
        $data = $this->getRecord();

        // Act
        $result = (new TradeTransformer())->transform($data);

        // Assert
        $this->assertEquals(
            [
                'id' => $trade->id,
                'job_id' => $recordData['job']->id,
                'worker_id' => $recordData['worker']->id,
                'worker_name' => $recordData['worker']->username,
                'state_group_id' => TradeState::GROUP_WORK,
                'state_group_text' => '作業中',
                'wall_id' => $recordData['wall']->id,
                'current_proposed_price' => $proposedTrade->proposed_price,
                'current_quantity' => $proposedTrade->quantity,
                'current_payment_price' => (int)round($paymentPrice * $deferringFee / 100) + $paymentPrice,
                'unread_count' => 0,
                'pinned_count' => 0
            ],
            $result
        );
    }
}
