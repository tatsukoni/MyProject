<?php

namespace App\Models;

use App\Models\ScoreScore;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Model;
use Exception;

class ScoreUserReputationCount extends Model
{
    /**
     * 指定されたユーザーのシュフティスコアを返却する
     *
     * @param int $userId
     */
    public static function getUserScore(int $userId)
    {
        $userReputationCounts = self::where('user_id', $userId)->get();
        if ($userReputationCounts->count() === 0) {
            return false;
        }

        // 指定されたユーザーの行動ごとのシュフティスコアを算出し、それらを合計する
        $userScore = 0;
        foreach ($userReputationCounts as $userReputationCount) {
            $userScore += ScoreScore::getScore($userReputationCount->score_reputation_id, $userReputationCount->count);
        }

        return $userScore;
    }

    /**
     * 仕事が承認された回数を取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userId ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfjobAccept(Carbon $startTime = null, Carbon $finishTime = null, array $userIds = null): array
    {
        if(!is_null($userIds)){
            $userIds = implode(",", $userIds);
            if(!preg_match("/^[0-9]+(,[0-9]+)*$/", $userIds)) {
                throw new Exception('第３引数の配列内の値が数字ではありません');
            }
            $sqlUserIds = 'AND u.id in ('.$userIds.')';
        } else {
            $sqlUserIds = '';
        }

        if(!is_null($startTime)){
            $sqlStartDay = 'AND j.modified >= "'.$startTime.'"'; // 00:00:00を含む
        } else {
            $sqlStartDay = '';
        }

        if(!is_null($finishTime)){
            $sqlFinishDay = 'AND j.modified < "'.$finishTime.'"'; // 00:00:00を含まない
        } else {
            $sqlFinishDay = '';
        }

        $records = DB::select('SELECT u.id as "user_id", 1 as "reputation_id", COUNT(j.id) as "count"
            FROM users u
                LEFT JOIN job_roles jr
                    ON u.id = jr.user_id
                        AND jr.role_id = 1
                LEFT JOIN jobs j
                    ON j.id = jr.job_id
                        AND j.activated = 1
                        '.$sqlStartDay.'
                        '.$sqlFinishDay.'
            WHERE u.view_mode = "outsource"
                '.$sqlUserIds.'
                '.$sqlStartDay.'
                '.$sqlFinishDay.'
            GROUP BY u.id
            ORDER BY u.id
        ');

        return $records; // バッチ処理の場合は「その日中に承認された仕事」という括りで取得される。ユーザー指定はない。
        // ただし「outsource」とだけは指定がされる
    }

    /**
     * 本人確認資料を提出したかどうか
     */
    public function getCountOfIsSupplement(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        if (! is_null($userIds)) {
            $this->sqlUserIds = $this->getUserIds($userIds);
        }

        if (! is_null($startTime)) {
            $this->sqlStartDay = 'AND supplement.modified >= "'.$startTime.'"';
        }

        if (! is_null($finishTime)) {
            $this->sqlFinishDay = 'AND supplement.modified < "'.$finishTime.'"';
        }

        $records = DB::select('SELECT u.id as "user_id", '.ScoreReputation::ID_IS_SUPPLEMENT.' as "reputation_id",
            CASE WHEN COUNT(supplement.id) > 0 THEN 1 
            ELSE 0 
            END as "No.4 本人確認提出",
            FROM users u
                LEFT JOIN s3_docs supplement
                    ON supplement.foreign_key = u.id
                        AND supplement.model = "User"
                        AND supplement.group = "supplement"
                        '.$sqlStartDay.'
                        '.$sqlFinishDay.'
            WHERE u.view_mode = "outsource"
                '.$sqlUserIds.'
                '.$sqlStartDay.'
                '.$sqlFinishDay.'
            GROUP BY u.id
            ORDER BY u.id
        ');

        return $records;
    }

    /**
     * 全ての行動回数を取得する
     * 
     * @param Carbon $startTime 集計開始時
     * @param Carbon $finishTime 集計終了時
     * @param array $userId ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfAllReputation(Carbon $startTime = null, Carbon $finishTime = null, array $userIds = null): array
    {
        $records = self::getCountOfjobAccept($startTime, $finishTime, $userIds);
        return $records;
    }

    /**
     * 回数を保存する
     * 
     * @param array $records stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function saveByRecords($records)
    {
        if (empty($records)) {
            return;
        }
        $sqlValues = ''; 
        foreach($records as $key => $record)
        {
            if($key === 0) {
                $firstBracket = '(';
            } else {
                $firstBracket = ',(';
            } 
            $sqlValues = $sqlValues.$firstBracket.$record->user_id.','.$record->reputation_id.','.$record->count.')';
        }
        $sql = 'INSERT INTO
            score_user_reputation_counts (user_id, score_reputation_id, count)
            VALUES
                '.$sqlValues.'
            ON DUPLICATE KEY UPDATE
                count = count + VALUES(count)';

        DB::statement($sql);
    }
}
