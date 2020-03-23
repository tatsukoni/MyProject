<?php

namespace Tests\Unit\Domain\ScoreReputation;

use App\Domain\ScoreReputation\ReputationCount;
use App\Models\ScoreUserReputationCount;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReputationCountTest extends TestCase
{
    use DatabaseTransactions;

    private $reputationCount;

    public function setUp()
    {
        parent::setUp();
        $this->reputationCount = new ReputationCount();
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
        $this->reputationCount->saveByRecords($data);

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
}
