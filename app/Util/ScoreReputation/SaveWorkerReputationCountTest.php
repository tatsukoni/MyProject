<?php

namespace Tests\Feature\Commands\ScoreReputation;

use Artisan;
use App\Models\JobRole;
use App\Models\ScoreReputation;
use App\Models\SellingPoint;
use App\Models\Trade;
use App\Models\User;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SaveWorkerReputationCountTest extends TestCase
{
    use DatabaseTransactions;

    private const ARTISAN_BASE_COMMAND = 'score:save_worker_reputation_count';

    private $yesterday;
    private $today;

    public function setUp()
    {
        parent::setUp();
        $this->yesterday = Carbon::yesterday('Asia/Tokyo')->subHours(9); // DBの時刻に合わせる
        $this->today = Carbon::today('Asia/Tokyo')->subHours(9); // DBの時刻に合わせる
    }

    public function createDefaultTargetData()
    {
        // 会員登録を行った：score_reputation_id = 1
        $user = factory(User::class)->states('worker')->create([
            'created' => $this->yesterday, // デフォルト集計開始日の閾値
        ]);
        // 仕事に応募した：score_reputation_id = 4
        factory(JobRole::class)->states('contractor')->create([
            'user_id' => $user->id,
            'created' => $this->yesterday->copy()->addSecond(),
        ]);
        // プロジェクト：納品した：score_reputation_id = 7
        factory(Trade::class)->states('delivery')->create([
            'contractor_id' => $user->id,
            'created' => $this->today->copy()->subSecond(), // デフォルト集計終了日の閾値
        ]);

        return compact('user');
    }

    /**
     * runモード
     * デフォルト期間指定
     * 期間対象のデータが保存されること
     */
    public function testHandleRun()
    {
        // Arrange
        $targetData = $this->createDefaultTargetData();

        // Act
        $params = ['run_mode' => 'run'];
        $result = $this->artisan(self::ARTISAN_BASE_COMMAND, $params);

        // Assert
        $this->assertEquals(0, $result);
        $this->assertContains('score:save_worker_reputation_count : 保存処理に成功しました。対象レコード数 : 3 件', Artisan::output());
        // 「会員登録を行った」行動が保存されていること
        $this->assertDatabaseHas(
            'score_user_reputation_counts',
            [
                'user_id' => $targetData['user']->id,
                'score_reputation_id' => ScoreReputation::ID_WORKER_REGISTRATION
            ]
        );
        // 「仕事に応募した」行動が保存されていること
        $this->assertDatabaseHas(
            'score_user_reputation_counts',
            [
                'user_id' => $targetData['user']->id,
                'score_reputation_id' => ScoreReputation::ID_PROPOSAL
            ]
        );
        // 「プロジェクト：納品した」行動が保存されていること
        $this->assertDatabaseHas(
            'score_user_reputation_counts',
            [
                'user_id' => $targetData['user']->id,
                'score_reputation_id' => ScoreReputation::ID_PROJECT_DELIVERY
            ]
        );
    }

    /**
     * dry runモード
     * デフォルト期間指定
     * dry 実行時にはデータ保存が行われないこと
     */
    public function testHandleDry()
    {
        // Arrange
        $targetData = $this->createDefaultTargetData();

        // Act
        $params = ['run_mode' => 'dry'];
        $result = $this->artisan(self::ARTISAN_BASE_COMMAND, $params);

        // Assert
        $this->assertEquals(0, $result);
        $this->assertContains('score:save_worker_reputation_count : dry モードで実行しました。対象レコード数 : 3 件', Artisan::output());
        // score_user_reputation_counts テーブルに、対象のデータが保存されていないこと
        // 同一の user_id が保存されていないことで担保する
        $this->assertDatabaseMissing(
            'score_user_reputation_counts',
            ['user_id' => $targetData['user']->id]
        );
    }

    /**
     * デフォルト期間指定
     * 期間対象外のデータは保存されないこと
     */
    public function testNotTargetPeriodData()
    {
        // Arrange
        $user = factory(User::class)->states('worker')->create([
            'created' => $this->yesterday->copy()->subSecond(), // 集計開始閾値より1秒
        ]);
        factory(Trade::class)->states('delivery')->create([
            'contractor_id' => $user->id,
            'created' => $this->today, // 集計終了日閾値より1秒後
        ]);

        // Act
        $params = ['run_mode' => 'run'];
        $result = $this->artisan(self::ARTISAN_BASE_COMMAND, $params);

        // Assert
        $this->assertEquals(0, $result);
        $this->assertContains('score:save_worker_reputation_count : 保存対象のレコードは存在しませんでした', Artisan::output());
        // score_user_reputation_counts テーブルに、対象のデータが保存されていないこと
        // 同一の user_id が保存されていないことで担保する
        $this->assertDatabaseMissing(
            'score_user_reputation_counts',
            ['user_id' => $user->id]
        );
    }

    /**
     * コマンド引数により期間指定される場合
     * 指定された期間が正しく反映されていること
     */
    public function testArgumentPeriod()
    {
        // Arrange
        // 会員登録を行った：score_reputation_id = 1
        $user = factory(User::class)->states('worker')->create([
            'created' => Carbon::parse('2018-12-31', 'Asia/Tokyo') // 集計開始日時閾値より1秒前
                ->setTime(23, 59, 59)
                ->subHours(9),
        ]);
        // 仕事に応募した：score_reputation_id = 4
        factory(JobRole::class)->states('contractor')->create([
            'user_id' => $user->id,
            'created' => Carbon::parse('2019-01-01', 'Asia/Tokyo') // 集計開始日時閾値
                ->setTime(0, 0, 0)
                ->subHours(9),
        ]);
        // プロジェクト：納品した：score_reputation_id = 7
        factory(Trade::class)->states('delivery')->create([
            'contractor_id' => $user->id,
            'created' => Carbon::parse('2019-01-01', 'Asia/Tokyo') // 集計終了日時閾値
                ->setTime(23, 59, 59)
                ->subHours(9),
        ]);
        // 自己紹介を設定した：score_reputation_id = 12
        factory(SellingPoint::class)->create([
            'user_id' => $user->id,
            'created' => Carbon::parse('2019-01-02', 'Asia/Tokyo') // 集計終了日時閾値より1秒後
                ->setTime(0, 0, 0)
                ->subHours(9),
        ]);

        // Act
        $params = [
            'run_mode' => 'run',
            'finishTime' => '2019-01-02'
        ];
        $result = $this->artisan(self::ARTISAN_BASE_COMMAND, $params);

        // Assert
        $this->assertEquals(0, $result);
        // 「会員登録を行った：score_reputation_id = 1」が保存されていないこと
        $this->assertDatabaseMissing(
            'score_user_reputation_counts',
            [
                'user_id' => $user->id,
                'score_reputation_id' => ScoreReputation::ID_WORKER_REGISTRATION
            ]
        );
        // 「仕事に応募した：score_reputation_id = 4」が保存されていること
        $this->assertDatabaseHas(
            'score_user_reputation_counts',
            [
                'user_id' => $user->id,
                'score_reputation_id' => ScoreReputation::ID_PROPOSAL
            ]
        );
        // 「プロジェクト：納品した：score_reputation_id = 7」が保存されていること
        $this->assertDatabaseHas(
            'score_user_reputation_counts',
            [
                'user_id' => $user->id,
                'score_reputation_id' => ScoreReputation::ID_PROJECT_DELIVERY
            ]
        );
        // 「自己紹介を設定した：score_reputation_id = 12」が保存されていないこと
        $this->assertDatabaseMissing(
            'score_user_reputation_counts',
            [
                'user_id' => $user->id,
                'score_reputation_id' => ScoreReputation::ID_WORKER_SET_PROFILE
            ]
        );
    }

    /**
     * コマンド引数により期間指定される場合
     * 指定した引数のフォーマットが適切でない場合
     *
     * @dataProvider providerInvalidArgumentFormat
     * @param string $finishTime
     */
    public function testInvalidArgumentFormat($finishTime)
    {
        // Act
        $params = [
            'run_mode' => 'run',
            'finishTime' => $finishTime
        ];
        $result = $this->artisan(self::ARTISAN_BASE_COMMAND, $params);

        // Assert
        $this->assertEquals(1, $result);
        $this->assertContains('finishTime は Y-m-d の形式で指定してください', Artisan::output());
    }

    public function providerInvalidArgumentFormat()
    {
        return
        [
            '日付以外のパラメータが渡された' => [
                'hoge'
            ],
            '日付の入力形式が適切でない1' => [
                '2020年1月1日'
            ],
            '日付の入力形式が適切でない2' => [
                '2020-01-01 00:00:00'
            ]
        ];
    }
}
