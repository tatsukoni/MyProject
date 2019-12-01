<?php

namespace Tests\Unit\Transformers\Client;

use App\Domain\User\Thumbnail;
use App\Http\Controllers\Components\TradeState;
use App\Models\Comment;
use App\Models\Job;
use App\Models\JobRole;
use App\Models\Thread;
use App\Models\ThreadTrack;
use App\Models\Trade;
use App\Models\User;
use App\Models\Wall;
use App\Transformers\Client\ThreadTrackTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ThreadTrackTransformerTest extends TestCase
{
    use DatabaseTransactions;

    public function testIndexTransformerUnreadThread()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $worker = factory(User::class)->states('worker')->create();

        $job = factory(Job::class)->states('project')->create();

        factory(JobRole::class)->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);

        $personalWall = factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id
        ]);

        $thread = factory(Thread::class)->create([
            'wall_id' => $personalWall->id,
            'user_id' => $worker->id,
        ]);

        factory(Comment::class)->create([
            'thread_id' => $thread->id,
        ]);

        $threadTrack = factory(ThreadTrack::class)->create([
            'foreign_key' => $thread->id,
            'user_id' => $client->id,
            'wall_id' => $personalWall->id,
            'modified' => '2017-08-31 11:56:00'
        ]);

        $threadTrackData = ThreadTrack::ofPersonalWall($client->id)->first();

        // Act
        $result = (new ThreadTrackTransformer())->transform($threadTrackData);

        // Assert
        $this->assertEquals(
            [
                'id' => $threadTrack->id,
                'job_id' => $job->id,
                'job_name' => $job->name,
                'message' => $thread->message,
                'worker_id' => $worker->id,
                'worker_name' => $worker->username,
                'thumbnail_url' => $worker->thumbnail_url,
                'modified' => $threadTrack->modified
                    ->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'state_group_id' => null,
                'state' => null
            ],
            $result
        );
    }

    public function testIndexTransformerUnreadThreadIncludesTime()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $worker = factory(User::class)->states('worker')->create();

        $job = factory(Job::class)->states('project')->create();

        factory(JobRole::class)->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);

        $personalWall = factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id
        ]);

        $thread = factory(Thread::class)->create([
            'wall_id' => $personalWall->id,
            'user_id' => $worker->id,
        ]);

        factory(Comment::class)->create([
            'thread_id' => $thread->id,
        ]);

        $threadTrack = factory(ThreadTrack::class)->create([
            'foreign_key' => $thread->id,
            'user_id' => $client->id,
            'wall_id' => $personalWall->id,
            'modified' => '2017-08-31 11:56:00'
        ]);

        $threadTrackData = ThreadTrack::ofPersonalWall($client->id)->first();

        $transformer = new ThreadTrackTransformer();
        $transformer->setIncludesTime(true);

        // Act
        $result = $transformer->transform($threadTrackData);

        // Assert
        $this->assertEquals(
            [
                'id' => $threadTrack->id,
                'job_id' => $job->id,
                'job_name' => $job->name,
                'message' => $thread->message,
                'worker_id' => $worker->id,
                'worker_name' => $worker->username,
                'thumbnail_url' => $worker->thumbnail_url,
                'modified' => $threadTrack->modified
                    ->setTimezone('Asia/Tokyo')->format('Y/m/d H:i:s'),
                'state_group_id' => null,
                'state' => null
            ],
            $result
        );
    }

    public function testIndexTransformerUnreadThreadNeedState()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $worker = factory(User::class)->states('worker')->create();

        $job = factory(Job::class)->states('project')->create();

        factory(JobRole::class)->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);

        $personalWall = factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id
        ]);

        $thread = factory(Thread::class)->create([
            'wall_id' => $personalWall->id,
            'user_id' => $worker->id,
        ]);

        factory(Comment::class)->create([
            'thread_id' => $thread->id,
        ]);

        $threadTrack = factory(ThreadTrack::class)->create([
            'foreign_key' => $thread->id,
            'user_id' => $client->id,
            'wall_id' => $personalWall->id,
            'modified' => '2017-08-31 11:56:00'
        ]);

        $threadTrackData = ThreadTrack::ofPersonalWall($client->id)->first();

        $trade = factory(Trade::class)->states('proposal')->create([
            'job_id' => $job->id,
            'contractor_id' => $worker->id,
        ]);

        $transformer = new ThreadTrackTransformer();
        $transformer->setNeedState(true);

        // Act
        $result = $transformer->transform($threadTrackData);

        // Assert
        $this->assertEquals(
            [
                'id' => $threadTrack->id,
                'job_id' => $job->id,
                'job_name' => $job->name,
                'message' => $thread->message,
                'worker_id' => $worker->id,
                'worker_name' => $worker->username,
                'thumbnail_url' => $worker->thumbnail_url,
                'modified' => $threadTrack->modified
                    ->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'state_group_id' => TradeState::GROUP_PROPOSAL,
                'state' => $trade->state
            ],
            $result
        );
    }

    public function testIndexTransformerUnreadComment()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $worker = factory(User::class)->states('worker')->create();

        $job = factory(Job::class)->states('project')->create();

        factory(JobRole::class)->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);

        $personalWall = factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id
        ]);

        $thread = factory(Thread::class)->create([
            'wall_id' => $personalWall->id,
            'user_id' => $client->id,
        ]);

        $comment = factory(Comment::class)->create([
            'thread_id' => $thread->id,
            'user_id' => $worker->id
        ]);

        $threadTrack = factory(ThreadTrack::class)->create([
            'foreign_key' => $thread->id,
            'user_id' => $client->id,
            'wall_id' => $personalWall->id,
        ]);

        $threadTrackData = ThreadTrack::ofPersonalWall($client->id)->first();

        // Act
        $result = (new ThreadTrackTransformer())->transform($threadTrackData);

        // Assert
        $this->assertEquals(
            [
                'id' => $threadTrack->id,
                'job_id' => $job->id,
                'job_name' => $job->name,
                'message' => $comment->comment,
                'worker_id' => $worker->id,
                'worker_name' => $worker->username,
                'thumbnail_url' => $worker->thumbnail_url,
                'modified' => $threadTrack->modified
                    ->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'state_group_id' => null,
                'state' => null
            ],
            $result
        );
    }

    public function testIndexTransformerUnreadEvaluationMessage()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $worker = factory(User::class)->states('worker')->create();

        $job = factory(Job::class)->states('project')->create();

        factory(JobRole::class)->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);

        $personalWall = factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id
        ]);

        $ratingTrade = factory(Trade::class)->states('finish_by_contractor')->create();

        $thread = factory(Thread::class)->create([
            'wall_id' => $personalWall->id,
            'user_id' => $worker->id,
            'trade_id' => $ratingTrade->id
        ]);

        $threadTrack = factory(ThreadTrack::class)->create([
            'foreign_key' => $thread->id,
            'user_id' => $client->id,
            'wall_id' => $personalWall->id,
        ]);

        $threadTrackData = ThreadTrack::ofPersonalWall($client->id)->first();

        // Act
        $result = (new ThreadTrackTransformer())->transform($threadTrackData);

        // Assert
        $this->assertEquals(
            [
                'id' => $threadTrack->id,
                'job_id' => $job->id,
                'job_name' => $job->name,
                'message' => '評価しました',
                'worker_id' => $worker->id,
                'worker_name' => $worker->username,
                'thumbnail_url' => $worker->thumbnail_url,
                'modified' => $threadTrack->modified
                    ->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'state_group_id' => null,
                'state' => null
            ],
            $result
        );
    }

    public function provideDataResignedWorker()
    {
        return
        [
            // 退会済みワーカーが削除されていない
            [false],
            // 退会済みワーカーが削除されている
            [true]
        ];
    }

    /**
      * @dataProvider provideDataResignedWorker
      *
      * @param array $expected
      * @param array $newValue
      */
    public function testIndexTransformerResignedWorker(bool $deleted)
    {
        // Arrange

        $client = factory(User::class)->states('client')->create();
        $resignedWorker = factory(User::class)->states('worker', 'resigned')->create();
        $resignedWorkerId = $resignedWorker->id;
        if ($deleted) {
            $resignedWorker->delete();
            $resignedWorkerId = null;
        }

        $job = factory(Job::class)->states('project')->create();

        factory(JobRole::class)->create([
            'job_id' => $job->id,
            'user_id' => $client->id
        ]);

        $personalWall = factory(Wall::class)->states('personal')->create([
            'job_id' => $job->id
        ]);

        $thread = factory(Thread::class)->create([
            'wall_id' => $personalWall->id,
            'user_id' => $resignedWorker->id,
        ]);

        $threadTrack = factory(ThreadTrack::class)->create([
            'foreign_key' => $thread->id,
            'user_id' => $client->id,
            'wall_id' => $personalWall->id,
            'modified' => '2017-08-31 11:56:00'
        ]);

        $noImageUrl = (new Thumbnail())->generateNoImageUrl();

        $threadTrackData = ThreadTrack::ofPersonalWall($client->id)->first();

        // Act
        $result = (new ThreadTrackTransformer())->transform($threadTrackData);

        // Assert
        $this->assertEquals(
            [
                'id' => $threadTrack->id,
                'job_id' => $job->id,
                'job_name' => $job->name,
                'message' => $thread->message,
                'worker_id' => $resignedWorkerId,
                'worker_name' => User::RESIGNED_USER_NAME,
                'thumbnail_url' => $noImageUrl,
                'modified' => $threadTrack->modified
                    ->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'state_group_id' => null,
                'state' => null
            ],
            $result
        );
    }
}
