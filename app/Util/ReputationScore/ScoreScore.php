<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreScore extends Model
{
    /**
     * 指定された回数を元に、その行動のシュフティスコアを算出する
     */
    public static function getScore(int $scoreReputationId, int $count): int
    {
        $baseQuery = self::where('score_reputation_id', $scoreReputationId);
        $score = 0;

        // 毎回加算される場合
        $targetRecordEvery = $baseQuery->whereNull('count')->first();
        if (! is_null($targetRecordEvery) && $targetRecordEvery->is_every_time) {
            $score += $targetRecordEvery->score * $count;
        }

        // 回数特典が存在する場合
        $targetRecordBonuses = $baseQuery->whereNotNull('count')->get();
        if ($targetRecordBonuses->count() !== 0) {
            foreach ($targetRecordBonuses as $targetRecordBonuse) {
                if ($count >= $targetRecordBonuse->count) {
                    $score += $targetRecordBonuse->score;
                }
            }
        }

        return $score;
    }
}
