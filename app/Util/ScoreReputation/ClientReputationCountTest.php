<?php

namespace Tests\Unit\Domain\ScoreReputation;

use App\Domain\ScoreReputation\ClientReputationCount;
use App\Models\Group;
use App\Models\Job;
use App\Models\JobRole;
use App\Models\Partner;
use App\Models\PointDetail;
use App\Models\PointLog;
use App\Models\S3Doc;
use App\Models\ScoreReputation;
use App\Models\SellingPoint;
use App\Models\TaskTrade;
use App\Models\Trade;
use App\Models\User;
use OwenIt\Auditing\Models\Audit;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ClientReputationCountTest extends TestCase
{
    use DatabaseTransactions;

    private $clientReputationCount;
    private $baseDatetime;
    private $baseDatetimeDb;

    public function setUp()
    {
        parent::setUp();
        $this->clientReputationCount = new ClientReputationCount();
        $this->baseDatetime = Carbon::now()->timezone('Asia/Tokyo'); // 現在時刻を設定する。
        $this->baseDatetimeDb = Carbon::now()->timezone('UTC'); // shuftiのdbの時刻を設定する
    }

    /**
     * クライアントの全ての行動テストデータを作成する
     * 何かの行動回数を取得する関数を作成した場合は、下記に追加してください
     */
    public function createDataAllClientReputation()
    {
        $this->createDataGetCountOfSomeTaskTrades(); // 取引（タスク）に関わるテストデータの作成
        $this->createDataGetCountOfSomeProjectTrades(); // 取引（プロジェクト）に関わるテストデータの作成
        $this->createDataGetCountOfRegistration(); // 会員登録したかどうかを取得するテストデータの作成
        $this->createDataGetCountOfInitScreening(); // 初回審査 を行なったかどうかを取得するテストデータの作成
        $this->createDataGetCountOfSetProfile(); // 自己紹介が設定されているかどうかを取得するテストデータの作成
        $this->createDataGetCountOfJobAccept(); // 仕事が承認されたデータを取得する ためのデータ
        $this->createDataGetCountOfJobReEdit(); // 差し戻し再申請に関わるテストデータの作成
        $this->createDataGetCountOfSupplement(); // 本人確認資料を提出した回数を取得するためのテストデータの作成
        $this->createDataGetCountOfSettingThumbnail(); // アイコンを設定した回数を取得するためのテストデータの作成
        $this->createDataGetCountOfApplyPartner(); // パートナー申請をした回数を取得する ためのデータ
        $this->createDataGetCountOfPaidDeffer(); // 後払いの代金を支払った回数を取得する ためのデータ
        $this->createDataGetCountOfGettingStarted(); // 開始準備済みかどうかに関わるテストデータの作成
    }

    /**
     * 全ての行動回数を取得できているか
     */
    public function testGetAllReputationCount()
    {
        // Arrange
        $this->createDataAllClientReputation();

        // Act
        // 1つ1つのメソッドで取得した合計
        $recordsGetCountOfSomeTaskTrades = $this->clientReputationCount->getCountOfSomeTaskTrades(); // 取引：タスク関連の行動回数
        $recordsGetCountOfSomeProjectTrades = $this->clientReputationCount->getCountOfSomeProjectTrades(); // 取引：プロジェクト関連の行動回数
        $recordsGetCountOfRegistration = $this->clientReputationCount->getCountOfRegistration(); // 会員登録したかどうか
        $recordsGetCountOfInitScreening = $this->clientReputationCount->getCountOfInitScreening(); // 初回審査を行なったかどうか
        $recordsGetCountOfSetProfile = $this->clientReputationCount->getCountOfSetProfile(); // 自己紹介が設定されているかどうか
        $recordsGetCountOfJobAccept = $this->clientReputationCount->getCountOfJobAccept(); // 仕事が承認された回数
        $recordsGetCountOfJobReEdit = $this->clientReputationCount->getCountOfJobReEdit(); // 差し戻された仕事を修正して再申請した回数
        $recordsGetCountOfSupplement = $this->clientReputationCount->getCountOfSupplement(); // 本人確認資料を提出した回数
        $recordsGetCountOfSettingThumbnail = $this->clientReputationCount->getCountOfSettingThumbnail(); // アイコンを設定した回数
        $recordsGetCountOfApplyPartner = $this->clientReputationCount->getCountOfApplyPartner(); // パートナー申請した回数
        $recordsGetCountOfPaidDeffer = $this->clientReputationCount->getCountOfPaidDeffer(); // 後払いの代金を支払った回数
        $recordsGetCountOfGettingStarted = $this->clientReputationCount->getCountOfGettingStarted(); // 開始準備した回数

        $resultSumRecordsCount = count($recordsGetCountOfSomeTaskTrades)
            + count($recordsGetCountOfSomeProjectTrades)
            + count($recordsGetCountOfRegistration)
            + count($recordsGetCountOfInitScreening)
            + count($recordsGetCountOfSetProfile)
            + count($recordsGetCountOfJobAccept)
            + count($recordsGetCountOfJobReEdit)
            + count($recordsGetCountOfSupplement)
            + count($recordsGetCountOfSettingThumbnail)
            + count($recordsGetCountOfApplyPartner)
            + count($recordsGetCountOfPaidDeffer)
            + count($recordsGetCountOfGettingStarted);

        // getAllReputationCount() で取得した場合の合計
        $recordsOfAll = $this->clientReputationCount->getAllReputationCount();
        $resultAllRecordsCount = count($recordsOfAll);

        // Assert
        $this->assertSame($resultSumRecordsCount, $resultAllRecordsCount); // 全体の配列数
    }

    public function providerTestGetTargetReputationCount()
    {
        return
        [
            '1つの行動のみ' => [
                [ScoreReputation::ID_CLIENT_SETTING_THUMBNAIL] // 【初】アイコンを設定する
            ],
            'タスク取引関連の行動を指定する' => [
                [
                    ScoreReputation::ID_TASK_ACCEPT_DELIVERY, // タスク：納品物の検品をする（承認）
                    ScoreReputation::ID_TASK_REJECT_DELIVERY, // タスク：納品物の検品をする（非承認）
                ]
            ],
            'プロジェクト取引関連の行動を指定する' => [
                [
                    ScoreReputation::ID_PROJECT_ORDER, // プロジェクト：発注する
                    ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY, // プロジェクト：納品物の検品をする（承認）
                    ScoreReputation::ID_PROJECT_REJECT_DELIVERY, // プロジェクト：納品物の検品をする（差し戻し）
                    ScoreReputation::ID_CLIENT_PROJECT_FINISH, // プロジェクト：評価する
                    ScoreReputation::ID_PROJECT_REORDER, // プロジェクト：再発注する
                ]
            ],
            '複数の行動を指定する' => [
                [
                    ScoreReputation::ID_CLIENT_GETTING_STARTED, // 【初】開始準備
                    ScoreReputation::ID_CLIENT_INIT_SCREENING, // 【初】初回審査
                    ScoreReputation::ID_CLIENT_INIT_SCREENING, // 【初】本人確認提出
                    // タスク取引関連の行動（差分を含む場合の確認）
                    ScoreReputation::ID_TASK_ACCEPT_DELIVERY, // タスク：納品物の検品をする（承認）
                    ScoreReputation::ID_TASK_REJECT_DELIVERY, // タスク：納品物の検品をする（非承認）
                    // プロジェクト取引関連の行動（差分を含む場合の確認）
                    ScoreReputation::ID_PROJECT_ORDER, // プロジェクト：発注する
                    ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY, // プロジェクト：納品物の検品をする（承認）
                    ScoreReputation::ID_PROJECT_REJECT_DELIVERY, // プロジェクト：納品物の検品をする（差し戻し）
                    ScoreReputation::ID_CLIENT_PROJECT_FINISH, // プロジェクト：評価する
                    ScoreReputation::ID_PROJECT_REORDER, // プロジェクト：再発注する
                ]
            ]
        ];
    }

    /**
     * 指定された行動回数を取得できているかどうか
     *
     * @dataProvider providerTestGetTargetReputationCount
     * @param array $targetReputations
     */
    public function testGetTargetReputationCount($targetReputations)
    {
        // Arrange
        $this->createDataAllClientReputation();

        // Act
        $records = $this->clientReputationCount->getTargetReputationCount($targetReputations);

        // Assert
        // 指定された行動郡のみが取得されること
        foreach ($records as $record) {
            $this->assertContains($record->reputation_id, $targetReputations);
        }
        // 指定された各行動が取得されていること
        foreach ($targetReputations as $targetReputation) {
            $targetRecordKeys = $this->getTargetReputationId($records, $targetReputation);
            $this->assertNotEmpty($targetRecordKeys);
        }
    }

    /**
     * タスク取引関連・プロジェクト取引関連の行動は、全て指定しないと何も取得されないこと
     */
    public function testGetTargetReputationCountInvalid()
    {
        // Arrange
        $this->createDataAllClientReputation();

        // Act & Assert
        // タスク取引関連の行動 が全て指定されていない
        $targetReputations = [ScoreReputation::ID_TASK_ACCEPT_DELIVERY]; // タスク：納品物の検品をする（承認）
        $records = $this->clientReputationCount->getTargetReputationCount($targetReputations);
        $this->assertEmpty($records);

        // プロジェクト取引関連の行動 が全て指定されていない
        $targetReputations = [
            ScoreReputation::ID_PROJECT_ORDER, // プロジェクト：発注する
            ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY, // プロジェクト：納品物の検品をする（承認）
        ];
        $records = $this->clientReputationCount->getTargetReputationCount($targetReputations);
        $this->assertEmpty($records);
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

    public function createDataGetCountOfSomeTaskTrades()
    {
        $user1 = factory(User::class)->states('client')->create();
        // 期間指定の対象となるjob_rolesと、countの対象となるtask_tradesを作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            $modifiedTimeDate = $baseDatetimeDb->addSecond();
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

        $user2 = factory(User::class)->states('client')->create();
        // 10s後に期間設定したデータをそれぞれ1件のみ作成
        $jobRole2 = factory(JobRole::class)->states('outsourcer')->create([
            'user_id' => $user2->id,
            'job_id' => random_int(100, 100000),
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);
        // タスク：納品物が検品され、承認されたテストデータ
        factory(TaskTrade::class)->states('delivery', 'delivery_accept')->create([
            'job_id' => $jobRole2->job_id,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);
        // タスク：納品物が検品され、非承認されたテストデータ
        factory(TaskTrade::class)->states('delivery', 'delivery_reject')->create([
            'job_id' => $jobRole2->job_id,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);

        return compact('user1', 'user2', 'targetCount');
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
    public function testGetCountOfSomeTaskTrades($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfSomeTaskTrades();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = $this->clientReputationCount->getCountOfSomeTaskTrades();
            // タスク：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_ACCEPT_DELIVERY);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount, $records[$targetRecordKeys[0]]->count); // user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2

            // タスク：納品物の検品をする（非承認） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_REJECT_DELIVERY);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount, $records[$targetRecordKeys[0]]->count); // user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2
        }
        if ($hasFinishTime) { // finishTime だけ渡された場合
            $records = $this->clientReputationCount->getCountOfSomeTaskTrades($finishTime);
            // タスク：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_ACCEPT_DELIVERY);
            $this->assertCount(1, $targetRecordKeys); // 0件の場合は取得されないこと
            $this->assertEquals(6, $records[$targetRecordKeys[0]]->count); // 最初から数えて6つ, user1

            // タスク：納品物の検品をする（非承認） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_REJECT_DELIVERY);
            $this->assertCount(1, $targetRecordKeys); // 0件の場合は取得されないこと
            $this->assertEquals(6, $records[$targetRecordKeys[0]]->count); // 最初から数えて6つ, user1
        }
        if ($hasStartTime) { // startTime だけ渡された場合
            $records = $this->clientReputationCount->getCountOfSomeTaskTrades(null, $startTime);
            // タスク：納品物の検品をする（承認） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_ACCEPT_DELIVERY);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount - 2, $records[$targetRecordKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2

            // タスク：納品物の検品をする（非承認） の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_REJECT_DELIVERY);
            $this->assertCount(2, $targetRecordKeys);
            $this->assertEquals($expectMaxCount - 2, $records[$targetRecordKeys[0]]->count); // 後ろから数えて-2, user1
            $this->assertEquals(1, $records[$targetRecordKeys[1]]->count); // user2
        }
        if ($hasUserIds) {
            $records = $this->clientReputationCount->getCountOfSomeTaskTrades(null, null, $userIds);
            // タスク：納品物の検品をする（承認） の回数が取得できていること を確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_ACCEPT_DELIVERY);
            $this->assertCount(1, $targetRecordKeys); // 指定されたユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[$targetRecordKeys[0]]->user_id); // user1 しか取得されないこと

            // タスク：納品物の検品をする（非承認）の回数が取得できていること を確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_TASK_REJECT_DELIVERY);
            $this->assertCount(1, $targetRecordKeys); // 指定されたユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[$targetRecordKeys[0]]->user_id); // user1 しか取得されないこと
        }
    }

    public function createDataGetCountOfSomeProjectTrades()
    {
        $user1 = factory(User::class)->states('client')->create();
        // 期間指定の対象となるjob_rolesと、countの対象となるtradesを作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            $modifiedTimeDate = $baseDatetimeDb->addSecond();
            $jobRole1 = factory(JobRole::class)->states('outsourcer')->create([
                'user_id' => $user1->id,
                'job_id' => random_int(100, 100000),
                'modified' => $modifiedTimeDate,
            ]);
            // プロジェクト：発注する のテストデータ
            factory(Trade::class)->states('order')->create([
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
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);
        // プロジェクト：発注する のテストデータ
        factory(Trade::class)->states('order')->create([
            'job_id' => $jobRole2->job_id,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);
        // プロジェクト：納品物の検品をする（承認） のテストデータ
        factory(Trade::class)->states('delivery', 'delivery_accept')->create([
            'job_id' => $jobRole2->job_id,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);
        // プロジェクト：納品物の検品をする（差し戻し） のテストデータ
        factory(Trade::class)->states('delivery', 'delivery_reject')->create([
            'job_id' => $jobRole2->job_id,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);
        // プロジェクト：評価する のテストデータ
        factory(Trade::class)->states('finish')->create([
            'job_id' => $jobRole2->job_id,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);
        // プロジェクト：再発注する のテストデータ
        factory(Trade::class)->states('reorder')->create([
            'job_id' => $jobRole2->job_id,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10),
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

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = $this->clientReputationCount->getCountOfSomeProjectTrades();
            // プロジェクト：発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_ORDER);
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
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_CLIENT_PROJECT_FINISH);
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
            $records = $this->clientReputationCount->getCountOfSomeProjectTrades($finishTime);
            // プロジェクト：発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_ORDER);
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
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_CLIENT_PROJECT_FINISH);
            $this->assertCount(1, $targetRecordKeys); // 0件の場合は取得されない
            $this->assertEquals(6, $records[$targetRecordKeys[0]]->count); // 最初から数えて6つ, user1

            // プロジェクト：再発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REORDER);
            $this->assertCount(1, $targetRecordKeys); // 0件の場合は取得されない
            $this->assertEquals(6, $records[$targetRecordKeys[0]]->count); // 最初から数えて6つ, user1
        }
        if ($hasStartTime) { // startTime だけ渡された場合
            $records = $this->clientReputationCount->getCountOfSomeProjectTrades(null, $startTime);
            // プロジェクト：発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_ORDER);
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
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_CLIENT_PROJECT_FINISH);
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
            $records = $this->clientReputationCount->getCountOfSomeProjectTrades(null, null, $userIds);
            // プロジェクト：発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_ORDER);
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
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_CLIENT_PROJECT_FINISH);
            $this->assertCount(1, $targetRecordKeys); // 指定されたユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[$targetRecordKeys[0]]->user_id); // user1 しか取得されないこと

            // プロジェクト：再発注する の回数が取得できていることを確認
            $targetRecordKeys = $this->getTargetReputationId($records, ScoreReputation::ID_PROJECT_REORDER);
            $this->assertCount(1, $targetRecordKeys); // 指定されたユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[$targetRecordKeys[0]]->user_id); // user1 しか取得されないこと
        }
    }

    public function createDataGetCountOfRegistration()
    {
        $user1 = factory(User::class)->states('client')->create([
            'created' => $this->baseDatetimeDb->copy(),
        ]);
        
        $user2 = factory(User::class)->states('client')->create([
            'created' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);

        return compact('user1', 'user2');
    }

    /**
     * 【初】会員登録したかどうかが取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfRegistration($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfRegistration();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = $this->clientReputationCount->getCountOfRegistration();
            $this->assertCount(2, $records);
            $this->assertEquals(1, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = $this->clientReputationCount->getCountOfRegistration($finishTime);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 の件数が取得されること
            $this->assertEquals(1, $records[0]->count); // user1
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = $this->clientReputationCount->getCountOfRegistration(null, $startTime);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user2']->id, $records[0]->user_id); // user2 の件数が取得されること
            $this->assertEquals(1, $records[0]->count); // user1
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = $this->clientReputationCount->getCountOfRegistration(null, null, $userIds);
            $this->assertCount(1, $records); // 指定された1ユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
    }

    public function createDataGetCountOfInitScreening()
    {
        $user1 = factory(User::class)->states('client', 'antisocial_ok')->create([
            'antisocial_check_date' => $this->baseDatetimeDb->copy(),
            'created' => $this->baseDatetimeDb->copy(),
        ]);
        
        $user2 = factory(User::class)->states('client', 'antisocial_ok')->create([
            'antisocial_check_date' => $this->baseDatetimeDb->copy()->addSeconds(10),
            'created' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);

        return compact('user1', 'user2');
    }

    /**
     * 【初】初回審査 を行なったかどうかが取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfInitScreening($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfInitScreening();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = $this->clientReputationCount->getCountOfInitScreening();
            $this->assertCount(2, $records);
            $this->assertEquals(1, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = $this->clientReputationCount->getCountOfInitScreening($finishTime);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 の件数が取得されること
            $this->assertEquals(1, $records[0]->count); // user1
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = $this->clientReputationCount->getCountOfInitScreening(null, $startTime);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user2']->id, $records[0]->user_id); // user2 の件数が取得されること
            $this->assertEquals(1, $records[0]->count); // user1
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = $this->clientReputationCount->getCountOfInitScreening(null, null, $userIds);
            $this->assertCount(1, $records); // 指定された1ユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
    }

    public function createDataGetCountOfSetProfile()
    {
        $user1 = factory(User::class)->states('client')->create();
        // 0s後に期間設定したデータを1件のみ作成（ユニークキー制約のため）
        factory(SellingPoint::class)->create([
            'user_id' => $user1->id,
            'modified' => $this->baseDatetimeDb->copy(),
        ]);

        $user2 = factory(User::class)->states('client')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(SellingPoint::class)->create([
            'user_id' => $user2->id,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);

        return compact('user1', 'user2');
    }

    /**
     * 自己紹介が設定されているかどうかが取得できること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfSetProfile($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfSetProfile();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = $this->clientReputationCount->getCountOfSetProfile();
            $this->assertCount(2, $records);
            $this->assertEquals(1, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = $this->clientReputationCount->getCountOfSetProfile($finishTime);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 の件数が取得されること
            $this->assertEquals(1, $records[0]->count); // user1
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = $this->clientReputationCount->getCountOfSetProfile(null, $startTime);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user2']->id, $records[0]->user_id); // user2 の件数が取得されること
            $this->assertEquals(1, $records[0]->count); // user1
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = $this->clientReputationCount->getCountOfSetProfile(null, null, $userIds);
            $this->assertCount(1, $records); // 指定された1ユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
    }

    public function createDataGetCountOfJobAccept()
    {
        $user1 = factory(User::class)->states('client')->create();
        // countの対象となる jobs・job_roles を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            $job1 = factory(Job::class)->states('active')->create([
                'activated_date' => $baseDatetimeDb->addSecond()
            ]);
            factory(JobRole::class)->states('outsourcer')->create([
                'user_id' => $user1->id,
                'job_id' => $job1->id
            ]);
        }

        $user2 = factory(User::class)->states('client')->create();
        // 10s後に期間設定したデータを1件のみ作成
        $job2 = factory(Job::class)->states('active')->create([
            'activated_date' => $this->baseDatetimeDb->copy()->addSeconds(10)
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

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = $this->clientReputationCount->getCountOfJobAccept();
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = $this->clientReputationCount->getCountOfJobAccept($finishTime);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals(6, $records[0]->count); // user1・最初から数えて6つ
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = $this->clientReputationCount->getCountOfJobAccept(null, $startTime);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // user1・後ろから数えて -2
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = $this->clientReputationCount->getCountOfJobAccept(null, null, $userIds);
            $this->assertCount(1, $records); // 指定された1ユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
    }

    public function createDataGetCountOfJobReEdit()
    {
        $user1 = factory(User::class)->states('client')->create();
        // countの対象となる audits を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
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
                'created_at' => $baseDatetimeDb->addSecond()
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
            'created_at' => $this->baseDatetimeDb->copy()->addSeconds(10)
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

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = $this->clientReputationCount->getCountOfJobReEdit();
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = $this->clientReputationCount->getCountOfJobReEdit($finishTime);
            $this->assertCount(1, $records);
            $this->assertEquals(6, $records[0]->count); // user1・最初から数えて6つ
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = $this->clientReputationCount->getCountOfJobReEdit(null, $startTime);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // user1・後ろから数えて -2
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = $this->clientReputationCount->getCountOfJobReEdit(null, null, $userIds);
            $this->assertCount(1, $records); // 指定された1ユーザーしか取得されない
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されない
        }
    }

    public function createDataGetCountOfSupplement()
    {
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        $user1 = factory(User::class)->states('client')->create([
            'verification_expiration' => '2999-12-31 00:00:00',
            'modified' => $baseDatetimeDb->addSecond(),
        ]);
        // countの対象となる user を作成する
        $targetCount = 10;
        for ($index = 1; $index < $targetCount; $index++) { // 他箇所と異なり、上で1user作成しているので、$index = 1 からのスタート
            factory(User::class)->states('client')->create([
                'verification_expiration' => '2999-12-31 00:00:00',
                'modified' => $baseDatetimeDb->addSecond(),
            ]);
        }

        return compact('user1', 'targetCount');
    }

    /**
     * 本人確認を行なったかどうかが取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfSupplement($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfSupplement();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = $this->clientReputationCount->getCountOfSupplement();
            $this->assertCount($expectMaxCount, $records); // 10のuserが合致する
            for ($index = 0; $index < count($records); $index++) {
                $this->assertEquals(1, $records[$index]->count); // 条件に合致すれば、count = 1
            }
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = $this->clientReputationCount->getCountOfSupplement($finishTime);
            $this->assertCount(6, $records); // 最初から数えて6
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = $this->clientReputationCount->getCountOfSupplement(null, $startTime);
            $this->assertCount($expectMaxCount - 2, $records); // 後ろから数えて-2
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = $this->clientReputationCount->getCountOfSupplement(null, null, $userIds);
            $this->assertCount(1, $records); // 指定のユーザーだけ が取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    public function createDataGetCountOfSettingThumbnail()
    {
        $user1 = factory(User::class)->states('client')->create();
        // countの対象となる S3Docs を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            factory(S3Doc::class)->states('thumbnail')->create([
                'foreign_key' => $user1->id,
                'created' => $baseDatetimeDb->addSecond()
            ]);
        }
        
        $user2 = factory(User::class)->states('client')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(S3Doc::class)->states('thumbnail')->create([
            'foreign_key' => $user2->id,
            'created' => $this->baseDatetimeDb->copy()->addSeconds(10)
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
    public function testGetCountOfSettingThumbnail($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfSettingThumbnail();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = $this->clientReputationCount->getCountOfSettingThumbnail();
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = $this->clientReputationCount->getCountOfSettingThumbnail($finishTime);
            $this->assertCount(1, $records); // 該当データ数が0件のものは取得されないこと
            $this->assertEquals(6, $records[0]->count); // 前から数えて6
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = $this->clientReputationCount->getCountOfSettingThumbnail(null, $startTime);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて-2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = $this->clientReputationCount->getCountOfSettingThumbnail(null, null, $userIds);
            $this->assertCount(1, $records); // 指定のユーザーだけ が取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    public function createDataGetCountOfApplyPartner()
    {
        $user1 = factory(User::class)->states('client')->create();
        // countの対象となる partners を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            factory(Partner::class)->create([
                'outsourcer_id' => $user1->id,
                'created' => $baseDatetimeDb->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('client')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(Partner::class)->create([
            'outsourcer_id' => $user2->id,
            'created' => $this->baseDatetimeDb->copy()->addSeconds(10)
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

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = $this->clientReputationCount->getCountOfApplyPartner();
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = $this->clientReputationCount->getCountOfApplyPartner($finishTime);
            $this->assertCount(1, $records); // 0件のものは取得されないこと
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = $this->clientReputationCount->getCountOfApplyPartner(null, $startTime);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = $this->clientReputationCount->getCountOfApplyPartner(null, null, $userIds);
            $this->assertCount(1, $records); // 指定の1ユーザーしか取得されない
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
    }

    public function createDataGetCountOfPaidDeffer()
    {
        $user1 = factory(User::class)->states('client')->create();
        // countの対象となる point_details・point_logs を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            $pointLog1 = factory(PointLog::class)->states('deferred_payment')->create();
            factory(PointDetail::class)->create([
                'user_id' => $user1->id,
                'point_log_id' => $pointLog1->id,
                'modified' => $baseDatetimeDb->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('client')->create();
        $pointLog2 = factory(PointLog::class)->states('deferred_payment')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(PointDetail::class)->create([
            'user_id' => $user2->id,
            'point_log_id' => $pointLog2->id,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10)
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

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = $this->clientReputationCount->getCountOfPaidDeffer();
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = $this->clientReputationCount->getCountOfPaidDeffer($finishTime);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = $this->clientReputationCount->getCountOfPaidDeffer(null, $startTime);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = $this->clientReputationCount->getCountOfPaidDeffer(null, null, $userIds);
            $this->assertCount(1, $records); // 指定のユーザーのみ取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    public function createDataGetCountOfGettingStarted()
    {
        $user1 = factory(User::class)->states('client')->create([
            'group_id' => Group::GROUP_ID_USER,
            'modified' => $this->baseDatetimeDb->copy(),
        ]);
        
        $user2 = factory(User::class)->states('client')->create([
            'group_id' => Group::GROUP_ID_USER,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);

        return compact('user1', 'user2');
    }

    /**
     * 開始準備が行われた回数を取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfGettingStarted($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfGettingStarted();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする

        // Act & Assert
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 引数の指定がない場合
            $records = $this->clientReputationCount->getCountOfGettingStarted();
            $this->assertCount(2, $records);
            $this->assertEquals(1, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // finishTime が渡された場合
            $records = $this->clientReputationCount->getCountOfGettingStarted($finishTime);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1
        }
        if ($hasStartTime) { // startTime が渡された場合
            $records = $this->clientReputationCount->getCountOfGettingStarted(null, $startTime);
            $this->assertCount(1, $records);
            $this->assertEquals($createData['user2']->id, $records[0]->user_id); // user2
        }
        if ($hasUserIds) { // userIds が渡された場合
            $records = $this->clientReputationCount->getCountOfGettingStarted(null, null, $userIds);
            $this->assertCount(1, $records); // 指定の1ユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
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
