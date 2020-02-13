<?php

namespace Tests\Unit\Jobs\Admin;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

use App\Jobs\Admin\ReputationScoreJob;
use App\Mail\Mails\Admin\ReputationScoreReport;
use App\Models\ScoreUserReputationCount;
use App\Models\ScoreScore;

class ReputationScoreJobTest extends TestCase
{
    use DatabaseTransactions;

    public function createUserScoreData()
    {
        $userId = random_int(1, 50000);
        $scoreUserReputationCount = factory(ScoreUserReputationCount::class)->create([
            'user_id' => $userId
        ]);

        return compact('userId', 'scoreUserReputationCount');
    }

    public function createScoreData()
    {
        $scoreScore = factory(ScoreScore::class)->create([
            'score' => 1
        ]);

        return $scoreScore;
    }

    // 全てのユーザーで処理が成功した
    public function testHandle()
    {
        // Arrange
        Mail::fake();
        $userScoreData1 = $this->createUserScoreData();
        $userScoreData2 = $this->createUserScoreData();
        $this->createScoreData();
        $userIds = [
            $userScoreData1->userId,
            $userScoreData2->userId
        ];

        // Act
        ReputationScoreJob::dispatch($userIds);

        // Assert
        Mail::assertSent(
            ReputationScoreReport::class,
            function ($mail) {
                return $mail->resultCode === ReputationScoreJob::SUCCESS_REPUTATION_SCORE &&
                    $mail->addressTo === config('shufti.admin_mail') &&
                    $mail->subject === '【シュフティスコア】処理に成功しました';
            }
        );
    }

    // 処理に失敗したユーザーがある
    public function testHandleFailSomeUsers()
    {
        // Arrange
        Mail::fake();
        $userScoreData1 = $this->createUserScoreData();
        $userScoreData2 = $this->createUserScoreData();
        $this->createScoreData();
        $userIds = [
            $userScoreData1->userId,
            $userScoreData2->userId + 1 // score_user_reputation_counts に存在しないuser_id
        ];

        // Act
        ReputationScoreJob::dispatch($userIds);

        // Assert
        Mail::assertSent(
            ReputationScoreReport::class,
            function ($mail) {
                return $mail->resultCode === ReputationScoreJob::FAIL_SOME_TARGET_USERS &&
                    $mail->addressTo === config('shufti.admin_mail') &&
                    $mail->subject === '【シュフティスコア】シュフティスコアの取得に失敗したデータがあります';
            }
        );
    }

    // 全てのユーザーで処理に失敗した
    public function testHandleFailAllUsers()
    {
        // Arrange
        Mail::fake();
        $userScoreData1 = $this->createUserScoreData();
        $userScoreData2 = $this->createUserScoreData();
        $this->createScoreData();
        $userIds = [
            $userScoreData1->userId + 1, // score_user_reputation_counts に存在しないuser_id
            $userScoreData2->userId + 1 // score_user_reputation_counts に存在しないuser_id
        ];

        // Act
        ReputationScoreJob::dispatch($userIds);

        // Assert
        Mail::assertSent(
            ReputationScoreReport::class,
            function ($mail) {
                return $mail->resultCode === ReputationScoreJob::FAIL_ALL_TARGET_USERS &&
                    $mail->addressTo === config('shufti.admin_mail') &&
                    $mail->subject === '【シュフティスコア】指定されたユーザーのシュフティスコアを取得できませんでした';
            }
        );
    }
}
