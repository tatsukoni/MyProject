<?php

namespace App\Domain\ScoreReputation;

/**
 * 行動回数の関心ごとを扱う
 */
class ScoreReputationCount
{
    const INSERT_LIMIT = 5000; // 保存時に bulk insert するレコードの上限数

    /**
     * 全ての行動回数を取得する（クライアント or ワーカー）
     * abstract でも良い
     */
    public function getAllReputationCount()
    {
        // code
    }

    /**
     * 対象の行動回数を取得する（クライアント or ワーカー）
     * abstract でも良い
     *
     * @param array $targetReputations
     */
    public function getTargetReputationCount(array $targetReputations)
    {
        // code
    }

    /**
     * 行動回数を score_user_reputation_counts に保存する
     *
     * @param array $records stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @return void
     */
    public function saveByRecords(array $records): void
    {
        // 保存対象のレコードが存在しないケース
        if (empty($records)) {
            Log::info('ScoreUserReputationCount::saveByRecords()で保存するレコードはありませんでした');
            return;
        }

        // bulk insert できる数に限りがあるので、あらかじめレコードを分割する
        $chunkRecords = array_chunk($records, self::INSERT_LIMIT);
        foreach ($chunkRecords as $targetRecorrds) {
            // bulk insert を行う
            $marks = [];
            $values = [];
            foreach ($targetRecorrds as $record) {
                $marks[] = '(?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)';
                $values = array_merge($values, [
                    $record->user_id,
                    $record->reputation_id,
                    $record->count,
                ]);
            }

            $sql = 'INSERT INTO
                score_user_reputation_counts (
                    user_id
                    , score_reputation_id
                    , count
                    , created_at
                    , updated_at
                )
                VALUES
                    ' . implode(',', $marks) . '
                ON DUPLICATE KEY UPDATE
                    count = count + VALUES(count)
                    , updated_at = CURRENT_TIMESTAMP
            ';
            DB::statement($sql, $values);
        }
    }

    /**
     * 引数で渡される conditions のフォーマットをチェック
     */
    private function checkConditions()
    {

    }
}
