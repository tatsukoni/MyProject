<?php

namespace Tests\Feature\Controllers\V1\Internal\Client;

use App\Http\Controllers\Components\TradeState;
use App\Models\Job;
use App\Models\JobRole;
use App\Models\Thread;
use App\Models\ThreadTrack;
use App\Models\Trade;
use App\Models\User;
use App\Models\Wall;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Controllers\V1\Internal\Pagination;
use Tests\TestCase;

class ThreadTracksControllerTest extends TestCase
{
    use DatabaseTransactions, Pagination;

    protected $url;

    protected function setUrl($user, $threadTrackId = null)
    {
        if (is_null($threadTrackId)) {
            $this->url = $this->internalDomain . '/api/v1/client/' . $user->id . '/thread_tracks';
        } else {
            $this->url = $this->internalDomain . '/api/v1/client/' . $user->id . '/thread_tracks/' . $threadTrackId;
        }
    }

    public function provideData()
    {
        return
        [
            // 全件検索
            '全件検索' => [true, 2, 1],
            // 絞り込み検索
            '絞り込み検索' => [false, 1, 0]
        ];
    }

    /**
     * @dataProvider provideData
     *
     * @param bool $allRecord
     */
    public function testIndex200(bool $allRecord, $expected, $key)
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $worker = factory(User::class)->states('worker')->create();

        $job1 = factory(Job::class)->states('project')->create();
        $job2 = factory(Job::class)->states('project')->create();

        factory(JobRole::class)->create([
            'job_id' => $job1->id,
            'user_id' => $client->id
        ]);
        factory(JobRole::class)->create([
            'job_id' => $job2->id,
            'user_id' => $client->id
        ]);

        $personalWall1 = factory(Wall::class)->states('personal')->create([
            'job_id' => $job1->id
        ]);
        $personalWall2 = factory(Wall::class)->states('personal')->create([
            'job_id' => $job2->id
        ]);

        $thread1 = factory(Thread::class)->create([
           'wall_id' => $personalWall1->id,
           'user_id' => $worker->id
        ]);
        $thread2 = factory(Thread::class)->create([
           'wall_id' => $personalWall2->id,
           'user_id' => $worker->id
        ]);

        $threadTrack1 = factory(ThreadTrack::class)->create([
           'foreign_key' => $thread1->id,
           'user_id' => $client->id,
           'wall_id' => $personalWall1->id,
           'modified' => '2017-08-31 11:56:00'
        ]);
        factory(ThreadTrack::class)->create([
           'foreign_key' => $thread2->id,
           'user_id' => $client->id,
           'wall_id' => $personalWall2->id,
           'modified' => '2017-08-31 11:56:00'
        ]);

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // Act
        $response = $allRecord ?
           $this->get($this->url, $this->headers) :
           $this->get($this->url . '?job_id=' . $job1->id, $this->headers);
        // assert
        $items = $response->decodeResponseJson()['data'];

