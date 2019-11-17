<?php

namespace Tests\Feature\Controllers\V1\Internal\Admin;

use App\Http\Controllers\V1\Internal\Admin\JobsCountController;
use App\Models\Job;
use App\Models\User;
use App\Models\JobRole;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Carbon\Carbon;

class JobsCountControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $url;

    /**
     * @param User $user
     */
    private function setUrl(User $user)
    {
        $this->url = $this->internalDomain . '/api/v1/admin/' . $user->id . '/jobs_count';
    }

    public function createData()
    {
        // 未承認かつ反社OK
        $job1 = factory(Job::class)->states('not_active')->create();
        $antisocialCheckedClient = factory(User::class)->states('client', 'antisocial_ok')->create([
            'antisocial_check_date' => Carbon::now()
        ]);
        factory(JobRole::class)->create([
            'job_id' => $job1->id,
            'user_id' => $antisocialCheckedClient->id
        ]);

        // 未承認かつ反社未チェック
        $job2 = factory(Job::class)->states('not_active')->create();
        $antisocialUncheckedClient = factory(User::class)->states('client', 'antisocial_unchecked')->create();
        factory(JobRole::class)->create([
            'job_id' => $job2->id,
            'user_id' => $antisocialUncheckedClient->id
        ]);

        return compact('antisocialCheckedClient', 'antisocialUncheckedClient');
    }

    public function testIndex200()
    {
        // Arrange
        $admin = factory(User::class)->states('admin')->create();
        $this->setUrl($admin);
        $this->setAuthHeader($admin);

        $this->createData();

        // Act
        $response = $this->get($this->url, $this->headers);

        // Assert
        $response->assertStatus(200);
    }

    // 該当するデータがなかった場合
    public function testIndexNoData()
    {
        // Arrange
        $admin = factory(User::class)->states('admin')->create();
        $this->setUrl($admin);
        $this->setAuthHeader($admin);

        // Act
        $response = $this->get($this->url, $this->headers);

        // Assert
        $response->assertStatus(200);
    }

    // バックエンドユーザー以下の権限では閲覧できない
    public function provideTestIndex403()
    {
        return[
            'finance権限' => ['finance'],
            'realtime権限' => ['realtime'],
        ];
    }

    /**
     * @dataProvider provideTestIndex403
     *
     * @param string $adminGroup
     */
    public function testIndex403($adminGroup)
    {
        $user = factory(User::class)->states($adminGroup)->create();
        $this->setUrl($user);
        $this->setAuthHeader($user);

        $this->createData();

        // Act
        $response = $this->get($this->url, $this->headers);

        // Assert
        $response->assertStatus(403);
    }

    // 未承認の仕事の件数が返却されることを確認
    public function testWaitingJobCount()
    {
        // Arrange
        $jobsCountController = new JobsCountController();
        $method = $this->unprotect($jobsCountController, 'baseQuery');
        $createdJobs = $this->createData();

        // 承認済の仕事を作成
        $job3 = factory(Job::class)->states('active')->create();
        factory(JobRole::class)->create([
            'job_id' => $job3->id,
            'user_id' => $createdJobs['antisocialCheckedClient']->id
        ]);
        $job4 = factory(Job::class)->states('active')->create();
        factory(JobRole::class)->create([
            'job_id' => $job4->id,
            'user_id' => $createdJobs['antisocialUncheckedClient']->id
        ]);

        $expectedCount = 2; // 未承認の仕事件数がカウントされるので、2件であれば良い

        // Act
        $resultCount = $method->invoke($jobsCountController)->count();

        // Assert
        $this->assertSame($expectedCount, $resultCount);
    }

    // 反社チェックOK・反社チェックNGの仕事が正しい件数で返却されることを確認
    public function provideTestGetAntisocialCount()
    {
        // ここでは、反社チェックOK・反社チェックNG以外の場合を返却する
        return
        [
            '反社チェックNG' => ['antisocial_ng'],
            'IN_PROGRESS' => ['in_progress'],
            'INITIAL_EXAMINATION' => ['initial_examination'],
        ];
    }

    /**
     * @dataProvider provideTestGetAntisocialCount
     *
     * @param string $antisocialState
     */
    public function testGetAntisocialCount($antisocialState)
    {
        // Arrange
        $jobsCountController = new JobsCountController();
        $method = $this->unprotect($jobsCountController, 'getAntisocialCount');
        $createdJobs = $this->createData();

        $job3 = factory(Job::class)->states('active')->create();
        $targetClient = factory(User::class)->states('client', $antisocialState)->create();
        factory(JobRole::class)->create([
            'job_id' => $job3->id,
            'user_id' => $targetClient->id
        ]);

        $expectedCountArray = [ // 3件目がカウントされず、反社OK・反社NGがそれぞれ1件ずつであれば良い
            'checkedAntisocialCount' => 1,
            'unCheckedAntisocialCount' => 1,
        ];

        // Act
        $resultCountArray = $method->invoke($jobsCountController);

        // Assert
        $this->assertSame($expectedCountArray, $resultCountArray);
    }
}
