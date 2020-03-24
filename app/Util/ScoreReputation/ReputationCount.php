<?php

namespace App\Domain\ScoreReputation;

use App\Domain\ScoreReputation\ClientReputationCount;
use App\Domain\ScoreReputation\WorkerReputationCount;

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

    private $clientReputationCount;
    private $workerReputationCount;

    public function __construct()
    {
        $this->clientReputationCount = new ClientReputationCount();
        $this->workerReputationCount = new WorkerReputationCount();
    }

    /**
     * 全ての行動回数を取得する（クライアント）
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getAllClientReputationCount(
        Carbon $finishTime = null,
        Carbon $startTime = null,
        array $userIds = null
    ): array {
        return $this->clientReputationCount->getAllReputationCount($finishTime, $startTime, $userIds);
    }

    /**
     * 全ての行動回数を取得する（ワーカー）
     *
     * @param array $conditions 指定条件
     * $conditions = [
     *     'startTime' => Carbon / 集計開始時,
     *     'finishTime' => Carbon / 集計終了時,
     *     'userIds' => array / 指定したいユーザーIDの配列
     * ]
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getAllWorkerReputationCount(array $conditions): array
    {
        return $this->workerReputationCount->getAllReputationCount($conditions);
    }

    /**
     * 対象の行動回数を取得する（クライアント）
     *
     * @param array $targetReputations
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getTargetClientReputationCount(
        array $targetReputations,
        Carbon $finishTime = null,
        Carbon $startTime = null,
        array $userIds = null
    ): array {
        return $this->clientReputationCount->getTargetReputationCount($targetReputations, $finishTime, $startTime, $userIds);
    }

    /**
     * 対象の行動回数を取得する（ワーカー）
     *
     * @param array $targetReputations
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getTargetWorkerReputationCount(
        array $targetReputations,
        Carbon $finishTime = null,
        Carbon $startTime = null,
        array $userIds = null
    ): array {
        return $this->workerReputationCount->getTargetReputationCount($targetReputations, $finishTime, $startTime, $userIds);
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
