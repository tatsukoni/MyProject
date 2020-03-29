<?php

namespace Tests\Unit\Domain\ScoreReputation;

use App\Domain\ScoreReputation\WorkerReputationCount;
use App\Models\Group;
use App\Models\Job;
use App\Models\JobRole;
use App\Models\PointDetail;
use App\Models\PointLog;
use App\Models\Rating;
use App\Models\User;
use App\Models\S3Doc;
use App\Models\ScoreReputation;
use App\Models\SellingPoint;
use App\Models\TaskTrade;
use App\Models\Trade;
use App\Models\Thread;
use App\Models\Wall;
use App\Models\WorkerReward;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WorkerReputationCountTest extends TestCase
{
    use DatabaseTransactions;

    private $workerReputationCount;
    private $baseDatetime;
    private $baseDatetimeDb;

    public function setUp()
    {
        parent::setUp();
        $this->workerReputationCount = new WorkerReputationCount();
        $this->baseDatetime = Carbon::now()->timezone('Asia/Tokyo'); // 現在時刻を設定する。
        $this->baseDatetimeDb = Carbon::now()->timezone('UTC'); // shuftiのdbの時刻を設定する
    }

    /**
     * private な各メソッドをテストするため
     */
    public function getAccessibleMethod(string $methodName)
    {
        return $this->unprotect($this->workerReputationCount, $methodName);
    }

    /**
     * ワーカーの全ての行動テストデータを作成する
     * 何かの行動回数を取得する関数を作成した場合は、下記に追加してください
     */
    public function createDataAllWorkerReputation()
    {
        $this->createDataGetCountOfRegistration(); // 【初】会員登録する_テストデータ作成
        $this->createDataGetCountOfGettingStarted(); // 【初】開始準備_テストデータ作成
        $this->createDataGetCountOfPostQuestion(); // 質問を投稿する_テストデータ作成
        $this->createDataGetCountOfProposal(); // 仕事に応募する_テストデータ作成
        $this->createDataGetCountOfTaskDelivery(); // タスク：納品する_テストデータ作成
        $this->createDataGetCountOfTaskGetReward(); // タスク：報酬を獲得する_テストデータ作成
        $this->createDataGetCountOfProjectDelivery(); // プロジェクト：納品する_テストデータ作成
        $this->createDataGetCountOfProjectGetRewards(); // プロジェクト：報酬を獲得する_テストデータ作成
        $this->createDataGetCountOfRating(); // プロジェクト：評価する_テストデータ作成
        $this->createDataGetCountOfAcceptReorder(); // プロジェクト：再受注する_テストデータ作成
        $this->createDataGetCountOfSettingThumbnail(); // 【初】アイコンを設定する_テストデータ作成
        $this->createDataGetCountOfSetProfile(); // 【初】自己紹介を設定する_テストデータ作成
        $this->createDataGetCountOfSetSupplement(); // 【初】本人確認を設定する_テストデータ作成
        $this->createDataGetCountOfReceiveReward(); // 報酬を受け取る_テストデータ作成
    }

    /**
     * 全ての行動回数を取得できているか
     */
    public function testGetAllReputationCount()
    {
        // Arrange
        $this->createDataAllWorkerReputation();

        // Act
        // 1つ1つのメソッドで取得した合計
        $recordsGetCountOfRegistration = $this->getAccessibleMethod('getCountOfRegistration')->invoke($this->workerReputationCount, []); // 【初】会員登録する
        $recordsGetCountOfGettingStarted = $this->getAccessibleMethod('getCountOfGettingStarted')->invoke($this->workerReputationCount, []); // 【初】開始準備
        $recordsGetCountOfPostQuestion = $this->getAccessibleMethod('getCountOfPostQuestion')->invoke($this->workerReputationCount, []); // 質問を投稿する
        $recordsGetCountOfProposal = $this->getAccessibleMethod('getCountOfProposal')->invoke($this->workerReputationCount, []); // 仕事に応募する
        $recordsGetCountOfTaskDelivery = $this->getAccessibleMethod('getCountOfTaskDelivery')->invoke($this->workerReputationCount, []); // タスク：納品する
        $recordsGetCountOfTaskGetReward = $this->getAccessibleMethod('getCountOfTaskGetReward')->invoke($this->workerReputationCount, []); // タスク：報酬を獲得する
        $recordsGetCountOfProjectDelivery = $this->getAccessibleMethod('getCountOfProjectDelivery')->invoke($this->workerReputationCount, []); // プロジェクト：納品する
        $recordsGetCountOfProjectGetRewards = $this->getAccessibleMethod('getCountOfProjectGetRewards')->invoke($this->workerReputationCount, []); // プロジェクト：報酬を獲得する
        $recordsGetCountOfRating = $this->getAccessibleMethod('getCountOfRating')->invoke($this->workerReputationCount, []); // プロジェクト：評価する
        $recordsGetCountOfAcceptReorder = $this->getAccessibleMethod('getCountOfAcceptReorder')->invoke($this->workerReputationCount, []); // プロジェクト：再受注する
        $recordsGetCountOfSettingThumbnail = $this->getAccessibleMethod('getCountOfSettingThumbnail')->invoke($this->workerReputationCount, []); // 【初】アイコンを設定する
        $recordsGetCountOfSetProfile = $this->getAccessibleMethod('getCountOfSetProfile')->invoke($this->workerReputationCount, []); // 【初】自己紹介を設定する
        $recordsGetCountOfSetSupplement = $this->getAccessibleMethod('getCountOfSetSupplement')->invoke($this->workerReputationCount, []); // 【初】本人確認を設定する
        $recordsGetCountOfReceiveReward = $this->getAccessibleMethod('getCountOfReceiveReward')->invoke($this->workerReputationCount, []); // 報酬を受け取る

        $resultSumRecordsCount = count($recordsGetCountOfRegistration)
            + count($recordsGetCountOfGettingStarted)
            + count($recordsGetCountOfPostQuestion)
            + count($recordsGetCountOfProposal)
            + count($recordsGetCountOfTaskDelivery)
            + count($recordsGetCountOfTaskGetReward)
            + count($recordsGetCountOfProjectDelivery)
            + count($recordsGetCountOfProjectGetRewards)
            + count($recordsGetCountOfRating)
            + count($recordsGetCountOfAcceptReorder)
            + count($recordsGetCountOfSettingThumbnail)
            + count($recordsGetCountOfSetProfile)
            + count($recordsGetCountOfSetSupplement)
            + count($recordsGetCountOfReceiveReward);

        // getAllReputationCount() で取得した場合の合計
        $recordsOfAll = $this->workerReputationCount->getAllReputationCount([]);
        $resultAllRecordsCount = count($recordsOfAll);

        // Assert
        $this->assertSame($resultSumRecordsCount, $resultAllRecordsCount); // 全体の配列数
    }

    public function providerTestGetTargetReputationCount()
    {
        return
        [
            '1つの行動のみ' => [
                [ScoreReputation::ID_WORKER_SETTING_THUMBNAIL] // 【初】アイコンを設定する
            ],
            '複数の行動を指定する' => [
                [
                    ScoreReputation::ID_POST_QUESTION, // 質問を投稿する
                    ScoreReputation::ID_TASK_DELIVERY, // タスク：納品する
                    ScoreReputation::ID_PROJECT_GET_REWARD, // プロジェクト：報酬を獲得する
                    ScoreReputation::ID_PROJECT_ACCEPT_REORDER, // プロジェクト：再受注する
                    ScoreReputation::ID_WORKER_SET_SUPPLEMENT, // 【初】本人確認を設定する
                ]
            ],
            '全ての行動を指定する' => [
                [
                    ScoreReputation::ID_WORKER_REGISTRATION,
                    ScoreReputation::ID_WORKER_GETTING_STARTED,
                    ScoreReputation::ID_POST_QUESTION,
                    ScoreReputation::ID_PROPOSAL,
                    ScoreReputation::ID_TASK_DELIVERY,
                    ScoreReputation::ID_TASK_GET_REWARD,
                    ScoreReputation::ID_PROJECT_DELIVERY,
                    ScoreReputation::ID_PROJECT_GET_REWARD,
                    ScoreReputation::ID_WORKER_PROJECT_RATING,
                    ScoreReputation::ID_PROJECT_ACCEPT_REORDER,
                    ScoreReputation::ID_WORKER_SETTING_THUMBNAIL,
                    ScoreReputation::ID_WORKER_SET_PROFILE,
                    ScoreReputation::ID_WORKER_SET_SUPPLEMENT,
                    ScoreReputation::ID_RECEIVE_REWARD
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
        $this->createDataAllWorkerReputation();

        // Act
        $records = $this->workerReputationCount->getTargetReputationCount($targetReputations, []);

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
     * $conditions で指定される条件を変動させてテストを行う
     * limit・offset は別でテストするので、ここには含めていない
     */
    public function providerTestGetCount()
    {
        return
        [
            '条件の指定がない場合' => [
                false, // hasFinishTime
                false, // hasStartTime
                false // hasUserIds
            ],
            '集計終了時が指定された場合' => [
                true,
                false,
                false
            ],
            '集計開始時が指定された場合' => [
                false,
                true,
                false
            ],
            'ユーザーIDが指定された場合' => [
                false,
                false,
                true
            ]
        ];
    }

    public function createDataGetCountOfRegistration()
    {
        $user1 = factory(User::class)->states('worker')->create([
            'created' => $this->baseDatetimeDb->copy(),
        ]);
        
        $user2 = factory(User::class)->states('worker')->create([
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
        $method = $this->getAccessibleMethod('getCountOfRegistration');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals(1, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 の件数が取得されること
        }
        if ($hasStartTime) { // 集計開始時が指定された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user2']->id, $records[0]->user_id); // user2 の件数が取得されること
        }
        if ($hasUserIds) { // ユーザーIDが指定された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定された1ユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
    }

    /**
     * 【初】会員登録したかどうか_条件外のデータは取得されないこと
     */
    public function testGetCountOfRegistrationInvalid()
    {
        // Arrange
        factory(User::class)->states('client')->create(); // クライアント
        factory(User::class)->states('not_active')->create(); // 非アクティブ
        factory(User::class)->states('resigned')->create(); // 退会済み

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfRegistration');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function createDataGetCountOfGettingStarted()
    {
        $user1 = factory(User::class)->states('worker')->create([
            'group_id' => Group::GROUP_ID_USER,
            'modified' => $this->baseDatetimeDb->copy(),
        ]);
        
        $user2 = factory(User::class)->states('worker')->create([
            'group_id' => Group::GROUP_ID_USER,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);

        return compact('user1', 'user2');
    }

    /**
     * 開始準備が行われたかどうかが取得できていること
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
        $method = $this->getAccessibleMethod('getCountOfGettingStarted');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals(1, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1
        }
        if ($hasStartTime) { // 集計開始時が指定された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records);
            $this->assertEquals($createData['user2']->id, $records[0]->user_id); // user2
        }
        if ($hasUserIds) { // ユーザーIDが指定された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定の1ユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
    }

    /**
     * 開始準備が行われたかどうか_条件外のデータは取得されないこと
     */
    public function testGetCountOfGettingStartedInvalid()
    {
        // Arrange
        factory(User::class)->states('worker', 'pre_user')->create(); // 開始準備を行っていない

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfGettingStarted');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function createDataGetCountOfPostQuestion()
    {
        $user1 = factory(User::class)->states('worker')->create();
        $wall1 = factory(Wall::class)->states('system')->create();
        // countの対象となる threads を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            factory(Thread::class)->create([
                'user_id' => $user1->id,
                'job_id' => random_int(1, 100000),
                'wall_id' => $wall1->id,
                'created' => $baseDatetimeDb->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('worker')->create();
        $wall2 = factory(Wall::class)->states('system')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(Thread::class)->create([
            'user_id' => $user2->id,
            'job_id' => random_int(1, 100000),
            'wall_id' => $wall2->id,
            'created' => $this->baseDatetimeDb->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
     * 質問に投稿した回数を取得できているかどうか
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfPostQuestion($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfPostQuestion();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfPostQuestion');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // 集計開始時が指定された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // ユーザーIDが指定された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定のユーザーのみ取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    /**
     * 質問に投稿した回数_条件外のデータは取得されないこと
     */
    public function testGetCountOfPostQuestionInvalid()
    {
        // Arrange
        $user = factory(User::class)->states('worker')->create();
        // wall.wall_type_id=4 でない場合
        $invalidStateses = [
            'private',
            'personal',
            'info',
            'partner',
            'user_info',
            'introduction',
            'task_contractor',
            'task_outsourcer'
        ];
        foreach ($invalidStateses as $invalidStates) {
            $invalidWall = factory(Wall::class)->states($invalidStates)->create();
            factory(Thread::class)->create([
                'user_id' => $user->id,
                'job_id' => random_int(1, 100000),
                'wall_id' => $invalidWall->id
            ]);
        }

        $wall = factory(Wall::class)->states('system')->create();
        factory(Thread::class)->create([
            'user_id' => $user->id,
            'job_id' => null, // threads.job_id を明示的にnullにする
            'wall_id' => $wall->id
        ]);

        // 外部キーの参照違い
        factory(Thread::class)->create([
            'user_id' => $user->id + 1,
            'job_id' => random_int(1, 100000),
            'wall_id' => $wall->id + 1
        ]);

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfPostQuestion');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function createDataGetCountOfProposal()
    {
        $user1 = factory(User::class)->states('worker')->create();
        // countの対象となる job_roles を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            $job1 = factory(Job::class)->create();
            factory(JobRole::class)->states('contractor')->create([
                'user_id' => $user1->id,
                'job_id' => $job1->id,
                'created' => $baseDatetimeDb->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('worker')->create();
        $job2 = factory(Job::class)->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(JobRole::class)->states('contractor')->create([
            'user_id' => $user2->id,
            'job_id' => $job2->id,
            'created' => $this->baseDatetimeDb->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
     * 仕事に応募した回数が取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfProposal($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfProposal();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfProposal');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // 集計開始時が指定された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // ユーザーIDが指定された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定のユーザーのみ取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    /**
     * 仕事に応募した回数_条件外のデータは取得されないこと
     */
    public function testGetCountOfProposalInvalid()
    {
        // Arrange
        $user = factory(User::class)->states('worker')->create();
        $job = factory(Job::class)->create();
        // job_role=1
        factory(JobRole::class)->states('outsourcer')->create([
            'user_id' => $user->id,
            'job_id' => $job->id
        ]);
        // 外部キー参照違い
        factory(JobRole::class)->states('outsourcer')->create([
            'user_id' => $user->id + 1,
            'job_id' => $job->id
        ]);

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfProposal');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function createDataGetCountOfTaskDelivery()
    {
        $user1 = factory(User::class)->states('worker')->create();
        // countの対象となる task_trades を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            factory(TaskTrade::class)->states('delivery')->create([
                'contractor_id' => $user1->id,
                'created' => $baseDatetimeDb->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('worker')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(TaskTrade::class)->states('delivery')->create([
            'contractor_id' => $user2->id,
            'created' => $this->baseDatetimeDb->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
     * タスク：納品した回数が取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfTaskDelivery($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfTaskDelivery();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfTaskDelivery');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // 集計開始時が指定された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // ユーザーIDが指定された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定のユーザーのみ取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    /**
     * タスク：納品した回数_条件外のデータは取得されないこと
     */
    public function testGetCountOfTaskDeliveryInvalid()
    {
        // Arrange
        $user = factory(User::class)->states('worker')->create();
        // task_trades.state=5 以外の場合
        $invalidStateses = [
            'registered',
            'closed',
            'closed_by_worker'
        ];
        foreach ($invalidStateses as $invalidStates) {
            factory(TaskTrade::class)->states($invalidStates)->create([
                'contractor_id' => $user->id
            ]);
        }
        // 外部キー参照違い
        factory(TaskTrade::class)->states('delivery')->create([
            'contractor_id' => $user->id + 1
        ]);

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfTaskDelivery');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function createDataGetCountOfTaskGetReward()
    {
        $user1 = factory(User::class)->states('worker')->create();
        // countの対象となる worker_rewards を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            $job1 = factory(Job::class)->states('task')->create();
            factory(WorkerReward::class)->create([
                'user_id' => $user1->id,
                'job_id' => $job1->id,
                'created' => $baseDatetimeDb->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('worker')->create();
        $job2 = factory(Job::class)->states('task')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(WorkerReward::class)->create([
            'user_id' => $user2->id,
            'job_id' => $job2->id,
            'created' => $this->baseDatetimeDb->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
     * タスク：報酬を獲得した回数が取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfTaskGetReward($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfTaskGetReward();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfTaskGetReward');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // 集計開始時が指定された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // ユーザーIDが指定された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定のユーザーのみ取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    /**
     * タスク：報酬を獲得した回数_条件外のデータは取得されないこと
     */
    public function testGetCountOfTaskGetRewardInvalid()
    {
        // Arrange
        $user = factory(User::class)->states('worker')->create();
        $invalidJob = factory(Job::class)->states('project')->create(); // プロジェクトの仕事
        factory(WorkerReward::class)->create([
            'user_id' => $user->id,
            'job_id' => $invalidJob->id,
        ]);
        // 外部キー参照違い
        $job = factory(Job::class)->states('task')->create();
        factory(WorkerReward::class)->create([
            'user_id' => $user->id + 1,
            'job_id' => $job->id,
        ]);
        factory(WorkerReward::class)->create([
            'user_id' => $user->id,
            'job_id' => $job->id + 1,
        ]);

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfTaskGetReward');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function createDataGetCountOfProjectDelivery()
    {
        $user1 = factory(User::class)->states('worker')->create();
        // countの対象となる trades を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            $targetStateses = ['delivery_accept', 'delivery_reject']; // どちらもカウント対象である
            $targetStates = $targetStateses[array_rand($targetStateses)]; // 上記のどちらかを取得
            factory(Trade::class)->states('delivery', $targetStates)->create([
                'contractor_id' => $user1->id,
                'created' => $baseDatetimeDb->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('worker')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(Trade::class)->states('delivery', 'delivery_accept')->create([
            'contractor_id' => $user2->id,
            'created' => $this->baseDatetimeDb->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
     * プロジェクト：納品した回数が取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfProjectDelivery($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfProjectDelivery();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfProjectDelivery');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // 集計開始時が指定された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // ユーザーIDが指定された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定のユーザーのみ取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    /**
     * プロジェクト：納品した回数_条件外のデータは取得されないこと
     */
    public function testGetCountOfProjectDeliveryInvalid()
    {
        // Arrange
        $user = factory(User::class)->states('worker')->create();
        // trades.state=5 以外の場合
        $invalidStateses = [
            'proposal',
            'order',
            'work',
            'finish',
            'closed',
            'terminated'
        ];
        foreach ($invalidStateses as $invalidStates) {
            factory(Trade::class)->states($invalidStates)->create([
                'contractor_id' => $user->id
            ]);
        }
        // 外部キー参照違い
        factory(Trade::class)->states('delivery')->create([
            'contractor_id' => $user->id + 1
        ]);

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfProjectDelivery');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function createDataGetCountOfProjectGetRewards()
    {
        $user1 = factory(User::class)->states('worker')->create();
        // countの対象となる trades を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            factory(Trade::class)->states('delivery', 'delivery_accept')->create([
                'contractor_id' => $user1->id,
                'modified' => $baseDatetimeDb->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('worker')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(Trade::class)->states('delivery', 'delivery_accept')->create([
            'contractor_id' => $user2->id,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
     * プロジェクト：報酬を獲得した回数が取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfProjectGetRewards($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfProjectGetRewards();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfProjectGetRewards');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // startTime が渡された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // userIds が渡された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定のユーザーのみ取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    /**
     * プロジェクト：報酬を獲得した回数_条件外のデータは取得されないこと
     */
    public function testGetCountOfProjectGetRewardsInvalid()
    {
        // Arrange
        $user = factory(User::class)->states('worker')->create();
        factory(Trade::class)->states('delivery', 'delivery_reject')->create([ // 検収NG
            'contractor_id' => $user->id
        ]);

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfProjectGetRewards');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function createDataGetCountOfRating()
    {
        $user1 = factory(User::class)->states('worker')->create();
        // countの対象となる ratings を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            factory(Rating::class)->create([
                'respondent' => $user1->id,
                'modified' => $baseDatetimeDb->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('worker')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(Rating::class)->create([
            'respondent' => $user2->id,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
     * プロジェクト：評価した回数が取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfRating($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfRating();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfRating');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // 集計開始時が指定された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // ユーザーIDが指定された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定のユーザーのみ取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    /**
     * プロジェクト：評価した回数_条件外のデータは取得されないこと
     */
    public function testGetCountOfRatingInvalid()
    {
        // Arrange
        $user = factory(User::class)->states('worker')->create();
        // 外部キー参照違い
        factory(Rating::class)->create([
            'respondent' => $user->id + 1
        ]);

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfRating');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function createDataGetCountOfAcceptReorder()
    {
        $user1 = factory(User::class)->states('worker')->create();
        // countの対象となる trades を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            factory(Trade::class)->states('reorder_quantity')->create([
                'contractor_id' => $user1->id,
                'modified' => $baseDatetimeDb->addSecond()
            ]);
        }

        $user2 = factory(User::class)->states('worker')->create();
        // 10s後に期間設定したデータを1件のみ作成
        factory(Trade::class)->states('reorder_quantity')->create([
            'contractor_id' => $user2->id,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
     * プロジェクト：再受注した回数が取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfAcceptReorder($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfAcceptReorder();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfAcceptReorder');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // 集計開始時が指定された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // ユーザーIDが指定された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定のユーザーのみ取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    /**
     * プロジェクト：再受注した回数_条件外のデータは取得されないこと
     */
    public function testGetCountOfAcceptReorderInvalid()
    {
        // Arrange
        $user = factory(User::class)->states('worker')->create();
        // trades.selected=129 以外の場合（再受注時）
        $invalidStateses = [
            're_proposal',
            'reorder',
            're_proposal_cancel',
            'reorder_cancel'
        ];
        foreach ($invalidStateses as $invalidStates) {
            factory(Trade::class)->states($invalidStates)->create([
                'contractor_id' => $user->id
            ]);
        }
        // 外部キー参照違い
        factory(Trade::class)->states('reorder_quantity')->create([
            'contractor_id' => $user->id + 1
        ]);

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfAcceptReorder');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function createDataGetCountOfSettingThumbnail()
    {
        $user1 = factory(User::class)->states('worker')->create();
        factory(S3Doc::class)->states('thumbnail')->create([
            'foreign_key' => $user1->id,
            'created' => $this->baseDatetimeDb->copy(),
        ]);

        $user2 = factory(User::class)->states('worker')->create();
        factory(S3Doc::class)->states('thumbnail')->create([
            'foreign_key' => $user2->id,
            'created' => $this->baseDatetimeDb->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2');
    }

    /**
     * 【初】アイコンが設定されているかどうかが取得できていること
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

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfSettingThumbnail');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals(1, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 の件数が取得されること
        }
        if ($hasStartTime) { // 集計開始時が指定された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user2']->id, $records[0]->user_id); // user2 の件数が取得されること
        }
        if ($hasUserIds) { // ユーザーIDが指定された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定された1ユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
    }

    /**
     * 【初】アイコンが設定されているかどうか_条件外のデータは取得されないこと
     */
    public function testGetCountOfSettingThumbnailInvalid()
    {
        // Arrange
        $user = factory(User::class)->states('worker')->create();
        // model・group が異なる
        $invalidStateses = [
            'certificate',
            'imagecheck',
            'additional_job_info',
            'trade_parameter',
            'task',
            'thread'
        ];
        foreach ($invalidStateses as $invalidStates) {
            factory(S3Doc::class)->states($invalidStates)->create([
                'foreign_key' => $user->id
            ]);
        }
        // 外部キー参照違い
        factory(S3Doc::class)->states('thumbnail')->create([
            'foreign_key' => $user->id + 1,
        ]);

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfSettingThumbnail');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function createDataGetCountOfSetProfile()
    {
        $user1 = factory(User::class)->states('worker')->create();
        factory(SellingPoint::class)->create([
            'user_id' => $user1->id,
            'created' => $this->baseDatetimeDb->copy(),
        ]);

        $user2 = factory(User::class)->states('worker')->create();
        factory(SellingPoint::class)->create([
            'user_id' => $user2->id,
            'created' => $this->baseDatetimeDb->copy()->addSeconds(10)
        ]);

        return compact('user1', 'user2');
    }

    /**
     * 【初】自己紹介が設定されているかどうかが取得できていること
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
        $method = $this->getAccessibleMethod('getCountOfSetProfile');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals(1, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 の件数が取得されること
        }
        if ($hasStartTime) { // 集計開始時が指定された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user2']->id, $records[0]->user_id); // user2 の件数が取得されること
        }
        if ($hasUserIds) { // ユーザーIDが指定された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定された1ユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
    }

    /**
     * 【初】自己紹介が設定されているかどうか_条件外のデータは取得されないこと
     */
    public function testGetCountOfSetProfileInvalid()
    {
        // Arrange
        $user = factory(User::class)->states('worker')->create();
        // 外部キー参照違い
        factory(SellingPoint::class)->create([
            'user_id' => $user->id + 1
        ]);

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfSetProfile');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function createDataGetCountOfSetSupplement()
    {
        $user1 = factory(User::class)->states('worker', 'verified')->create([
            'modified' => $this->baseDatetimeDb,
        ]);

        $user2 = factory(User::class)->states('worker')->create([
            'verification_expiration' => $this->baseDatetimeDb,
            'modified' => $this->baseDatetimeDb->copy()->addSeconds(10),
        ]);

        return compact('user1', 'user2');
    }

    /**
     * 【初】本人確認を設定したかどうかが取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfSetSupplement($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfSetSupplement();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfSetSupplement');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals(1, $records[0]->count); // user1
            $this->assertEquals(1, $records[1]->count); // user2
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 の件数が取得されること
        }
        if ($hasStartTime) { // 集計開始時が指定された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user2']->id, $records[0]->user_id); // user2 の件数が取得されること
        }
        if ($hasUserIds) { // ユーザーIDが指定された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定された1ユーザーしか取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 しか取得されないこと
        }
    }

    /**
     * 【初】本人確認を設定したかどうか_条件外のデータは取得されないこと
     */
    public function testGetCountOfSetSupplementInvalid()
    {
        // Arrange
        // verified=0 かつ verification_expiration is null
        $user = factory(User::class)->states('worker')->create([
            'verified' => false
        ]);

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfSetSupplement');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function createDataGetCountOfReceiveReward()
    {
        $user1 = factory(User::class)->states('worker')->create();
        // countの対象となる point_logs・point_details を作成する
        $targetCount = 10;
        $baseDatetimeDb = $this->baseDatetimeDb->copy();
        for ($index = 0; $index < $targetCount; $index++) {
            $pointLog1 = factory(PointLog::class)->states('permit_points_conversion')->create([
                'created' => $baseDatetimeDb->addSecond()
            ]);
            factory(PointDetail::class)->states('escrow', 'payment')->create([
                'point_log_id' => $pointLog1->id,
                'user_id' => $user1->id
            ]);
        }

        $user2 = factory(User::class)->states('worker')->create();
        // 10s後に期間設定したデータを1件のみ作成
        $pointLog2 = factory(PointLog::class)->states('permit_points_conversion')->create([
            'created' => $this->baseDatetimeDb->copy()->addSeconds(10)
        ]);
        factory(PointDetail::class)->states('escrow', 'payment')->create([
            'point_log_id' => $pointLog2->id,
            'user_id' => $user2->id
        ]);

        return compact('user1', 'user2', 'targetCount');
    }

    /**
     * 報酬を受け取った回数が取得できていること
     *
     * @dataProvider providerTestGetCount
     * @param bool $hasFinishTime
     * @param bool $hasStartTime
     * @param bool $hasUserIds
     */
    public function testGetCountOfReceiveReward($hasFinishTime, $hasStartTime, $hasUserIds)
    {
        // Arrange
        $createData = $this->createDataGetCountOfReceiveReward();

        $startTime = $this->baseDatetime->copy()->addSeconds(3); // 日本時間
        $finishTime = $this->baseDatetime->copy()->addSeconds(7); // 日本時間
        $userIds = [$createData['user1']->id]; // user1 を明示的に指定するようにする
        $expectMaxCount = $createData['targetCount']; // 10

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfReceiveReward');
        if (! ($hasFinishTime || $hasStartTime || $hasUserIds)) { // 条件の指定がない場合
            $conditions = [];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount, $records[0]->count);
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasFinishTime) { // 集計終了時が指定された場合
            $conditions = ['finishTime' => $finishTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 0件の場合は取得されないこと
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
            $this->assertEquals(6, $records[0]->count); // 最初から数えて6つ
        }
        if ($hasStartTime) { // 集計開始時が指定された場合
            $conditions = ['startTime' => $startTime];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(2, $records);
            $this->assertEquals($expectMaxCount - 2, $records[0]->count); // 後ろから数えて -2
            $this->assertEquals(1, $records[1]->count);
        }
        if ($hasUserIds) { // ユーザーIDが指定された場合
            $conditions = ['userIds' => $userIds];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(1, $records); // 指定のユーザーのみ取得されること
            $this->assertEquals($createData['user1']->id, $records[0]->user_id); // user1 が取得されること
        }
    }

    /**
     * 報酬を受け取った回数_条件外のデータは取得されないこと
     */
    public function testGetCountOfReceiveRewardInvalid()
    {
        // Arrange
        $user = factory(User::class)->states('worker')->create();

        // point_details の条件違い：account_id
        $pointLog = factory(PointLog::class)->states('permit_points_conversion')->create();
        $invalidStateses = [
            'point',
            'receivable',
            'cash_account'
        ];
        foreach ($invalidStateses as $invalidStates) {
            factory(PointDetail::class)->states($invalidStates, 'payment')->create([
                'point_log_id' => $pointLog->id,
                'user_id' => $user->id
            ]);
        }

        // point_details の条件違い：account_title_id
        $invalidStateses = [
            'cash',
            'compensation',
            'compensation_escrow',
            'transfer_escrow',
            'receipt_credit',
        ];
        foreach ($invalidStateses as $invalidStates) {
            factory(PointDetail::class)->states('escrow', $invalidStates)->create([
                'point_log_id' => $pointLog->id,
                'user_id' => $user->id
            ]);
        }

        // 外部キーの参照違い
        factory(PointDetail::class)->states('escrow', 'payment')->create([
            'point_log_id' => $pointLog->id + 1,
            'user_id' => $user->id
        ]);
        factory(PointDetail::class)->states('escrow', 'payment')->create([
            'point_log_id' => $pointLog->id,
            'user_id' => $user->id + 1
        ]);

        // point_logs の条件違い：detail
        $invalidStateses = [
            'accept_delivery',
            'transfer_request',
            'purchase'
        ];
        foreach ($invalidStateses as $invalidStates) {
            $pointLogInvalid = factory(PointLog::class)->states($invalidStates)->create();
            factory(PointDetail::class)->states('escrow', 'payment')->create([
                'point_log_id' => $pointLogInvalid->id,
                'user_id' => $user->id
            ]);
        }

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfReceiveReward');
        $records = $method->invoke($this->workerReputationCount, []);
        $this->assertEmpty($records); // 条件に合致しないデータは取得されないこと
    }

    public function providerTestLimitConditions()
    {
        return
        [
            'limit のみ指定された場合' => [
                false
            ],
            'offset も指定された場合' => [
                true
            ]
        ];
    }

    /**
     * $conditions に limit・offset が指定されたケースをテストする
     *
     * @dataProvider providerTestLimitConditions
     * @param bool $hasOffset
     */
    public function testLimitConditions($hasOffset)
    {
        // Arrange
        // 「会員登録する」の行動対象に含まれる100ユーザーを作成する
        for ($index = 1; $index <= 100; $index++) {
            factory(User::class)->states('worker')->create([
                'id' => $index,
                'created' => $this->baseDatetimeDb
            ]);
        }

        // Act & Assert
        $method = $this->getAccessibleMethod('getCountOfRegistration');
        if (! $hasOffset) { // limit のみ指定された場合
            $conditions = ['limit' => 10];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(10, $records); // limit で指定された個数のみ取得されること
            $this->assertEquals(1, $records[0]->user_id); // 頭のユーザーから取得されること
            $this->assertEquals(10, $records[9]->user_id); // 頭から10番目のユーザーまで取得されること
        } else { // offset も指定された場合
            $conditions = [
                'limit' => 10,
                'offset' => 50
            ];
            $records = $method->invoke($this->workerReputationCount, $conditions);
            $this->assertCount(10, $records); // limit で指定された個数のみ取得されること
            $this->assertEquals(51, $records[0]->user_id); // offset が反映され、51番目のユーザーから取得されること
            $this->assertEquals(60, $records[9]->user_id); // 60番目のユーザーまで取得されること
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
