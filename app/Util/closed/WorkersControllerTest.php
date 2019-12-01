<?php

namespace Tests\Feature\Controllers\V1\Internal\Client;

use App\Http\Controllers\Components\TradeState;
use App\Models\Bookmark;
use App\Models\Job;
use App\Models\JobRole;
use App\Models\ThreadTrack;
use App\Models\Trade;
use App\Models\User;
use App\Models\Wall;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Controllers\V1\Internal\Pagination;
use Tests\TestCase;

class WorkersControllerTest extends TestCase
{
    use DatabaseTransactions, Pagination;

    protected $url;

    private function setUrl($user, $job)
    {
        $this->url = $this->internalDomain . '/api/v1/client/' . $user->id
            . '/jobs/' . $job->id . '/workers';
    }

    public function testIndex200()
    {
        // Arrange
        // users
        $client = factory(User::class)->states('client')->create();
        $proposalWorker = factory(User::class)->states('worker')->create();
        $revisedWorker = factory(User::class)->states('worker')->create();
        $workWorker = factory(User::class)->states('worker')->create();
        $deliveryWorker = factory(User::class)->states('worker')->create();
        $finishWorker = factory(User::class)->states('worker')->create();
        $closedWorker = factory(User::class)->states('worker')->create();

        // jobs
        $job = factory(Job::class)->states('project')->create();
        factory(JobRole::class)->states('outsourcer')->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);
        $jobRoleProposal = factory(JobRole::class)->states('contractor')->create([
            'job_id' => $job->id,
            'user_id' => $proposalWorker->id
        ]);
        factory(JobRole::class)->states('contractor')->create([
            'job_id' => $job->id,
            'user_id' => $revisedWorker->id
        ]);
        factory(JobRole::class)->states('contractor')->create([
            'job_id' => $job->id,
            'user_id' => $workWorker->id
        ]);
        factory(JobRole::class)->states('contractor')->create([
            'job_id' => $job->id,
            'user_id' => $deliveryWorker->id
        ]);
        factory(JobRole::class)->states('contractor')->create([
            'job_id' => $job->id,
            'user_id' => $finishWorker->id
        ]);
        factory(JobRole::class)->states('contractor')->create([
            'job_id' => $job->id,
            'user_id' => $closedWorker->id
        ]);

