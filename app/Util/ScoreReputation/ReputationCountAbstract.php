<?php

namespace App\Domain\ScoreReputation;

use Carbon\Carbon;
use Exception;
use stdClass;

abstract class ReputationCountAbstract
{
    /**
     * 全ての行動回数を取得する（クライアント or ワーカー）
     */
    abstract public function getAllReputationCount(
        Carbon $finishTime = null,
        Carbon $startTime = null,
        array $userIds = null
    ): array;

    /**
     * 対象の行動回数を取得する（クライアント or ワーカー）
     */
    abstract public function getTargetReputationCount(
        array $targetReputations,
        Carbon $finishTime = null,
        Carbon $startTime = null,
        array $userIds = null
    ): array;

    /**
     * ユーザーidが指定された際に、条件指定で用いるsql句を返却する
     *
     * @param array $userIds
     * @return string
     * @throws Exception
     */
    protected function getUserIds(array $userIds): string
    {
        $userIds = implode(",", $userIds);
        if (! preg_match("/^[0-9]+(,[0-9]+)*$/", $userIds)) {
            throw new Exception('$userIdsの配列内の値が数字ではありません');
        }
        return 'AND u.id in ('.$userIds.')';
    }

    /**
     * 複数の行動回数を取得するケースで、各行動を分割したレコードを返却する
     *
     * @param array $targetDatas stdClassが格納された配列
     * @return array
     */
    protected function getRecords(array $targetDatas): array
    {
        $records = [];

        foreach ($targetDatas as $targetData) {
            $arrayData = get_object_vars($targetData); // stdClassを配列に変換する
            $userId = $arrayData['user_id'];
            foreach ($arrayData as $column => $value) {
                if ($column === 'user_id' || $value == 0) { // カラムが「user_id」の場合と、行動回数が「0」の場合をあらかじめ除く
                    continue;
                }

                $obj = new stdClass(); // 各行動に対してstdオブジェクトを作成する
                $obj->user_id = $userId;
                $obj->reputation_id = $column;
                $obj->count = $value;
                array_push($records, $obj); // 作成されたstdオブジェクトごとに、返却する配列に格納する
            }
        }

        return $records;
    }
}
