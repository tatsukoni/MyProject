<?php

namespace App\Domain\ScoreReputation;

use Carbon\Carbon;
use Exception;
use stdClass;

/**
 * 行動回数取得処理で、共通で用いるメソッドを切り出す
 */
trait ReputationCountTrait
{
    /**
     * 引数で渡された $conditions が適切であるかどうかを判定する
     *
     * @param array $conditions
     * @return bool
     */
    public function checkConditions(array $conditions): bool
    {
        // $conditions の要素数が3以上でないか
        if (count($conditions) > 3) {
            return false;
        }
        // タイプミスの可能性を除外する
        $targetKeys = ['startTime', 'finishTime', 'userIds']; // 左記以外のキー名は許可しない
        foreach ($conditions as $key => $value) {
            if (! in_array($key, $targetKeys)) {
                return false;
            }
        }

        // 以降は中身を判定する
        // startTime が Carbonインスタンス であるかどうか
        if (array_key_exists('startTime', $conditions)
            && ! ($conditions['startTime'] instanceof Carbon)
        ) {
            return false;
        }
        // finishTime が Carbonインスタンス であるかどうか
        if (array_key_exists('finishTime', $conditions)
            && ! ($conditions['finishTime'] instanceof Carbon)
        ) {
            return false;
        }
        // userIds が 配列型 であるかどうか
        if (array_key_exists('userIds', $conditions)
            && ! is_array($conditions['userIds'])
        ) {
            return false;
        }

        return true;
    }

    /**
     * ユーザーidが指定された際に、条件指定で用いるsql句を返却する
     *
     * @param array $userIds
     * @return string
     * @throws Exception
     */
    public function getSqlUserIds(array $userIds): string
    {
        $userIds = implode(",", $userIds);
        if (! preg_match("/^[0-9]+(,[0-9]+)*$/", $userIds)) {
            throw new Exception('$userIdsの配列内の値が数字ではありません');
        }
        return 'AND u.id in ('.$userIds.')';
    }

    /**
     * 対象のクエリ句を元に、条件指定で用いる開始日のsql句を返却する
     *
     * @param string $targetQuery
     * @param Carbon $startTime
     * @return string
     */
    public function getSqlStartDay(string $targetQuery, Carbon $startTime): string
    {
        return "AND CONVERT_TZ({$targetQuery}, '+00:00', '+09:00') >= "."'".$startTime."'";
    }

    /**
     * 対象のクエリ句を元に、条件指定で用いる終了日のsql句を返却する
     *
     * @param string $targetQuery
     * @param Carbon $finishTime
     * @return string
     */
    public function getSqlFininshDay(string $targetQuery, Carbon $finishTime): string
    {
        return "AND CONVERT_TZ({$targetQuery}, '+00:00', '+09:00') < "."'".$finishTime."'";
    }

    /**
     * 複数の行動回数を取得するケースで、各行動を分割したレコードを返却する
     *
     * @param array $targetDatas stdClassが格納された配列
     * @return array
     */
    public function getRecords(array $targetDatas): array
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
