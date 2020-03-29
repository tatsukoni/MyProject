<?php

namespace Tests\Feature\Commands\ScoreReputation;

use Artisan;
use App\Models\Job;
use App\Models\JobRole;
use App\Models\PointDetail;
use App\Models\PointLog;
use App\Models\Rating;
use App\Models\ScoreReputation;
use App\Models\SellingPoint;
use App\Models\S3Doc;
use App\Models\TaskTrade;
use App\Models\Thread;
use App\Models\Trade;
use App\Models\User;
use App\Models\Wall;
use App\Models\WorkerReward;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SaveWorkerAllReputationCountTest extends TestCase
{
    use DatabaseTransactions;

    private const ARTISAN_BASE_COMMAND = 'score:save_worker_all_reputation_count';

    private $targetDateTime;

    public function setUp()
    {
        parent::setUp();
        $this->targetBaseDateTimeDb = Carbon::yesterday('Asia/Tokyo')
            ->subHours(9) // DBの時刻に合わせる
            ->subSecond(); // 閾値の1秒前
    }

    /**
     * スコア対象の全てのワーカー行動テストデータを作成する
     * 
     * @param null|Carbon $dateTime
     */
    public function createData(Carbon $dateTime = null)
    {
        if (is_null($dateTime)) {
            $targetDateTimeDb = $this->targetBaseDateTimeDb;
        } else {
            $targetDateTimeDb = $dateTime;
        }
        // 会員登録を行った：score_reputation_id = 1
        // 開始準備を行った：score_reputation_id = 2
        // 本人確認を設定した：score_reputation_id = 13
        $user = factory(User::class)->states('worker', 'verified')->create([
            'created' => $targetDateTimeDb,
            'modified' => $targetDateTimeDb,
        ]);
        // 質問を投稿した：score_reputation_id = 3
        $wall = factory(Wall::class)->states('system')->create();
        factory(Thread::class)->create([
            'user_id' => $user->id,
            'job_id' => random_int(1, 100000),
            'wall_id' => $wall->id,
            'created' => $targetDateTimeDb,
        ]);
        // 仕事に応募した：score_reputation_id = 4
        factory(JobRole::class)->states('contractor')->create([
            'user_id' => $user->id,
            'created' => $targetDateTimeDb,
        ]);
        // タスク：納品した：score_reputation_id = 5
        factory(TaskTrade::class)->states('delivery')->create([
            'contractor_id' => $user->id,
            'created' => $targetDateTimeDb,
        ]);
        // タスク：報酬を獲得した：score_reputation_id = 6
        $job = factory(Job::class)->states('task')->create();
        factory(WorkerReward::class)->create([
            'user_id' => $user->id,
            'job_id' => $job->id,
            'created' => $targetDateTimeDb,
        ]);
        // プロジェクト：納品した：score_reputation_id = 7
        factory(Trade::class)->states('delivery')->create([
            'contractor_id' => $user->id,
            'created' => $targetDateTimeDb,
        ]);
        // プロジェクト：報酬を獲得した：score_reputation_id = 8
        factory(Trade::class)->states('delivery', 'delivery_accept')->create([
            'contractor_id' => $user->id,
            'modified' => $targetDateTimeDb,
        ]);
        // プロジェクト：評価した：score_reputation_id = 9
        factory(Rating::class)->create([
            'respondent' => $user->id,
            'modified' => $targetDateTimeDb,
        ]);
        // プロジェクト：再受注した：score_reputation_id = 10
        factory(Trade::class)->states('reorder_quantity')->create([
            'contractor_id' => $user->id,
            'modified' => $targetDateTimeDb,
        ]);
        // アイコンを設定した：score_reputation_id = 11
        factory(S3Doc::class)->states('thumbnail')->create([
            'foreign_key' => $user->id,
            'created' => $targetDateTimeDb,
        ]);
        // 自己紹介を設定した：score_reputation_id = 12
        factory(SellingPoint::class)->create([
            'user_id' => $user->id,
            'created' => $targetDateTimeDb,
        ]);
        // 報酬を受け取った：score_reputation_id = 14
        $pointLog = factory(PointLog::class)->states('permit_points_conversion')->create([
            'created' => $targetDateTimeDb,
        ]);
        factory(PointDetail::class)->states('escrow', 'payment')->create([
            'point_log_id' => $pointLog->id,
            'user_id' => $user->id
        ]);

        return compact('user');
    }

    /**
     * run モード実行時
     * スコア対象のワーカーの全ての行動回数が保存されていること
     */
    public function testHandleRun()
    {
        // Arrange
        $targetData = $this->createData();

        // Act
        $params = ['run_mode' => 'run'];
        $result = $this->artisan(self::ARTISAN_BASE_COMMAND, $params);

        // Assert
        $this->assertEquals(0, $result);
        $this->assertContains('save_worker_all_reputation_count : 保存処理に成功しました。', Artisan::output());
        // 全ての行動が保存されていること
        $targetReputations = [
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
        ];
        foreach ($targetReputations as $targetReputation) {
            $this->assertDatabaseHas(
                'score_user_reputation_counts',
                [
                    'user_id' => $targetData['user']->id,
                    'score_reputation_id' => $targetReputation
                ]
            );
        }
    }

    /**
     * dry モード実行時
     * 対象のデータが保存されないこと
     */
    public function testHandleDry()
    {
        // Arrange
        $targetData = $this->createData();

        // Act
        $params = ['run_mode' => 'dry'];
        $result = $this->artisan(self::ARTISAN_BASE_COMMAND, $params);

        // Assert
        $this->assertEquals(0, $result);
        $this->assertContains('save_worker_all_reputation_count : dry モードで実行しました。対象レコード数 : 14 件', Artisan::output());
        // 全ての行動について、行動回数データが保存されていないこと
        // 該当のユーザーIDが存在しないことで担保する
        $this->assertDatabaseMissing(
            'score_user_reputation_counts',
            ['user_id' => $targetData['user']->id]
        );
    }

    /**
     * 集計期間対象外の行動回数は保存されないこと
     */
    public function testNotTargetPeriodData()
    {
        // Arrange
        $notTargetDateTime = $this->targetBaseDateTimeDb->copy()->addSecond(); // 集計終了日時の閾値
        $notTargetRepiodData = $this->createData($notTargetDateTime);

        // Act
        $params = ['run_mode' => 'run'];
        $result = $this->artisan(self::ARTISAN_BASE_COMMAND, $params);

        // Assert
        $this->assertEquals(0, $result);
        // 全ての行動について、行動回数データが保存されていないこと
        // 該当のユーザーIDが存在しないことで担保する
        $this->assertDatabaseMissing(
            'score_user_reputation_counts',
            ['user_id' => $notTargetRepiodData['user']->id]
        );
    }
}