        // trades
        $modified = Carbon::today();
        // 選考中
        factory(Trade::class)->states('proposal')->create([
            'job_id' => $job->id,
            'contractor_id' => $proposalWorker->id,
            'modified' => $modified
        ]);
        $wallProposal = factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id,
            'owner_id' => $proposalWorker->id
        ]);
        // 応募内容修正
        factory(Trade::class)->states('proposal_revised')->create([
            'job_id' => $job->id,
            'contractor_id' => $revisedWorker->id,
            'modified' => $modified->addSecond(1) // addSecond()は元のオブジェクトも書き換えるため、呼び出し毎に1ずつ増えていく
        ]);
        factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id,
            'owner_id' => $revisedWorker->id
        ]);
        // 作業中
        factory(Trade::class)->states('work')->create([
            'job_id' => $job->id,
            'contractor_id' => $workWorker->id,
            'modified' => $modified->addSecond(1)
        ]);
        factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id,
            'owner_id' => $workWorker->id
        ]);
        // 納品中
        factory(Trade::class)->states('delivery')->create([
            'job_id' => $job->id,
            'contractor_id' => $deliveryWorker->id,
            'modified' => $modified->addSecond(1)
        ]);
        factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id,
            'owner_id' => $deliveryWorker->id
        ]);
        // 評価中
        factory(Trade::class)->states('order')->create([
            'job_id' => $job->id,
            'contractor_id' => $finishWorker->id,
            'created' => $modified->addSecond(1),
            'modified' => $modified->addSecond(1)
        ]);
        factory(Trade::class)->states('delivery', 'delivery_accept')->create([
            'job_id' => $job->id,
            'contractor_id' => $finishWorker->id,
            'created' => $modified->addSecond(1),
            'modified' => $modified->addSecond(1)
        ]);
        factory(Trade::class)->states('finish')->create([
            'job_id' => $job->id,
            'contractor_id' => $finishWorker->id,
            'modified' => $modified->addSecond(1)
        ]);
        factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id,
            'owner_id' => $finishWorker->id
        ]);
        // 終了
        factory(Trade::class)->states('order')->create([
            'job_id' => $job->id,
            'contractor_id' => $closedWorker->id,
            'created' => $modified->addSecond(1),
            'modified' => $modified->addSecond(1)
        ]);
        factory(Trade::class)->states('delivery', 'delivery_accept')->create([
            'job_id' => $job->id,
            'contractor_id' => $closedWorker->id,
            'created' => $modified->addSecond(1),
            'modified' => $modified->addSecond(1)
        ]);
        factory(Trade::class)->states('closed')->create([
            'job_id' => $job->id,
            'contractor_id' => $closedWorker->id,
            'modified' => $modified->addSecond(1)
        ]);
        factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id,
            'owner_id' => $closedWorker->id
        ]);

        // bookmarks
        factory(Bookmark::class)->create([
            'user_id' => $client->id,
            'model' => 'JobRole',
            'foreign_key' => $jobRoleProposal->id
        ]);

        // thread_tracks
        factory(ThreadTrack::class)->create([
            'model' => 'Thread',
            'foreign_key' => -1,
            'user_id' => $client->id,
            'wall_id' => $wallProposal->id
        ]);

        $this->setUrl($client, $job);
        $this->setAuthHeader($client);

        // Act
        $url = $this->url . '?state_group_id=' . TradeState::GROUP_PROPOSAL;
        $response = $this->get($url, $this->headers);
        // Assert
        $response->assertStatus(200);

        $data = $response->decodeResponseJson()['data'];
        $ids = array_column($data, 'id');
        $this->assertSame([$proposalWorker->id, $revisedWorker->id], $ids);

        // Act
        $url = $this->url . '?state_group_id=' . TradeState::GROUP_WORK;
        $response = $this->get($url, $this->headers);
        // Assert
        $response->assertStatus(200);

        $data = $response->decodeResponseJson()['data'];
        $ids = array_column($data, 'id');
        $this->assertSame([$workWorker->id], $ids);

        // Act
        $url = $this->url . '?state_group_id=' . TradeState::GROUP_DELIVERY;
        $response = $this->get($url, $this->headers);
        // Assert
        $response->assertStatus(200);

        $data = $response->decodeResponseJson()['data'];
        $ids = array_column($data, 'id');
        $this->assertSame([$deliveryWorker->id], $ids);

        // Act
        $url = $this->url . '?state_group_id=' . TradeState::GROUP_FINISH;
        $response = $this->get($url, $this->headers);
        // Assert
        $response->assertStatus(200);

        $data = $response->decodeResponseJson()['data'];
        $ids = array_column($data, 'id');
        $this->assertSame([$finishWorker->id], $ids);

        // Act
        $url = $this->url . '?state_group_id=' . TradeState::GROUP_CLOSED;
        $response = $this->get($url, $this->headers);
        // Assert
        $response->assertStatus(200);

        $data = $response->decodeResponseJson()['data'];
        $ids = array_column($data, 'id');
        $this->assertSame([$closedWorker->id], $ids);
    }

    public function testIndex422()
    {
        $client = factory(User::class)->states('client')->create();
        $job = factory(Job::class)->states('project')->create();
        factory(JobRole::class)->states('outsourcer')->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);
        $this->setUrl($client, $job);
        $this->setAuthHeader($client);

        // 対応外のstate_group_id
        $url = $this->url . '?state_group_id=' . TradeState::GROUP_ACTIVE;
        $this->get($url, $this->headers)->assertStatus(422);
        // state_group_idの指定なし
        $this->get($this->url, $this->headers)->assertStatus(422);
    }

    public function testPaginationParams()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $job = factory(Job::class)->states('project')->create();
        factory(JobRole::class)->states('outsourcer')->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);
        $this->setUrl($client, $job);

        self::$limitMax = 1000;

        // Act, Assert
        $this->assertPagination200($this->url . '?state_group_id=' . TradeState::GROUP_PROPOSAL, $client);
        $this->assertPagination422($this->url . '?state_group_id=' . TradeState::GROUP_PROPOSAL, $client);
    }
}
