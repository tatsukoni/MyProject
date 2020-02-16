<?php

namespace Tests\Feature\Commands;

use App\Models\User;
use App\Models\Job;
use App\Models\JobRole;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SaveReputationCountTest extends TestCase
{
    use DatabaseTransactions;

    public function testHandle()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        // 前日の 00:00:00 に承認された仕事
        $job1 = factory(Job::class)->states('active')->create([
            'modified' => Carbon::yesterday() // 00:00:00
        ]);
        factory(JobRole::class)->states('outsourcer')->create([
            'job_id' => $job1->id,
            'user_id' => $user->id
        ]);
        // 前日の 23:59:59 に承認された仕事
        $job2 = factory(Job::class)->states('active')->create([
            'modified' => Carbon::yesterday()->setTime(23, 59, 59) // 23:59:59
        ]);
        factory(JobRole::class)->states('outsourcer')->create([
            'job_id' => $job2->id,
            'user_id' => $user->id
        ]);

        // Act
        $result = $this->artisan('score:save_reputation_count');

        // Assert
        $this->assertEquals($result, 0);
        $this->assertDatabaseHas(
            'score_user_reputation_counts',
            [
                'user_id' => $user->id,
                'score_reputation_id' => 1,
                'count' => 2
            ]
        );
    }

    public function testNotExistData()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        // 前々日の 23:59:59 に承認された仕事
        $job1 = factory(Job::class)->states('active')->create([
            'modified' => Carbon::now()->subDays(2)->setTime(23, 59, 59) // 00:00:00
        ]);
        factory(JobRole::class)->states('outsourcer')->create([
            'job_id' => $job1->id,
            'user_id' => $user->id
        ]);
        // 今日の 00:00:00 に承認された仕事
        $job2 = factory(Job::class)->states('active')->create([
            'modified' => Carbon::today() // 00:00:00
        ]);
        factory(JobRole::class)->states('outsourcer')->create([
            'job_id' => $job2->id,
            'user_id' => $user->id
        ]);

        // Act
        $result = $this->artisan('score:save_reputation_count');

        // Assert
        $this->assertEquals($result, 0);
        $this->assertDatabaseMissing(
            'score_user_reputation_counts',
            [
                'user_id' => $user->id,
                'score_reputation_id' => 1
            ]
        );
    }
}
