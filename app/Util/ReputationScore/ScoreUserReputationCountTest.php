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

    public function createDataGetCountOfSomeClientTaskReputations()
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
        }

        return compact('user1', 'user2', 'targetCount1', 'targetCount2');
    }

    /**
     * タスク：納品物の検品をする（承認） の回数が取得できていること
     * タスク：納品物の検品をする（非承認） の回数が取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfSomeClientTaskReputations($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfSomeClientTaskReputations();

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
        }
    }

    public function createDataGetCountOfSomeProjectTrades()
    {
        $user1 = factory(User::class)->states('client')->create();
        // 期間指定の対象となるjob_rolesと、countの対象となるtradesを作成する
        $targetCount = 10;
        $baseDatetime = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            $modifiedTimeDate = $baseDatetime->addSecond();
            $jobRole1 = factory(JobRole::class)->states('outsourcer')->create([
                'user_id' => $user1->id,
                'job_id' => random_int(100, 100000),
                'modified' => $modifiedTimeDate,
            ]);
            // プロジェクト：発注する のテストデータ
            factory(Trade::class)->states('work')->create([
                'job_id' => $jobRole1->job_id,
                'modified' => $modifiedTimeDate,
            ]);
            // プロジェクト：納品物の検品をする（承認） のテストデータ
            factory(Trade::class)->states('delivery', 'delivery_accept')->create([
                'job_id' => $jobRole1->job_id,
                'modified' => $modifiedTimeDate,
            ]);
            // プロジェクト：納品物の検品をする（差し戻し） のテストデータ
            factory(Trade::class)->states('delivery', 'delivery_reject')->create([
                'job_id' => $jobRole1->job_id,
                'modified' => $modifiedTimeDate,
            ]);
            // プロジェクト：評価する のテストデータ
            factory(Trade::class)->states('finish')->create([
                'job_id' => $jobRole1->job_id,
                'modified' => $modifiedTimeDate,
            ]);
            // プロジェクト：再発注する のテストデータ
            factory(Trade::class)->states('reorder')->create([
                'job_id' => $jobRole1->job_id,
                'modified' => $modifiedTimeDate,
            ]);
        }

        $user2 = factory(User::class)->states('client')->create();
        // 10s後に期間設定したデータをそれぞれ1件のみ作成
        $jobRole2 = factory(JobRole::class)->states('outsourcer')->create([
            'user_id' => $user2->id,
            'job_id' => random_int(100, 100000),
            'modified' => $this->baseDatetime->copy()->addSeconds(10),
        ]);
        // プロジェクト：発注する のテストデータ
        factory(Trade::class)->states('work')->create([
            'job_id' => $jobRole2->job_id,
            'modified' => $this->baseDatetime->copy()->addSeconds(10),
        ]);
        // プロジェクト：納品物の検品をする（承認） のテストデータ
        factory(Trade::class)->states('delivery', 'delivery_accept')->create([
            'job_id' => $jobRole2->job_id,
            'modified' => $this->baseDatetime->copy()->addSeconds(10),
        ]);
        // プロジェクト：納品物の検品をする（差し戻し） のテストデータ
        factory(Trade::class)->states('delivery', 'delivery_reject')->create([
            'job_id' => $jobRole2->job_id,
            'modified' => $this->baseDatetime->copy()->addSeconds(10),
        ]);
        // プロジェクト：評価する のテストデータ
        factory(Trade::class)->states('finish')->create([
            'job_id' => $jobRole2->job_id,
            'modified' => $this->baseDatetime->copy()->addSeconds(10),
        ]);
        // プロジェクト：再発注する のテストデータ
        factory(Trade::class)->states('reorder')->create([
            'job_id' => $jobRole2->job_id,
            'modified' => $this->baseDatetime->copy()->addSeconds(10),
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
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
    public function testGetCountOfSomeProjectTrades($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfSomeProjectTrades();

        $startTime = $this->baseDatetime->copy()->addSeconds(3);
        $finishTime = $this->baseDatetime->copy()->addSeconds(7);
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getCountOfSomeProjectTrades();
            // プロジェクト：発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_ORDER);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount, $records[$targetRecordKeys[0]]->count); // user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2

            // プロジェクト：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount, $records[$targetRecordKeys[0]]->count); // user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2

            // プロジェクト：納品物の検品をする（差し戻し） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REJECT_DELIVERY);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount, $records[$targetRecordKeys[0]]->count); // user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2

            // プロジェクト：評価する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_FINISH);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount, $records[$targetRecordKeys[0]]->count); // user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2

            // プロジェクト：再発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REORDER);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount, $records[$targetRecordKeys[0]]->count); // user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2
        }
        if ($hasFinishTime) { // finishTime だけ渡された場合
            $records = ScoreUserReputationCount::getCountOfSomeProjectTrades($finishTime);
            // プロジェクト：発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_ORDER);
            $this->assertCount(1, $targetRecordKeys); // 0件の場合は取得されない
            $this->assertEquals(6, $records[$targetRecordKeys[0]]->count); // 最初から数えて6つ, user1

            // プロジェクト：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY);
            $this->assertCount(1, $targetRecordKeys); // 0件の場合は取得されない
            $this->assertEquals(6, $records[$targetRecordKeys[0]]->count); // 最初から数えて6つ, user1

            // プロジェクト：納品物の検品をする（差し戻し） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REJECT_DELIVERY);
            $this->assertCount(1, $targetRecordKeys); // 0件の場合は取得されない
            $this->assertEquals(6, $records[$targetRecordKeys[0]]->count); // 最初から数えて6つ, user1

            // プロジェクト：評価する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_FINISH);
            $this->assertCount(1, $targetRecordKeys); // 0件の場合は取得されない
            $this->assertEquals(6, $records[$targetRecordKeys[0]]->count); // 最初から数えて6つ, user1

            // プロジェクト：再発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REORDER);
            $this->assertCount(1, $targetRecordKeys); // 0件の場合は取得されない
            $this->assertEquals(6, $records[$targetRecordKeys[0]]->count); // 最初から数えて6つ, user1
        }
        if ($hasStartTime) { // startTime だけ渡された場合
            $records = ScoreUserReputationCount::getCountOfSomeProjectTrades(null, $startTime);
            // プロジェクト：発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_ORDER);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount - 2, $records[$targetRecordKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2

            // プロジェクト：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount - 2, $records[$targetRecordKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2

            // プロジェクト：納品物の検品をする（差し戻し） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REJECT_DELIVERY);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount - 2, $records[$targetRecordKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2

            // プロジェクト：評価する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_FINISH);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount - 2, $records[$targetRecordKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2

            // プロジェクト：再発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REORDER);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount - 2, $records[$targetRecordKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2
        }
        if ($hasUserIds) {
            $records = ScoreUserReputationCount::getCountOfSomeProjectTrades(null, null, $userIds);
            // プロジェクト：発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_ORDER);
            $this->assertCount(1, $targetRecordKeys); // 指定されたユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[$targetRecordKeys[0]]->user_id); // user1 しか取得されないこと

            // プロジェクト：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY);
            $this->assertCount(1, $targetRecordKeys); // 指定されたユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[$targetRecordKeys[0]]->user_id); // user1 しか取得されないこと

            // プロジェクト：納品物の検品をする（差し戻し） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REJECT_DELIVERY);
            $this->assertCount(1, $targetRecordKeys); // 指定されたユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[$targetRecordKeys[0]]->user_id); // user1 しか取得されないこと

            // プロジェクト：評価する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_FINISH);
            $this->assertCount(1, $targetRecordKeys); // 指定されたユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[$targetRecordKeys[0]]->user_id); // user1 しか取得されないこと

            // プロジェクト：再発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REORDER);
            $this->assertCount(1, $targetRecordKeys); // 指定されたユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[$targetRecordKeys[0]]->user_id); // user1 しか取得されないこと
        }
    }

    public function createDataGetCountOfSomeUserReputations()
    {
        $user1 = factory(User::class)->states('client', 'antisocial_ok')->create([
            'antisocial_check_date' => $this->baseDatetime->copy(),
            'created' => $this->baseDatetime->copy(),
        ]);
        factory(SellingPoint::class)->create([
            'user_id' => $user1->id,
            'modified' => $this->baseDatetime->copy()
        ]);
        
        $user2 = factory(User::class)->states('client', 'antisocial_ok')->create([
            'antisocial_check_date' => $this->baseDatetime->copy()->addSeconds(10),
            'created' => $this->baseDatetime->copy()->addSeconds(10),
        ]);
        factory(SellingPoint::class)->create([
            'user_id' => $user2->id,
            'modified' => $this->baseDatetime->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2');
    }

    /**
     * 【初】会員登録したかどうか が取得できていること
     * 【初】初回審査 を行なったか が取得できていること
     * 自己紹介を設定したかどうか が取得できいること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfSomeUserReputations($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfSomeUserReputations();

        $startTime = $this->baseDatetime->copy()->addSeconds(3);
        $finishTime = $this->baseDatetime->copy()->addSeconds(7);
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getCountOfSomeUserReputations();
            // 【初】会員登録したかどうか が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_REGISTRATION);
            $this->assertEquals(1, $records[$targetKeys[0]]->count); // user1
            $this->assertEquals(1, $records[$targetKeys[1]]->count); // user2

            // 【初】初回審査 を行なったか が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_INIT_SCREENING);
            $this->assertEquals(1, $records[$targetKeys[0]]->count); // user1
            $this->assertEquals(1, $records[$targetKeys[1]]->count); // user2

            // 自己紹介を設定したかどうか が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_SET_PROFILE);
            $this->assertEquals(1, $records[$targetKeys[0]]->count); // user1
            $this->assertEquals(1, $records[$targetKeys[1]]->count); // user2
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfSomeUserReputations($finishTime);
            // 【初】会員登録したかどうか が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_REGISTRATION);
            $this->assertEquals(1, $records[$targetKeys[0]]->count); // user1
            $this->assertEquals(0, $records[$targetKeys[1]]->count); // user2

            // 【初】初回審査 を行なったか が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_INIT_SCREENING);
            $this->assertEquals(1, $records[$targetKeys[0]]->count); // user1（+0s）
            $this->assertEquals(0, $records[$targetKeys[1]]->count); // user2（+10s）
            dump($targetKeys);

            // 自己紹介を設定したかどうか が取得できいることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_SET_PROFILE);
            $this->assertEquals(1, $records[$targetKeys[0]]->count); // user1（+0s）
            $this->assertEquals(0, $records[$targetKeys[1]]->count); // user2（+10s）
            dump($targetKeys);
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfSomeUserReputations(null, $startTime);
            // 【初】会員登録したかどうか が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_REGISTRATION);
            $this->assertEquals(0, $records[$targetKeys[0]]->count); // user1（+0s）
            $this->assertEquals(1, $records[$targetKeys[1]]->count); // user2（+10s）

            // 【初】初回審査 を行なったか が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_INIT_SCREENING);
            $this->assertEquals(0, $records[$targetKeys[0]]->count); // user1（+0s）
            $this->assertEquals(1, $records[$targetKeys[1]]->count); // user2（+10s）

            // 自己紹介を設定したかどうか が取得できいることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_SET_PROFILE);
            $this->assertEquals(0, $records[$targetKeys[0]]->count); // user1（+0s）
            $this->assertEquals(1, $records[$targetKeys[1]]->count); // user2（+10s）
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getCountOfSomeUserReputations(null, null, $userIds);
            // 【初】会員登録したかどうか が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_REGISTRATION);
            $this->assertCount(1, $targetKeys); // 指定の行動に対する結果が1つしか含まれないこと
            $this->assertEquals($createData['user1']->id, $records[$targetKeys[0]]->user_id); // 指定されたuser1 が含まれること
            $this->assertEquals(1, $records[$targetKeys[0]]->count); // user1

            // 【初】初回審査 を行なったか が取得できていることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_INIT_SCREENING);
            $this->assertCount(1, $targetKeys); // 指定の行動に対する結果が1つしか含まれないこと
            $this->assertEquals($createData['user1']->id, $records[$targetKeys[0]]->user_id); // 指定されたuser1 が含まれること
            $this->assertEquals(1, $records[$targetKeys[0]]->count); // user1

            // 自己紹介を設定したかどうか が取得できいることを確認
            $targetKeys = $this->getTargetReputationId($records, ScoreReputation::ID_SET_PROFILE);
            $this->assertCount(1, $targetKeys); // 指定の行動に対する結果が1つしか含まれないこと
            $this->assertEquals($createData['user1']->id, $records[$targetKeys[0]]->user_id); // 指定されたuser1 が含まれること
            $this->assertEquals(1, $records[$targetKeys[0]]->count); // user1
        }
    }

    public function createDataGetCountOfJobAccept()
    {
        $user1 = factory(User::class)->states('client')->create();
        // countの対象となる jobs・job_roles を作成する
        $targetCount = 10;
        $baseDatetime = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            $job1 = factory(Job::class)->states('active')->create([
                'activated_date' => $baseDatetime->addSecond()
            ]);
            factory(JobRole::class)->states('outsourcer')->create([
                'user_id' => $user1->id,
                'job_id' => $job1->id
            ]);
        }

        $user2 = factory(User::class)->states('client')->create();
        // 10s後に期間設定したデータを1件のみ作成
        $job2 = factory(Job::class)->states('active')->create([
            'activated_date' => $this->baseDatetime->copy()->addSeconds(10)
        ]);
        factory(JobRole::class)->states('outsourcer')->create([
            'user_id' => $user2->id,
            'job_id' => $job2->id
        ]);

        return compact('user1', 'user2', 'targetCount');
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
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getCountOfJobAccept();
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfJobAccept($finishTime);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals(6, $records[0]->count); // user1・最初から数えて6つ
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfJobAccept(null, $startTime);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // user1・後ろから数えて -2
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getCountOfJobAccept(null, null, $userIds);
            $this->assertCount(1, $records); // 指定された1ユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
    }

    public function createDataGetCountOfJobReEdit()
    {
        $user1 = factory(User::class)->states('client')->create();
        // countの対象となる audits を作成する
        $targetCount = 10;
        $baseDatetime = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            // auditsの期間指定による取得件数変化を見たいので、jobs・job_rolesも複数作成する
            $job1 = factory(Job::class)->create();
            factory(JobRole::class)->states('outsourcer')->create([
                'user_id' => $user1->id,
                'job_id' => $job1->id
            ]);
            factory(Audit::class)->states('job')->create([
                'user_id' => $user1->id,
                'auditable_id' => $job1->id,
                'event' => 'updated',
                'old_values' => ['re_edit' => 1],
                'new_values' => ['re_edit' => false],
                'created_at' => $baseDatetime->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('client')->create();
        $job2 = factory(Job::class)->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(JobRole::class)->states('outsourcer')->create([
            'user_id' => $user2->id,
            'job_id' => $job2->id
        ]);
        factory(Audit::class)->states('job')->create([
            'user_id' => $user2->id,
            'auditable_id' => $job2->id,
            'event' => 'updated',
            'old_values' => ['re_edit' => 1],
            'new_values' => ['re_edit' => false],
            'created_at' => $this->baseDatetime->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2', 'targetCount');
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
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getCountOfJobReEdit();
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfJobReEdit($finishTime);
            $this->assertCount(1, $records);
            $this->assertEquals(6, $records[0]->count); // user1・最初から数えて6つ
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfJobReEdit(null, $startTime);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // user1・後ろから数えて -2
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getCountOfJobReEdit(null, null, $userIds);
            $this->assertCount(1, $records); // 指定された1ユーザーしか取得されない
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されない
        }
    }

    public function createDataGetIsSupplement()
    {
        $user1 = factory(User::class)->states('client')->create();
        // countの対象となる S3Docs を作成する
        $targetCount = 10;
        $baseDatetime = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            factory(S3Doc::class)->states('certificate')->create([
                'foreign_key' => $user1->id,
                'modified' => $baseDatetime->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('client')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(S3Doc::class)->states('certificate')->create([
            'foreign_key' => $user2->id,
            'modified' => $this->baseDatetime->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
     * 本人確認資料が提出された回数を取得できていること
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
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getIsSupplement();
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getIsSupplement($finishTime);
            $this->assertCount(1, $records); // 該当データ数が0件のものは取得されないこと
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getIsSupplement(null, $startTime);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて-2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getIsSupplement(null, null, $userIds);
            $this->assertCount(1, $records); // 指定のユーザーだけ が取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    public function createDataGetIsSettingThumbnail()
    {
        $user1 = factory(User::class)->states('client')->create();
        // countの対象となる S3Docs を作成する
        $targetCount = 10;
        $baseDatetime = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            factory(S3Doc::class)->states('thumbnail')->create([
                'foreign_key' => $user1->id,
                'created' => $baseDatetime->addSecond()
            ]);
        }
        
        $user2 = factory(User::class)->states('client')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(S3Doc::class)->states('thumbnail')->create([
            'foreign_key' => $user2->id,
            'created' => $this->baseDatetime->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
     * アイコンを設定した回数を取得できていること
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
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getIsSettingThumbnail();
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getIsSettingThumbnail($finishTime);
            $this->assertCount(1, $records); // 該当データ数が0件のものは取得されないこと
            $this->assertEquals(6, $records[0]->count); // 前から数えて6
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getIsSettingThumbnail(null, $startTime);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて-2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getIsSettingThumbnail(null, null, $userIds);
            $this->assertCount(1, $records); // 指定のユーザーだけ が取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    public function createDataGetCountOfApplyPartner()
    {
        $user1 = factory(User::class)->states('client')->create();
        // countの対象となる partners を作成する
        $targetCount = 10;
        $baseDatetime = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            factory(Partner::class)->create([
                'outsourcer_id' => $user1->id,
                'created' => $baseDatetime->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('client')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(Partner::class)->create([
            'outsourcer_id' => $user2->id,
            'created' => $this->baseDatetime->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2', 'targetCount');
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
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getCountOfApplyPartner();
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfApplyPartner($finishTime);
            $this->assertCount(1, $records); // 0件のものは取得されないこと
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfApplyPartner(null, $startTime);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getCountOfApplyPartner(null, null, $userIds);
            $this->assertCount(1, $records); // 指定の1ユーザーしか取得されない
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
    }

    public function createDataGetCountOfPaidDeffer()
    {
        $user1 = factory(User::class)->states('client')->create();
        // countの対象となる point_details・point_logs を作成する
        $targetCount = 10;
        $baseDatetime = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            $pointLog1 = factory(PointLog::class)->states('deferred_payment')->create();
            factory(PointDetail::class)->create([
                'user_id' => $user1->id,
                'point_log_id' => $pointLog1->id,
                'modified' => $baseDatetime->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('client')->create();
        $pointLog2 = factory(PointLog::class)->states('deferred_payment')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(PointDetail::class)->create([
            'user_id' => $user2->id,
            'point_log_id' => $pointLog2->id,
            'modified' => $this->baseDatetime->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
     * 後払いの代金を支払った回数を取得できているかどうか
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
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getCountOfPaidDeffer();
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfPaidDeffer($finishTime);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfPaidDeffer(null, $startTime);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getCountOfPaidDeffer(null, null, $userIds);
            $this->assertCount(1, $records); // 指定のユーザーのみ取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    public function createDataGetCountOfDoneGettingStarted()
    {
        $user1 = factory(User::class)->states('client')->create();
        // countの対象となる audits を作成する
        $targetCount = 10;
        $baseDatetime = $this->baseDatetime->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            factory(Audit::class)->states('user')->create([
                'user_id' => $user1->id,
                'event' => 'updated',
                'old_values' => ['group_id' => 7],
                'created_at' => $baseDatetime->addSecond(),
            ]);
        }
        
        $user2 = factory(User::class)->states('client')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(Audit::class)->states('user')->create([
            'user_id' => $user2->id,
            'event' => 'updated',
            'old_values' => ['group_id' => 7],
            'created_at' => $this->baseDatetime->copy()->addSeconds(10),
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
     * 開始準備が行われた回数を取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfDoneGettingStarted($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfDoneGettingStarted();

        $startTime = $this->baseDatetime->copy()->addSeconds(3);
        $finishTime = $this->baseDatetime->copy()->addSeconds(7);
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = ScoreUserReputationCount::getCountOfDoneGettingStarted();
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfDoneGettingStarted($finishTime);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals(6, $records[0]->count); // user1・最初から数えて6
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = ScoreUserReputationCount::getCountOfDoneGettingStarted(null, $startTime);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // user1・後ろから数えて-2
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = ScoreUserReputationCount::getCountOfDoneGettingStarted(null, null, $userIds);
            $this->assertCount(1, $records); // 指定の1ユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
    }

    /**
     * 全ての行動回数を取得できているか
     */
    public function testGetCountOfAllReputation()
    {
        // Arrange
        // データ作成するごとに、2ユーザーが作成される
        $this->createDataGetCountOfSomeClientTaskReputations(); // 取引（タスク）に関わるテストデータの作成
        $this->createDataGetCountOfSomeClientProjectReputations(); // 取引（プロジェクト）に関わるテストデータの作成
        $this->createDataGetCountOfSomeUserReputations(); // 初回審査・自己紹介設定に関わるテストデータの作成
        $this->createDataGetCountOfJobAccept(); // 仕事が承認されたデータを取得する ためのデータ
        $this->createDataGetCountOfJobReEdit(); // 差し戻し再申請に関わるテストデータの作成
        $this->createDataGetIsSupplement(); // 本人確認資料を提出したかどうか のデータ
        $this->createDataGetIsSettingThumbnail(); // アイコンを設定したかどうか のデータ
        $this->createDataGetCountOfApplyPartner(); // パートナー申請をした回数を取得する ためのデータ
        $this->createDataGetCountOfPaidDeffer(); // 後払いの代金を支払った回数を取得する ためのデータ
        $this->createDataGetCountOfDoneGettingStarted(); // 開始準備済みかどうかに関わるテストデータの作成

        // Act
        $recordsGetCountOfSomeClientReputations = ScoreUserReputationCount::getCountOfSomeClientReputations(); // 取引に関わる回数
        $recordsGetCountOfSomeUserReputations = ScoreUserReputationCount::getCountOfSomeUserReputations(); // 初回審査・自己紹介設定
        $recordsGetCountOfJobAccept = ScoreUserReputationCount::getCountOfJobAccept(); // 仕事が承認された回数
        $recordsGetCountOfJobReEdit = ScoreUserReputationCount::getCountOfJobReEdit(); // 差し戻された仕事を修正して再申請した回数
        $recordsGetIsSupplement = ScoreUserReputationCount::getIsSupplement(); // 本人確認資料を提出したかどうか
        $recordsGetIsSettingThumbnail = ScoreUserReputationCount::getIsSettingThumbnail(); // アイコンを設定したかどうか
        $recordsGetCountOfApplyPartner = ScoreUserReputationCount::getCountOfApplyPartner(); // パートナー申請した回数
        $recordsGetCountOfPaidDeffer = ScoreUserReputationCount::getCountOfPaidDeffer(); // 後払いの代金を支払った回数
        $recordsGetCountOfDoneGettingStarted = ScoreUserReputationCount::getCountOfDoneGettingStarted(); // 開始準備したかどうか

        $recordsOfAll = ScoreUserReputationCount::getCountOfAllReputation();

        $resultSumRecordsCount = count($recordsGetCountOfSomeClientReputations)
            + count($recordsGetCountOfSomeUserReputations)
            + count($recordsGetCountOfJobAccept)
            + count($recordsGetCountOfJobReEdit)
            + count($recordsGetIsSupplement)
            + count($recordsGetIsSettingThumbnail)
            + count($recordsGetCountOfApplyPartner)
            + count($recordsGetCountOfPaidDeffer)
            + count($recordsGetCountOfDoneGettingStarted);
        $resultAllRecordsCount = count($recordsOfAll);

        // Assert
        $this->assertSame($resultSumRecordsCount, $resultAllRecordsCount); // 全体の配列数
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
