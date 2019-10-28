<?php

namespace Tests\Unit\Transformers\Admin;

use App\Http\Controllers\Components\TradeState;
use App\Domain\Message\MessageService;
use App\Models\CurrentTrade;
use App\Models\DeferringFee;
use App\Models\Job;
use App\Models\JobRole;
use App\Models\Trade;
use App\Models\User;
use App\Models\Wall;
use App\Transformers\Admin\ContractorTradeStateTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContractorTradeStateTransformerTest extends TestCase
{
    use DatabaseTransactions;

    // 前払いの場合
    public function testResponse()
    {
        // Arrange
        factory(User::class)->states('admin')->create();
        $worker = factory(User::class)->states('worker')->create();
        $job = factory(Job::class)->create();
        $proposedTrade = factory(Trade::class)->states('order')->create([
            'job_id' => $job->id,
            'contractor_id' => $worker->id,
            'proposed_price' => 150,
            'quantity' => 10
        ]);
        $currentTrade = factory(Trade::class)->states('work')->create([
            'job_id' => $job->id,
            'contractor_id' => $worker->id,
        ]);
        $client = factory(User::class)->states('client', 'prepaid')->create();
        factory(JobRole::class)->states('contractor')->create([
            'job_id' => $job->id,
            'user_id' => $worker->id
        ]);
        factory(JobRole::class)->states('outsourcer')->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);
        $paymentPrice = $proposedTrade->proposed_price * $proposedTrade->quantity;

        $data = CurrentTrade::orderBy('id', 'desc')->first();

        $messageService = resolve(MessageService::class);
        $wall = $messageService->createBoard(
            Wall::TYPE_PERSONAL,
            $worker->id,
            $job->id
        );

        $result = (new ContractorTradeStateTransformer())->transform($data);

        $this->assertEquals(
            $result,
            [
                'id' => $currentTrade->job_id,
                'client_id' => $client->id,
                'client_name' => $client->username,
                'worker_id' => $worker->id,
                'worker_name' => $worker->username,
                'is_deferrable' => $client->deferrable,
                'deferring_fee_rate' => null,
                'wall_id' => $wall->id,
                'state_group_id' => TradeState::GROUP_WORK,
                'state_group_txt' => "作業中",
                'state_id' => $currentTrade->state,
                'state_txt' => '作業開始できます',
                'expire_date' => $currentTrade->getExpireDate(),
                'current_proposed_price' => $proposedTrade->proposed_price,
                'current_quantity' => $proposedTrade->quantity,
                'current_payment_price' => $paymentPrice
            ]
        );
    }

    public function testResponseDeferredPayment()
    {
        // Arrange
        factory(User::class)->states('admin')->create();
        $worker = factory(User::class)->states('worker')->create();
        $job = factory(Job::class)->create([
            'deferrable' => 1
        ]);
        $deferringFee = factory(DeferringFee::class)->create();
        $proposedTrade = factory(Trade::class)->states('order')->create([
            'job_id' => $job->id,
            'contractor_id' => $worker->id,
            'proposed_price' => 150,
            'quantity' => 10
        ]);
        $currentTrade = factory(Trade::class)->states('work')->create([
            'job_id' => $job->id,
            'contractor_id' => $worker->id
        ]);

        $client = factory(User::class)->states('client', 'deferrable')->create([
            'deferring_fee_id' => $deferringFee->id,
        ]);
        factory(JobRole::class)->states('contractor')->create([
            'job_id' => $job->id,
            'user_id' => $worker->id
        ]);
        factory(JobRole::class)->states('outsourcer')->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);
        $paymentPrice = $proposedTrade->proposed_price * $proposedTrade->quantity;

        $data = CurrentTrade::orderBy('id', 'desc')->first();

        $messageService = resolve(MessageService::class);
        $wall = $messageService->createBoard(
            Wall::TYPE_PERSONAL,
            $worker->id,
            $job->id
        );

        $result = (new ContractorTradeStateTransformer())->transform($data);

        $this->assertEquals(
            $result,
            [
                'id' => $currentTrade->job_id,
                'client_id' => $client->id,
                'client_name' => $client->username,
                'worker_id' => $worker->id,
                'worker_name' => $worker->username,
                'is_deferrable' => $client->deferrable,
                'deferring_fee_rate' => $deferringFee->fee,
                'wall_id' => $wall->id,
                'state_group_id' => TradeState::GROUP_WORK,
                'state_group_txt' => "作業中",
                'state_id' => $currentTrade->state,
                'state_txt' => '作業開始できます',
                'expire_date' => $currentTrade->getExpireDate(),
                'current_proposed_price' => $proposedTrade->proposed_price,
                'current_quantity' => $proposedTrade->quantity,
                'current_payment_price' => (int)round($paymentPrice * $deferringFee->fee / 100) + $paymentPrice
            ]
        );
    }
}
