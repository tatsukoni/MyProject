<?php

namespace App\Domain\ScoreReputation;

use App\Domain\ScoreReputation\WorkerReputationCount;
use App\Domain\ScoreReputation\ClientReputationCount;

use Carbon\Carbon;
use DB;
use Exception;
use Log;

/**
 * スコアリング対象の行動回数に関する関心ごとを扱う
 * 現段階では、各行動は「ワーカー」「クライアント」の2区分のみであるため、このクラスで差分を吸収している
 * TODO：行動区分が増えれば、各行動を流動的要素と見なし、メソッドから各行動取得クラスを依存注入することを検討する
 */
class ReputationCount
{
    const INSERT_LIMIT = 5000; // 保存時に bulk insert するレコードの上限数

    private $workerReputation;
    private $clientReputation;

    public function __construct()
    {
        $this->workerReputation = new WorkerReputationCount();
        $this->clientReputation = new ClientReputationCount();
    }

    /**
     * 全ての行動回数を取得する（ワーカー）
     *
     * @param array $conditions 指定条件
     * $conditions = [
     *     'startTime' => Carbon / 集計開始時,
     *     'finishTime' => Carbon / 集計終了時,
     *     'userIds' => array / 指定したいユーザーIDの配列,
     *     'limit' => int / 取得レコードの上限数（レコードを分割して取得したい場合に指定する),
     *     'offset' => int / 取得レコードの取得開始位置（レコードを分割して取得したい場合に指定する),
     * ]
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getAllWorkerReputationCount(array $conditions): array
    {
        return $this->workerReputation->getAllReputationCount($conditions);
    }

    /**
     * 全ての行動回数を取得する（クライアント）
     *
     * @param array $conditions 指定条件
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getAllClientReputationCount(array $conditions): array
    {
        return $this->clientReputation->getAllReputationCount($conditions);
    }

    /**
     * 対象の行動回数を取得する（ワーカー）
     *
     * @param array $targetReputations 対象の行動
     * @param array $conditions 指定条件
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getTargetWorkerReputationCount(array $targetReputations, array $conditions): array
    {
        return $this->workerReputation->getTargetReputationCount($targetReputations, $conditions);
    }

    /**
     * 対象の行動回数を取得する（クライアント）
     *
     * @param array $targetReputations 対象の行動
     * @param array $conditions 指定条件
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getTargetClientReputationCount(array $targetReputations, array $conditions): array
    {
        return $this->clientReputation->getTargetReputationCount($targetReputations, $conditions);
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
            Log::info('保存対象のレコードはありませんでした');
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
}