        $this->assertEquals($expected, count($items));
        $this->assertEquals($threadTrack1->id, $items[$key]['id']);
        $response->assertStatus(200);
    }

    // 個別連絡ボード以外の未読が返ってこないことを確認
    public function testIndex200ReturnOnlyPersonalBoard()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();

        $job = factory(Job::class)->states('project')->create();

        factory(JobRole::class)->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);

        $otherWall = factory(Wall::class)->create();

        $otherThread = factory(Thread::class)->create([
            'wall_id' => $otherWall->id,
            'user_id' => $client->id
        ]);
        $threadTrack = factory(ThreadTrack::class)->create([
            'foreign_key' => $otherThread->id,
            'user_id' => $client->id,
            'wall_id' => $otherWall->id
        ]);

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // Act
        $response = $this->get($this->url, $this->headers);
        // Assert
        $this->assertJsonMissingId($response, $threadTrack->id);
    }

    public function testIndex200UnreadNotExist()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();

        $job = factory(Job::class)->states('project')->create();

        factory(JobRole::class)->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // Act
        $response = $this->get($this->url . '?job_id=' . $job->id, $this->headers);
        // Assert
        $response->assertStatus(200);
        $this->assertEmpty($response->decodeResponseJson()['data']);
    }

    /**
     * システムメッセージの未読が返ってこないことを確認
     */
    public function testIndex200ExcludeSysMsg()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $worker = factory(User::class)->states('worker')->create();

        $job = factory(Job::class)->states('project')->create();
        factory(JobRole::class)->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);

        $wall = factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id
        ]);
        $thread = factory(Thread::class)->create([
            'wall_id' => $wall->id,
            'user_id' => $client->id,
            'trade_id' => 12345,
        ]);
        $threadTrack = factory(ThreadTrack::class)->create([
            'foreign_key' => $thread->id,
            'user_id' => $client->id,
            'wall_id' => $wall->id
        ]);

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // Act
        $response = $this->get($this->url . '?exclude_sys_msg=1', $this->headers);

        // Assert
        $response->assertStatus(200);
        $this->assertJsonMissingId($response, $threadTrack->id);
    }

    /**
     * need_stateがtrue時に、取引ステータスの情報が返却されることを確認
     */
    public function testIndex200NeedState()
    {
        $client = factory(User::class)->states('client')->create();
        $worker = factory(User::class)->states('worker')->create();

        $job = factory(Job::class)->states('project')->create();
        factory(JobRole::class)->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);

        $wall = factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id
        ]);
        $thread = factory(Thread::class)->create([
            'wall_id' => $wall->id,
            'user_id' => $worker->id,
        ]);
        $threadTrack = factory(ThreadTrack::class)->create([
            'foreign_key' => $thread->id,
            'user_id' => $client->id,
            'wall_id' => $wall->id,
            'modified' => '2017-08-31 11:56:00'
        ]);

        $trade = factory(Trade::class)->states('proposal')->create([
            'job_id' => $job->id,
            'contractor_id' => $worker->id,
        ]);

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // Act
        $url = $this->url . '?job_id=' . $job->id . '&need_state=1';
        $response = $this->get($url, $this->headers);
        $responseData = $response->decodeResponseJson()['data'][0]['attributes'];

        // Assert
        $response->assertStatus(200);
        $this->assertSame(TradeState::GROUP_PROPOSAL, $responseData['state_group_id']);
        $this->assertSame($trade->state, $responseData['state']);
    }

    /**
     * @dataProvider provideIndex422Data
     * @param  array  $params
     * @param  array  $validationMessage
     */
    public function testIndex422(array $params, array $validationMessage)
    {
        $client = factory(User::class)->states('client')->create();

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // Act
        $url = $this->url . '?' . http_build_query($params);
        $response = $this->get($url, $this->headers);

        // Assert
        $response->assertStatus(422);
        $response->assertJson($validationMessage);
    }

    public function provideIndex422Data()
    {
        return [
            'exclude_sys_msgがフラグでない' => [
                ['exclude_sys_msg' => 12345],
                ['exclude_sys_msg' => ['いずれかを選択してください。']]
            ],
            'need_stateがフラグではない' => [
                ['need_state' => 12345],
                ['need_state' => ['いずれかを選択してください。']]
            ]
        ];
    }

    public function testIndex422JobId()
    {
        // Arrange
        // users
        $client = factory(User::class)->states('client')->create();
        $otherClient = factory(User::class)->states('client')->create();
        // jobs
        $job = factory(Job::class)->states('project')->create();
        // job_roles
        factory(JobRole::class)->create([
            'job_id' => $job->id,
            'user_id' => $otherClient->id
        ]);

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // Act & Assert
        // 指定したjob_idが存在しない
        $url = $this->url . '?job_id=a';
        $response = $this->get($url, $this->headers);
        $response->assertStatus(422);

        // 指定したjob_idが自分の仕事ではない
        $url = $this->url . '?job_id=' . $job->id;
        $response = $this->get($url, $this->headers);
        $response->assertStatus(422);
    }

    public function testDestroy204()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $threadTrack = factory(ThreadTrack::class)->create([
            'user_id' => $client->id
        ]);

        $this->setUrl($client, $threadTrack->id);
        $this->setAuthHeader($client);

        // Act
        $response = $this->delete($this->url, [], $this->headers);

        // Assert
        $response->assertStatus(204);
        $this->assertDatabaseMissing(
            'thread_tracks',
            [
                'id' => $threadTrack->id,
                'user_id' => $client->id
            ]
        );
    }

    public function testDestroy404()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $otherClient = factory(User::class)->states('client')->create();
        $threadTrack = factory(ThreadTrack::class)->create([
            'user_id' => $otherClient->id
        ]);

        $this->setUrl($client, $threadTrack->id);
        $this->setAuthHeader($client);

        // Act & Assert
        // 指定したthread_track_idが、指定したclient_idの未読でない
        $response = $this->delete($this->url, [], $this->headers);
        $response->assertStatus(404);

        // 指定したthread_track_idが存在しない
        $this->setUrl($client, 'a');
        $response = $this->delete($this->url, [], $this->headers);
        $response->assertStatus(404);
    }

    public function testPaginationParams()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $this->setUrl($client);

        // Act, Assert
        $this->assertPagination200($this->url, $client);
        $this->assertPagination422($this->url, $client);
    }
}
