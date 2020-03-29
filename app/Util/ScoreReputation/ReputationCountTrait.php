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
        // $conditions の中身が適切かどうかを判定する
        // 1つでも適切でない要素が存在した場合 or 許可しないキー名の場合に false を返却
        foreach ($conditions as $key => $value) {
            if (! $this->checkConditionsValue($key, $value)) {
                return false;
            }
        }

        // startTime > finishTime の場合にfalseを返却する
        if (array_key_exists('startTime', $conditions)
            && array_key_exists('finishTime', $conditions)
        ) {
            if ($conditions['startTime'] > $conditions['finishTime']) {
                return false;
            }
        }

        return true;
    }

    /**
     * $conditions の中身が適切であるかどうかを判定する
     *
     * @param string $key
     * @param $value
     * @return bool
     */
    public function checkConditionsValue(string $key, $value): bool
    {
        switch ($key) {
            case 'startTime':
            case 'finishTime':
                return ($value instanceof Carbon);
            case 'userIds':
                return (is_array($value));
            case 'limit':
            case 'offset':
                return (is_int($value));
            default:
                // 上記以外のキー名は許可しない
                return false;
        }
    }

    /**
     * SQLで日時比較を行う際、タイムゾーンを UTC に変更する
     *
     * @param Carbon $targetDate
     * @return Carbon
     */
    public function getUtcDateTime(Carbon $targetDate): Carbon
    {
        if ($targetDate->utc) { // 引数のタイムゾーンが UTC の場合はそのまま返却
            return $targetDate;
        } else {
            return $targetDate->setTimezone('UTC');
        }
    }

    /**
     * ユーザーidが指定された際に、条件指定で用いるsql句を返却する
     *
     * @param array $conditions
     * @return string
     * @throws Exception
     */
    public function getSqlUserIds(array $conditions): string
    {
        if (! array_key_exists('userIds', $conditions)) {
            return '';
        }

        $userIds = implode(",", $conditions['userIds']);
        if (! preg_match("/^[0-9]+(,[0-9]+)*$/", $userIds)) {
            throw new Exception('$userIdsの配列内の値が数字ではありません');
        }
        return 'AND u.id in ('.$userIds.')';
    }

    /**
     * 集計開始日が指定された際に、条件指定で用いるsql句を返却する
     *
     * @param array $conditions
     * @param string $targetQuery
     * @return string
     */
    public function getSqlStartDay(array $conditions, string $targetQuery): string
    {
        if (! array_key_exists('startTime', $conditions)) {
            return '';
        }

        return "AND $targetQuery >= "."'".$this->getUtcDateTime($conditions['startTime'])."'";
    }

    /**
     * 集計終了日が指定された際に、条件指定で用いるsql句を返却する
     *
     * @param array $conditions
     * @param string $targetQuery
     * @return string
     */
    public function getSqlFinishDay(array $conditions, string $targetQuery): string
    {
        if (! array_key_exists('finishTime', $conditions)) {
            return '';
        }

        return "AND $targetQuery < "."'".$this->getUtcDateTime($conditions['finishTime'])."'";
    }

    /**
     * 分割条件が指定された際に、左記を表現するsql句を返却する
     *
     * @param array $conditions
     * @param string $targetQuery
     * @return string
     */
    public function getSqlChunkQuery(array $conditions): string
    {
        if (! array_key_exists('limit', $conditions)) {
            return '';
        }
        $limit = $conditions['limit'];

        // offset句の有無によって、返却値が変わる
        if (array_key_exists('offset', $conditions)) { 
            $offset = $conditions['offset'];
            return "LIMIT {$limit} OFFSET {$offset}";
        } else {
            return "LIMIT {$limit}";
        }
    }
}
