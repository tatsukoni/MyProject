<?php

namespace Tests\Unit\Models;

use App\Models\ScoreUserReputationCount;
use App\Models\ScoreScore;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ScoreUserReputationCountTest extends TestCase
{
    use DatabaseTransactions;

    public function testGetUserScore()
    {
        // Arrange
        $userId = random_int(1, 50000);
        $scoreUserReputationCount = factory(ScoreUserReputationCount::class)->create([
            'user_id' => $userId
        ]);
        $scoreScore = factory(ScoreScore::class)->create([
            'score' => 1
        ]);
        $expectUserScore = //;

        // Act
        $resultUserScore = ScoreUserReputationCount::getUserScore($userId);

        // Assert
        $this->assertSame($expectUserScore, $resultUserScore);
    }

    public function testGetUserScoreInvalidUser()
    {
        // Arrange
        $scoreUserReputationCount = factory(ScoreUserReputationCount::class)->create([
            'user_id' => random_int(1, 50000)
        ]);
        $invalidUserId = $scoreUserReputationCount->user_id + 1; // 存在しないユーザーID

        // Act
        $resultUserScore = ScoreUserReputationCount::getUserScore($invalidUserId);

        // Assert
        $this->assertFalse($resultUserScore);
    }
}
