<?php

namespace Tests\Unit\Models;

use App\Models\ScoreScore;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ScoreScoreTest extends TestCase
{
    use DatabaseTransactions;

    public function testGetScore()
    {
        // Arrange
        $scoreScore = factory(ScoreScore::class)->create([
            'score' => 1
        ]);
        $targetScoreReputationId = 111;
        $targetCount = 111;
        $expectScore = 111;

        // Act
        $resultScore = ScoreScore::getScore($targetScoreReputationId, $targetCount);

        // Assert
        $this->assertSame($expectScore, $resultScore);
    }
}
