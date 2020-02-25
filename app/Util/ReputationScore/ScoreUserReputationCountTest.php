<?php

namespace Tests\Unit\Models;

use OwenIt\Auditing\Models\Audit;
use App\Models\Job;
use App\Models\JobRole;
use App\Models\Partner;
use App\Models\PointDetail;
use App\Models\PointLog;
use App\Models\S3Doc;
use App\Models\ScoreUserReputationCount;
use App\Models\ScoreReputation;
use App\Models\ScoreScore;
use App\Models\User;
use App\Models\TaskTrade;
use App\Models\Trade;
use App\Models\SellingPoint;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ScoreUserReputationCountTest extends TestCase
{
    use DatabaseTransactions;

    private $baseDatetime;

    public function setUp()
    {
        parent::setUp();
        $this->baseDatetime = Carbon::now(); // 現在時刻を設定する
    }

    /**
     * 指定されたユーザーに関して、正しいシュフティスコアが返却されること
     */
    public function testGetUserScore()
    {
        // Arrange
        $userId = random_int(1, 50000);
        // score_user_reputation_counts のテストデータを作成
        factory(ScoreUserReputationCount::class)->create([
            'user_id' => $userId,
            'score_reputation_id' => 1,
            'count' => 11,
        ]);
        factory(ScoreUserReputationCount::class)->create([
            'user_id' => $userId,
            'score_reputation_id' => 2,
            'count' => 11,
        ]);
        // score_scores のテストデータを作成
        // 既存データで挿入されているものについては作成しない
        factory(ScoreScore::class)->create([
            'score_reputation_id' => 2,
            'is_every_time' => 1,
            'score' => 10,
        ]);
        $expectUserScore = 121; // (1*11 + 10*11 = 121)

        // Act
        $resultUserScore = ScoreUserReputationCount::getUserScore($userId);

        // Assert
        $this->assertSame($expectUserScore, $resultUserScore);
    }

    /**
     * 指定されたユーザーが、score_user_reputation_counts テーブルに存在しない（行動を行なっていない）場合
     */
    public function testGetUserScoreInvalidUser()
    {
        // Arrange
        $scoreUserReputationCount = factory(ScoreUserReputationCount::class)->create([
            'user_id' => random_int(1, 50000),
            'score_reputation_id' => random_int(1, 100)
        ]);
        $invalidUserId = $scoreUserReputationCount->user_id + 1; // 存在しないユーザーID

        // Act
        $resultUserScore = ScoreUserReputationCount::getUserScore($invalidUserId);

        // Assert
        $this->assertFalse($resultUserScore);
    }

    public function providerTestGetCount()
    {
        return
        [
            '引数なし' => [
                false, // hasFinishTime
                false, // hasStartTime
                false // hasUserIds
            ],
            'finishTime が渡された場合' => [
                true,
                false,
                false
            ],
            'startTime が渡された場合' => [
                false,
                true,
                false
            ],
            'userIds が渡された場合' => [
                false,
                false,
                true
            ]
        ];
    }

    public function createDataGetCountOfSomeClientReputations()
    {
        $user1 = factory(User::class)->states('client')->create();
        $user2 = factory(User::class)->states('client')->create();

        // 期間指定の対象となるjob_rolesと、countの対象となるtask_tradesを作成する
        $targetCount1 = 10;
        $targetCount2 = 8;
        $baseDatetime1 = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount1; $index++) {
            $modifiedTimeDate = $baseDatetime1->addSecond();
            $jobRole1 = factory(JobRole::class)->states('outsourcer')->create([
                'user_id' => $user1->id,
                'job_id' => random_int(100, 100000),
                'modified' => $modifiedTimeDate,
            ]);
            // タスク：納品物の検品をする（承認） のテストデータ
            factory(TaskTrade::class)->states('delivery', 'delivery_accept')->create([
                'job_id' => $jobRole1->job_id,
                'modified' => $modifiedTimeDate,
            ]);
            // タスク：納品物の検品をする（非承認） テストデータ
            factory(TaskTrade::class)->states('delivery', 'delivery_reject')->create([
                'job_id' => $jobRole1->job_id,
                'modified' => $modifiedTimeDate,
            ]);
            // プロジェクト：発注する のテストデータ
            factory(Trade::class)->states('work')->create([
                'job_id' => $jobRole1->job_id,
                'modified' => $modifiedTimeDate,
            ]);
            // // プロジェクト：納品物の検品をする（承認） のテストデータ
            // factory(Trade::class)->states('delivery', 'delivery_accept')->create([
            //     'job_id' => $jobRole1->job_id,
            //     'modified' => $modifiedTimeDate,
            // ]);
            // // プロジェクト：納品物の検品をする（差し戻し） のテストデータ
            // factory(Trade::class)->states('delivery', 'delivery_reject')->create([
            //     'job_id' => $jobRole1->job_id,
            //     'modified' => $modifiedTimeDate,
            // ]);
            // // プロジェクト：評価する のテストデータ
            // factory(Trade::class)->states('finish')->create([
            //     'job_id' => $jobRole1->job_id,
            //     'modified' => $modifiedTimeDate,
            // ]);
            // // プロジェクト：再発注する のテストデータ
            // factory(Trade::class)->states('reorder')->create([
            //     'job_id' => $jobRole1->job_id,
            //     'modified' => $modifiedTimeDate,
            // ]);
        }

        $baseDatetime2 = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount2; $index++) {
            $modifiedTimeDate = $baseDatetime2->addSecond();
            $jobRole2 = factory(JobRole::class)->states('outsourcer')->create([
                'user_id' => $user2->id,
                'job_id' => random_int(100, 100000),
                'modified' => $modifiedTimeDate,
            ]);
            // タスク：納品物が検品され、承認されたテストデータ
            factory(TaskTrade::class)->states('delivery', 'delivery_accept')->create([
                'job_id' => $jobRole2->job_id,
                'modified' => $modifiedTimeDate,
            ]);
            // タスク：納品物が検品され、非承認されたテストデータ
            factory(TaskTrade::class)->states('delivery', 'delivery_reject')->create([
                'job_id' => $jobRole2->job_id,
                'modified' => $modifiedTimeDate,
            ]);
            // プロジェクト：発注する のテストデータ
            factory(Trade::class)->states('work')->create([
                'job_id' => $jobRole2->job_id,
                'modified' => $modifiedTimeDate,
            ]);
            // // プロジェクト：納品物の検品をする（承認） のテストデータ
            // factory(Trade::class)->states('delivery', 'delivery_accept')->create([
            //     'job_id' => $jobRole2->job_id,
            //     'modified' => $modifiedTimeDate,
            // ]);
            // // プロジェクト：納品物の検品をする（差し戻し） のテストデータ
            // factory(Trade::class)->states('delivery', 'delivery_reject')->create([
            //     'job_id' => $jobRole2->job_id,
            //     'modified' => $modifiedTimeDate,
            // ]);
            // // プロジェクト：評価する のテストデータ
            // factory(Trade::class)->states('finish')->create([
            //     'job_id' => $jobRole2->job_id,
            //     'modified' => $modifiedTimeDate,
            // ]);
            // // プロジェクト：再発注する のテストデータ
            // factory(Trade::class)->states('reorder')->create([
            //     'job_id' => $jobRole2->job_id,
            //     'modified' => $modifiedTimeDate,
            // ]);
        }

        return compact('user1', 'user2', 'targetCount1', 'targetCount2');
    }

    /**
     * タスク：納品物の検品をする（承認） の回数が取得できていること
     * タスク：納品物の検品をする（非承認） の回数が取得できていること
     * プロジェクト：発注する の回数が取得できていること
     * プロジェクト：納品物の検品をする（承認） の回数が取得できていること
     * プロジェクト：納品物の検品をする（差し戻し） の回数が取得できていること
     * プロジェクト：評価する の回数が取得できていること
     * プロジェクト：再発注する の回数が取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfSomeClientReputations($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfSomeClientReputations();

        $startTime = $this->baseDatetime->copy()->addSeconds(3);
        $finishTime = $this->baseDatetime->copy()->addSeconds(7);
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount1 = $createData['targetCount1'];
        $expectMaxCount2 = $createData['targetCount2'];

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getCountOfSomeClientReputations();
            // タスク：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_ACCEPT_DELIVERY);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // count:10, user1
            $this->assertEquals($expectMaxCount2, $records[$targetKeys[1]]->count); // count:8, user2

            // タスク：納品物の検品をする（非承認） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_REJECT_DELIVERY);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // count:10, user1
            $this->assertEquals($expectMaxCount2, $records[$targetKeys[1]]->count); // count:8, user2

            // プロジェクト：発注する の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_ORDER);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // count:10, user1
            $this->assertEquals($expectMaxCount2, $records[$targetKeys[1]]->count); // count:8, user2

            // プロジェクト：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // count:10, user1
            $this->assertEquals($expectMaxCount2, $records[$targetKeys[1]]->count); // count:8, user2

            // プロジェクト：納品物の検品をする（差し戻し） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REJECT_DELIVERY);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // count:10, user1
            $this->assertEquals($expectMaxCount2, $records[$targetKeys[1]]->count); // count:8, user2

            // プロジェクト：評価する の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_FINISH);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // count:10, user1
            $this->assertEquals($expectMaxCount2, $records[$targetKeys[1]]->count); // count:8, user2

            // プロジェクト：再発注する の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REORDER);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // count:10, user1
            $this->assertEquals($expectMaxCount2, $records[$targetKeys[1]]->count); // count:8, user2
        }
        if ($hasFinishTime && ! ($hasStartTime || $hasUserIds)) { // finishTime だけ渡された場合
            $records = ScoreUserReputationCount::getCountOfSomeClientReputations($finishTime);
            // タスク：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_ACCEPT_DELIVERY);
            $this->assertEquals(6, $records[$targetKeys[0]]->count); // 最初から数えて6つ, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 最初から数えて6つ, user2

            // タスク：納品物の検品をする（非承認） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_REJECT_DELIVERY);
            $this->assertEquals(6, $records[$targetKeys[0]]->count); // 最初から数えて6つ, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 最初から数えて6つ, user2

            // プロジェクト：発注する の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_ORDER);
            $this->assertEquals(6, $records[$targetKeys[0]]->count); // 最初から数えて6つ, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 最初から数えて6つ, user2

            // プロジェクト：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY);
            $this->assertEquals(6, $records[$targetKeys[0]]->count); // 最初から数えて6つ, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 最初から数えて6つ, user2

            // プロジェクト：納品物の検品をする（差し戻し） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REJECT_DELIVERY);
            $this->assertEquals(6, $records[$targetKeys[0]]->count); // 最初から数えて6つ, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 最初から数えて6つ, user2

            // プロジェクト：評価する の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_FINISH);
            $this->assertEquals(6, $records[$targetKeys[0]]->count); // 最初から数えて6つ, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 最初から数えて6つ, user2

            // プロジェクト：再発注する の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REORDER);
            $this->assertEquals(6, $records[$targetKeys[0]]->count); // 最初から数えて6つ, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 最初から数えて6つ, user2
        }
        if ($hasStartTime && ! ($hasFinishTime || $hasUserIds)) { // startTime だけ渡された場合
            $records = ScoreUserReputationCount::getCountOfSomeClientReputations(null, $startTime);
            // タスク：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_ACCEPT_DELIVERY);
            $this->assertEquals(8, $records[$targetKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 後ろから数えて-2, user2

            // タスク：納品物の検品をする（非承認） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_REJECT_DELIVERY);
            $this->assertEquals(8, $records[$targetKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 後ろから数えて-2, user2

            // プロジェクト：発注する の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_ORDER);
            $this->assertEquals(8, $records[$targetKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 後ろから数えて-2, user2

            // プロジェクト：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY);
            $this->assertEquals(8, $records[$targetKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 後ろから数えて-2, user2

            // プロジェクト：納品物の検品をする（差し戻し） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REJECT_DELIVERY);
            $this->assertEquals(8, $records[$targetKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 後ろから数えて-2, user2

            // プロジェクト：評価する の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_FINISH);
            $this->assertEquals(8, $records[$targetKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 後ろから数えて-2, user2

            // プロジェクト：再発注する の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REORDER);
            $this->assertEquals(8, $records[$targetKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(6, $records[$targetKeys[1]]->count); // 後ろから数えて-2, user2
        }
        if ($hasUserIds && ! ($hasFinishTime || $hasStartTime)) {
            $records = ScoreUserReputationCount::getCountOfSomeClientReputations(null, null, $userIds);
            // タスク：納品物の検品をする（承認） の回数が取得できていること を確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_ACCEPT_DELIVERY);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // 10
            $this->assertCount(1, $targetKeys); // 指定の行動に対する結果が1つしか含まれないこと
            $this->assertEquals($createData['user1']->id, $records[$targetKeys[0]]->user_id); // 上記が user1 のものであること

            // タスク：納品物の検品をする（非承認）の回数が取得できていること を確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_REJECT_DELIVERY);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // 10
            $this->assertCount(1, $targetKeys); // 指定の行動に対する結果が1つしか含まれないこと
            $this->assertEquals($createData['user1']->id, $records[$targetKeys[0]]->user_id); // 上記が user1 のものであること

            // プロジェクト：発注する の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_ORDER);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // 10
            $this->assertCount(1, $targetKeys); // 指定の行動に対する結果が1つしか含まれないこと
            $this->assertEquals($createData['user1']->id, $records[$targetKeys[0]]->user_id); // 上記が user1 のものであること

            // プロジェクト：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // 10
            $this->assertCount(1, $targetKeys); // 指定の行動に対する結果が1つしか含まれないこと
            $this->assertEquals($createData['user1']->id, $records[$targetKeys[0]]->user_id); // 上記が user1 のものであること

            // プロジェクト：納品物の検品をする（差し戻し） の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REJECT_DELIVERY);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // 10
            $this->assertCount(1, $targetKeys); // 指定の行動に対する結果が1つしか含まれないこと
            $this->assertEquals($createData['user1']->id, $records[$targetKeys[0]]->user_id); // 上記が user1 のものであること

            // プロジェクト：評価する の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_FINISH);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // 10
            $this->assertCount(1, $targetKeys); // 指定の行動に対する結果が1つしか含まれないこと
            $this->assertEquals($createData['user1']->id, $records[$targetKeys[0]]->user_id); // 上記が user1 のものであること

            // プロジェクト：再発注する の回数が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REORDER);
            $this->assertEquals($expectMaxCount1, $records[$targetKeys[0]]->count); // 10
            $this->assertCount(1, $targetKeys); // 指定の行動に対する結果が1つしか含まれないこと
            $this->assertEquals($createData['user1']->id, $records[$targetKeys[0]]->user_id); // 上記が user1 のものであること
        }
    }

    public function createDataGetCountOfJobAccept()
    {
        $user1 = factory(User::class)->states('client')->create();
        $user2 = factory(User::class)->states('client')->create();

        // countの対象となる jobs・job_roles を作成する
        $targetCount1 = 10;
        $targetCount2 = 8;
        $baseDatetime1 = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount1; $index++) {
            $job1 = factory(Job::class)->states('active')->create([
                'modified' => $baseDatetime1->addSecond()
            ]);
            factory(JobRole::class)->states('outsourcer')->create([
                'user_id' => $user1->id,
                'job_id' => $job1->id
            ]);
        }
        $baseDatetime2 = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount2; $index++) {
            $job2 = factory(Job::class)->states('active')->create([
                'modified' => $baseDatetime2->addSecond()
            ]);
            factory(JobRole::class)->states('outsourcer')->create([
                'user_id' => $user2->id,
                'job_id' => $job2->id
            ]);
        }

        return compact('user1', 'user2', 'targetCount1', 'targetCount2');
    }

    /**
     * 仕事が承認された回数を取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfJobAccept($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfJobAccept();

        $startTime = $this->baseDatetime->copy()->addSeconds(3);
        $finishTime = $this->baseDatetime->copy()->addSeconds(7);
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount1 = $createData['targetCount1'];
        $expectMaxCount2 = $createData['targetCount2'];

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getCountOfJobAccept();
            $this->assertEquals($expectMaxCount1, $records[0]->count); // 10
            $this->assertEquals($expectMaxCount2, $records[1]->count); // 8
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfJobAccept($finishTime);
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
            $this->assertEquals(6, $records[1]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfJobAccept(null, $startTime);
            $this->assertEquals(8, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(6, $records[1]->count); // 後ろから数えて -2
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getCountOfJobAccept(null, null, $userIds);
            $this->assertEquals($expectMaxCount1, $records[0]->count); // 10
            $this->assertCount(1, $records); // 結果が1つしか含まれないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // 上記が user1 のものあること
        }
    }

    public function createDataGetCountOfJobReEdit()
    {
        $user1 = factory(User::class)->states('client')->create();
        $job1 = factory(Job::class)->create();
        factory(JobRole::class)->states('outsourcer')->create([
            'user_id' => $user1->id,
            'job_id' => $job1->id
        ]);
        $user2 = factory(User::class)->states('client')->create();
        $job2 = factory(Job::class)->create();
        factory(JobRole::class)->states('outsourcer')->create([
            'user_id' => $user2->id,
            'job_id' => $job2->id
        ]);

        // countの対象となる Audits を作成する
        $targetCount1 = 10;
        $targetCount2 = 8;
        $baseDatetime1 = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount1; $index++) {
            factory(Audit::class)->states('job')->create([
                'user_id' => $user1->id,
                'auditable_id' => $job1->id,
                'event' => 'updated',
                'old_values' => ['re_edit' => 1],
                'new_values' => ['re_edit' => false],
                'created_at' => $baseDatetime1->addSecond()
            ]);
        }
        $baseDatetime2 = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount2; $index++) {
            factory(Audit::class)->states('job')->create([
                'user_id' => $user2->id,
                'auditable_id' => $job2->id,
                'event' => 'updated',
                'old_values' => ['re_edit' => 1],
                'new_values' => ['re_edit' => false],
                'created_at' => $baseDatetime2->addSecond()
            ]);
        }

        return compact('user1', 'user2', 'targetCount1', 'targetCount2');
    }

    /**
     * 差し戻された仕事を修正して再申請した回数を取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfJobReEdit($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfJobReEdit();

        $startTime = $this->baseDatetime->copy()->addSeconds(3);
        $finishTime = $this->baseDatetime->copy()->addSeconds(7);
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount1 = $createData['targetCount1'];
        $expectMaxCount2 = $createData['targetCount2'];

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getCountOfJobReEdit();
            $this->assertEquals($expectMaxCount1, $records[0]->count); // 10
            $this->assertEquals($expectMaxCount2, $records[1]->count); // 8
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfJobReEdit($finishTime);
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
            $this->assertEquals(6, $records[1]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfJobReEdit(null, $startTime);
            $this->assertEquals(8, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(6, $records[1]->count); // 後ろから数えて -2
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getCountOfJobReEdit(null, null, $userIds);
            $this->assertEquals($expectMaxCount1, $records[0]->count); // 10
            $this->assertCount(1, $records); // 結果が1つしか含まれないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // 上記が user1 のものあること
        }
    }

    public function createDataGetIsSupplement()
    {
        $user1 = factory(User::class)->states('client')->create();
        factory(S3Doc::class)->states('certificate')->create([
            'foreign_key' => $user1->id,
            'modified' => $this->baseDatetime->copy()
        ]);
        
        $user2 = factory(User::class)->states('client')->create();
        factory(S3Doc::class)->states('certificate')->create([
            'foreign_key' => $user2->id,
            'modified' => $this->baseDatetime->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2');
    }

    /**
     * 本人確認資料を提出したかどうか を取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetIsSupplement($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetIsSupplement();

        $startTime = $this->baseDatetime->copy()->addSeconds(3);
        $finishTime = $this->baseDatetime->copy()->addSeconds(7);
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getIsSupplement();
            $this->assertEquals(1, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getIsSupplement($finishTime);
            $this->assertEquals(1, $records[0]->count);
            $this->assertEquals(0, $records[1]->count);
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getIsSupplement(null, $startTime);
            $this->assertEquals(0, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getIsSupplement(null, null, $userIds);
            $this->assertEquals(1, $records[0]->count);
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が含まれること
            $this->assertNotEquals($createData['user2']->id, $records[0]->user_id); // user2 が含まれないこと
        }
    }

    public function createDataGetIsSettingThumbnail()
    {
        $user1 = factory(User::class)->states('client')->create();
        factory(S3Doc::class)->states('thumbnail')->create([
            'foreign_key' => $user1->id,
            'created' => $this->baseDatetime->copy()
        ]);
        
        $user2 = factory(User::class)->states('client')->create();
        factory(S3Doc::class)->states('thumbnail')->create([
            'foreign_key' => $user2->id,
            'created' => $this->baseDatetime->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2');
    }

    /**
     * アイコンを設定したかどうか を取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetIsSettingThumbnail($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetIsSettingThumbnail();

        $startTime = $this->baseDatetime->copy()->addSeconds(3);
        $finishTime = $this->baseDatetime->copy()->addSeconds(7);
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getIsSettingThumbnail();
            $this->assertEquals(1, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getIsSettingThumbnail($finishTime);
            $this->assertEquals(1, $records[0]->count);
            $this->assertEquals(0, $records[1]->count);
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getIsSettingThumbnail(null, $startTime);
            $this->assertEquals(0, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getIsSettingThumbnail(null, null, $userIds);
            $this->assertEquals(1, $records[0]->count);
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が含まれること
            $this->assertNotEquals($createData['user2']->id, $records[0]->user_id); // user2 が含まれないこと
        }
    }

    public function createDataGetCountOfApplyPartner()
    {
        $user1 = factory(User::class)->states('client')->create();
        $user2 = factory(User::class)->states('client')->create();

        // countの対象となる partners を作成する
        $targetCount1 = 10;
        $targetCount2 = 8;
        $baseDatetime1 = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount1; $index++) {
            factory(Partner::class)->create([
                'outsourcer_id' => $user1->id,
                'created' => $baseDatetime1->addSecond()
            ]);
        }
        $baseDatetime2 = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount2; $index++) {
            factory(Partner::class)->create([
                'outsourcer_id' => $user2->id,
                'created' => $baseDatetime2->addSecond()
            ]);
        }

        return compact('user1', 'user2', 'targetCount1', 'targetCount2');
    }

    /**
     * パートナー申請した回数 を取得できているかどうか
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfApplyPartner($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfApplyPartner();

        $startTime = $this->baseDatetime->copy()->addSeconds(3);
        $finishTime = $this->baseDatetime->copy()->addSeconds(7);
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount1 = $createData['targetCount1'];
        $expectMaxCount2 = $createData['targetCount2'];

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getCountOfApplyPartner();
            $this->assertEquals($expectMaxCount1, $records[0]->count); // 10
            $this->assertEquals($expectMaxCount2, $records[1]->count); // 8
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfApplyPartner($finishTime);
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
            $this->assertEquals(6, $records[1]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfApplyPartner(null, $startTime);
            $this->assertEquals(8, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(6, $records[1]->count); // 後ろから数えて -2
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getCountOfApplyPartner(null, null, $userIds);
            $this->assertEquals($expectMaxCount1, $records[0]->count); // 10
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が含まれること
            $this->assertNotEquals($createData['user2']->id, $records[0]->user_id); // user2 が含まれないこと
        }
    }

    public function createDataGetCountOfPaidDeffer()
    {
        $user1 = factory(User::class)->states('client')->create();
        $user2 = factory(User::class)->states('client')->create();

        // countの対象となる point_details・point_logs を作成する
        $targetCount1 = 10;
        $targetCount2 = 8;
        $baseDatetime1 = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount1; $index++) {
            $pointLog1 = factory(PointLog::class)->states('deferred_payment')->create();
            factory(PointDetail::class)->create([
                'user_id' => $user1->id,
                'point_log_id' => $pointLog1->id,
                'modified' => $baseDatetime1->addSecond()
            ]);
        }
        $baseDatetime2 = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount2; $index++) {
            $pointLog2 = factory(PointLog::class)->states('deferred_payment')->create();
            factory(PointDetail::class)->create([
                'user_id' => $user2->id,
                'point_log_id' => $pointLog2->id,
                'modified' => $baseDatetime2->addSecond()
            ]);
        }

        return compact('user1', 'user2', 'targetCount1', 'targetCount2');
    }

    /**
     * 後払いの代金を支払った回数 を取得できているかどうか
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfPaidDeffer($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfPaidDeffer();

        $startTime = $this->baseDatetime->copy()->addSeconds(3);
        $finishTime = $this->baseDatetime->copy()->addSeconds(7);
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount1 = $createData['targetCount1'];
        $expectMaxCount2 = $createData['targetCount2'];

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getCountOfPaidDeffer();
            $this->assertEquals($expectMaxCount1, $records[0]->count); // 10
            $this->assertEquals($expectMaxCount2, $records[1]->count); // 8
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfPaidDeffer($finishTime);
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
            $this->assertEquals(6, $records[1]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfPaidDeffer(null, $startTime);
            $this->assertEquals(8, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(6, $records[1]->count); // 後ろから数えて -2
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getCountOfPaidDeffer(null, null, $userIds);
            $this->assertEquals($expectMaxCount1, $records[0]->count); // 10
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が含まれること
            $this->assertNotEquals($createData['user2']->id, $records[0]->user_id); // user2 が含まれないこと
        }
    }

    /**
     * 全ての行動回数を取得できているか
     */
    public function testGetCountOfAllReputation()
    {
        // Arrange
        // データ作成するごとに、2ユーザーが作成される
        $this->createDataGetCountOfJobAccept(); // 仕事が承認されたデータを取得する ためのデータ
        $this->createDataGetIsSupplement(); // 本人確認資料を提出したかどうか のデータ
        $this->createDataGetIsSettingThumbnail(); // アイコンを設定したかどうか のデータ
        $this->createDataGetCountOfApplyPartner(); // パートナー申請をした回数を取得する ためのデータ
        $this->createDataGetCountOfPaidDeffer(); // 後払いの代金を支払った回数を取得する ためのデータ

        $expectRecordsCount = 5; // (2*5) * 5
        $expectedInRecordCount = 10; // 2*5

        // Act
        $recordsGetCountOfJobAccept = ScoreUserReputationCount::getCountOfJobAccept();
        $recordsOfAll = ScoreUserReputationCount::getCountOfAllReputation();
        $resultRecordsCount = count($recordsOfAll);

        // Assert
        $this->assertSame($expectRecordsCount, $resultRecordsCount); // 全体の配列数
        $this->assertSame($expectedInRecordCount, count($recordsOfAll[0])); // 個々の配列数
        $this->assertSame($expectedInRecordCount, count($recordsOfAll[1])); // 個々の配列数
        $this->assertSame($expectedInRecordCount, count($recordsOfAll[2])); // 個々の配列数
        $this->assertSame($expectedInRecordCount, count($recordsOfAll[3])); // 個々の配列数
        $this->assertSame($expectedInRecordCount, count($recordsOfAll[4])); // 個々の配列数
    }

    /**
     * 回数を保存時の新規登録と更新を確認
     *
     * @return void
     */
    public function testSaveByRecords()
    {
        // Arrange
        $scoreUserReputationCount = factory(ScoreUserReputationCount::class)->create([
            'user_id' => 1,
            'score_reputation_id' => random_int(1, 10),
        ]);
        $data = [
            // 既存データ更新
            (object)[
                'user_id' => $scoreUserReputationCount->user_id,
                'reputation_id' => $scoreUserReputationCount->score_reputation_id,
                'count' => 100,
            ],
            // 既存データとuser_id違いで新規
            (object)[
                'user_id' => 2,
                'reputation_id' => $scoreUserReputationCount->score_reputation_id,
                'count' => 200,
            ],
            // 既存データとreputation_id違いで新規
            (object)[
                'user_id' => $scoreUserReputationCount->user_id,
                'reputation_id' => $scoreUserReputationCount->score_reputation_id + 1,
                'count' => 100,
            ],
        ];

        // Act
        ScoreUserReputationCount::saveByRecords($data);

        // Assert
        $this->assertDatabaseHas('score_user_reputation_counts', [
            'user_id' => $scoreUserReputationCount->user_id,
            'score_reputation_id' => $scoreUserReputationCount->score_reputation_id,
            'count' => $scoreUserReputationCount->count + $data[0]->count,
        ]);
        $this->assertDatabaseHas('score_user_reputation_counts', [
            'user_id' => $data[1]->user_id,
            'score_reputation_id' => $scoreUserReputationCount->score_reputation_id,
            'count' => $data[1]->count,
        ]);
        $this->assertDatabaseHas('score_user_reputation_counts', [
            'user_id' => $scoreUserReputationCount->user_id,
            'score_reputation_id' => $data[2]->reputation_id,
            'count' => $data[2]->count,
        ]);
    }

    /**
     * 任意の $reputationId を返却する
     */
    private function getTargetReputationId(array $targetArray, int $reputationId): array
    {
        $reputationIds = array_column($targetArray, 'reputation_id');
        return array_keys($reputationIds, $reputationId);
    }
}
