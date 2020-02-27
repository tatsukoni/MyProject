<?php

namespace App\Models;

use App\Models\ScoreReputation;
use App\Models\ScoreScore;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Database\Eloquent\Model;

use Log;
use stdClass;

class ScoreUserReputationCount extends Model
{
    protected $casts = [
        'user_id' => 'integer',
        'score_reputation_id' => 'integer',
        'count' => 'integer',
    ];

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
     * 全ての行動回数を取得する
     *
     * 何かの行動回数を取得する関数を作成した場合、
     * getCountOfAllReputation関数に作成した関数を追加し、
     * 全ての行動回数を取得できるようにしてください
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfAllReputation(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $records = [];
        $records = array_merge($records, self::getCountOfSomeClientReputations($finishTime, $startTime, $userIds));
        $records = array_merge($records, self::getCountOfSomeUserReputations($finishTime, $startTime, $userIds));
        $records = array_merge($records, self::getCountOfJobAccept($finishTime, $startTime, $userIds));
        $records = array_merge($records, self::getCountOfJobReEdit($finishTime, $startTime, $userIds));
        $records = array_merge($records, self::getIsSupplement($finishTime, $startTime, $userIds));
        $records = array_merge($records, self::getIsSettingThumbnail($finishTime, $startTime, $userIds));
        $records = array_merge($records, self::getCountOfApplyPartner($finishTime, $startTime, $userIds));
        $records = array_merge($records, self::getCountOfPaidDeffer($finishTime, $startTime, $userIds));
        $records = array_merge($records, self::getCountOfDoneGettingStarted($finishTime, $startTime, $userIds));
        return $records;
    }

    /**
     * 下記の行動回数を取得する
     *
     * タスク：納品物の検品をする（承認）
     * タスク：納品物の検品をする（非承認）
     * プロジェクト：発注する
     * プロジェクト：納品物の検品をする（承認）
     * プロジェクト：納品物の検品をする（差し戻し）
     * プロジェクト：評価する
     * プロジェクト：再発注する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfSomeClientReputations(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDayJr = '';
        $sqlStartDayTt = '';
        $sqlStartDayT = '';
        $sqlFinishDayJr = '';
        $sqlFinishDayTt = '';
        $sqlFinishDayT = '';

        if (!is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (!is_null($startTime)) {
            $sqlStartDayJr = 'AND jr.modified >= "'.$startTime.'"';
            $sqlStartDayTt = 'AND tt.modified >= "'.$startTime.'"';
            $sqlStartDayT = 'AND t.modified >= "'.$startTime.'"';
        }
        if (!is_null($finishTime)) {
            $sqlFinishDayJr = 'AND jr.modified < "'.$finishTime.'"';
            $sqlFinishDayTt = 'AND tt.modified < "'.$finishTime.'"';
            $sqlFinishDayT = 'AND t.modified < "'.$finishTime.'"';
        }

        $recordsGroupByUserId = DB::select('SELECT u.id as "user_id",
            SUM(CASE WHEN tt.state = 5 AND tt.selected = 122 THEN 1 ELSE 0 END) as "'.ScoreReputation::ID_TASK_ACCEPT_DELIVERY.'",
            SUM(CASE WHEN tt.state = 5 AND tt.selected = 123 THEN 1 ELSE 0 END) as "'.ScoreReputation::ID_TASK_REJECT_DELIVERY.'",
            SUM(CASE WHEN t.state = 4 THEN 1 ELSE 0 END) as "'.ScoreReputation::ID_ORDER.'",
            SUM(CASE WHEN t.state = 5 AND t.selected IN (122, 126) THEN 1 ELSE 0 END) as "'.ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY.'",
            SUM(CASE WHEN t.state = 5 AND t.selected = 123 THEN 1 ELSE 0 END) as "'.ScoreReputation::ID_PROJECT_REJECT_DELIVERY.'",
            SUM(CASE WHEN t.state = 6 THEN 1 ELSE 0 END) as "'.ScoreReputation::ID_FINISH.'",
            SUM(CASE WHEN t.state = 63 THEN 1 ELSE 0 END) as "'.ScoreReputation::ID_PROJECT_REORDER.'"
            FROM users u
                LEFT JOIN job_roles jr
                    ON jr.user_id = u.id
                        AND jr.role_id = 1
                        '.$sqlStartDayJr.'
                        '.$sqlFinishDayJr.'
                LEFT JOIN task_trades tt
                    ON tt.job_id = jr.job_id
                        AND tt.state IN (4, 5)
                        '.$sqlStartDayTt.'
                        '.$sqlFinishDayTt.'
                LEFT JOIN trades t
                    ON t.job_id = jr.job_id
                        AND t.state IN (4, 5, 6, 63)
                        '.$sqlStartDayT.'
                        '.$sqlFinishDayT.'
            WHERE u.view_mode = "outsource"
                '.$sqlUserIds.'
            GROUP BY u.id
            ORDER BY u.id;
        ');

        // 整形
        $records = [];
        foreach ($recordsGroupByUserId as $record) {
            $recordArray = get_object_vars($record);
            $userId = $recordArray['user_id'];
            foreach ($recordArray as $key => $value) {
                if ($key !== 'user_id' && $value !== 0) {
                    $obj = new stdClass();
                    $obj->user_id = $userId;
                    $obj->reputation_id = (string)$key;
                    $obj->count = $value;
                    array_push($records, $obj);
                }
            }
        }

        return $records;
    }

    /**
     * 下記を取得する
     *
     * プロジェクト：発注する
     * プロジェクト：納品物の検品をする（承認）
     * プロジェクト：納品物の検品をする（差し戻し）
     * プロジェクト：評価する
     * プロジェクト：再発注する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfSomeProjectTrades(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDayJr = '';
        $sqlStartDayT = '';
        $sqlFinishDayJr = '';
        $sqlFinishDayT = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDayJr = 'AND jr.modified >= "'.$startTime.'"';
            $sqlStartDayT = 'AND t.modified >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDayJr = 'AND jr.modified < "'.$finishTime.'"';
            $sqlFinishDayT = 'AND t.modified < "'.$finishTime.'"';
        }

        $recordsGroupByUserId = DB::select('SELECT u.id as "user_id",
            SUM(CASE WHEN t.state = 4 THEN 1 ELSE 0 END) as "'.ScoreReputation::ID_ORDER.'",
            SUM(CASE WHEN t.state = 5 AND t.selected IN (122, 126) THEN 1 ELSE 0 END) as "'.ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY.'",
            SUM(CASE WHEN t.state = 5 AND t.selected = 123 THEN 1 ELSE 0 END) as "'.ScoreReputation::ID_PROJECT_REJECT_DELIVERY.'",
            SUM(CASE WHEN t.state = 6 THEN 1 ELSE 0 END) as "'.ScoreReputation::ID_FINISH.'",
            SUM(CASE WHEN t.state = 63 THEN 1 ELSE 0 END) as "'.ScoreReputation::ID_PROJECT_REORDER.'"
            FROM users u
                INNER JOIN job_roles jr
                    ON jr.user_id = u.id
                        AND jr.role_id = 1
                        '.$sqlStartDayJr.'
                        '.$sqlFinishDayJr.'
                INNER JOIN trades t
                    ON t.job_id = jr.job_id
                        AND t.state IN (4, 5, 6, 63)
                        '.$sqlStartDayT.'
                        '.$sqlFinishDayT.'
            WHERE u.view_mode = "outsource"
                '.$sqlUserIds.'
            GROUP BY u.id
            ORDER BY u.id;
        ');

        // 整形
        $records = [];
        foreach ($recordsGroupByUserId as $record) {
            $recordArray = get_object_vars($record);
            $userId = $recordArray['user_id'];
            foreach ($recordArray as $key => $value) {
                if ($key !== 'user_id' && $value !== 0) {
                    $obj = new stdClass();
                    $obj->user_id = $userId;
                    $obj->reputation_id = (string)$key;
                    $obj->count = $value;
                    array_push($records, $obj);
                }
            }
        }

        return $records;
    }

    /**
     * 下記を取得する
     *
     * 【初】会員登録したかどうか（DBに登録されている全てのユーザーに付与）
     * 【初】初回審査 を行なったかどうか
     * 自己紹介を設定したかどうか
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfSomeUserReputations(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDayU = '';
        $sqlStartDayAc = '';
        $sqlStartDaySp = '';
        $sqlFinishDayU = '';
        $sqlFinishDayAc = '';
        $sqlFinishDaySp = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDayAc = 'AND u.antisocial_check_date >= "'.$startTime.'"';
            $sqlStartDaySp = 'AND sp.modified >= "'.$startTime.'"';
            $sqlStartDayU = 'AND u.created >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDayAc = 'AND u.antisocial_check_date < "'.$finishTime.'"';
            $sqlFinishDaySp = 'AND sp.modified < "'.$finishTime.'"';
            $sqlFinishDayU = 'AND u.created < "'.$finishTime.'"';
        }

        $recordsGroupByUserId = DB::select('SELECT u.id as "user_id",
            1 as "'.ScoreReputation::ID_REGISTRATION.'",
            CASE u.antisocial
                WHEN u.antisocial_check_date IS NOT NULL
                    '.$sqlStartDayAc.'
                    '.$sqlFinishDayAc.'
                THEN 1
                ELSE 0
                END as "'.ScoreReputation::ID_INIT_SCREENING.'",
            CASE WHEN sp.id IS NULL THEN 0 ELSE 1 END as "'.ScoreReputation::ID_SET_PROFILE.'"
            FROM users u
                LEFT JOIN selling_points sp
                    ON sp.user_id = u.id
                        '.$sqlStartDaySp.'
                        '.$sqlFinishDaySp.'
            WHERE u.view_mode = "outsource"
                '.$sqlUserIds.'
            GROUP BY u.id
            ORDER BY u.id
        ');

        // 整形
        $records = [];
        foreach ($recordsGroupByUserId as $record) {
            $recordArray = get_object_vars($record);
            $userId = $recordArray['user_id'];
            foreach ($recordArray as $key => $value) {
                if ($key !== 'user_id' && $value !== 0) {
                    $obj = new stdClass();
                    $obj->user_id = $userId;
                    $obj->reputation_id = (string)$key;
                    $obj->count = $value;
                    array_push($records, $obj);
                }
            }
        }

        return $records;
    }

    /**
     * 仕事が承認された回数を取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfJobAccept(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = 'AND j.activated_date >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = 'AND j.activated_date < "'.$finishTime.'"';
        }

        $records = DB::select('SELECT u.id as "user_id", '.ScoreReputation::ID_JOB_ACCEPT.' as "reputation_id", COUNT(j.id) as "count"
            FROM users u
                INNER JOIN job_roles jr
                    ON u.id = jr.user_id
                        AND jr.role_id = 1
                INNER JOIN jobs j
                    ON j.id = jr.job_id
                        AND j.activated = 1
                        '.$sqlStartDay.'
                        '.$sqlFinishDay.'
            WHERE u.view_mode = "outsource"
                '.$sqlUserIds.'
            GROUP BY u.id
            ORDER BY u.id
        ');

        return $records;
    }

    /**
     * 差し戻された仕事を修正して再申請した回数を取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfJobReEdit(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = 'AND ad.created_at >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = 'AND ad.created_at < "'.$finishTime.'"';
        }
        $reputationId = ScoreReputation::ID_JOB_RE_EDIT;

        $sql = <<<__SQL__
            SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', COUNT(ad.id) as 'count'
                FROM users u
                    INNER JOIN job_roles jr
                        ON u.id = jr.user_id
                            AND jr.role_id = 1
                    INNER JOIN audits ad
                        ON u.id = ad.user_id
                            AND ad.auditable_id = jr.job_id
                            AND ad.event = 'updated'
                            AND ad.auditable_type = 'Job'
                            AND ad.old_values LIKE '%"re_edit":1%'
                            AND ad.new_values LIKE '%"re_edit":false%'
                            {$sqlStartDay}
                            {$sqlFinishDay}
                WHERE u.view_mode = 'outsource'
                    {$sqlUserIds}
                GROUP BY u.id
                ORDER BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * 【初】本人確認資料を提出した回数を取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getIsSupplement(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = 'AND supplement.modified >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = 'AND supplement.modified < "'.$finishTime.'"';
        }

        $records = DB::select('SELECT u.id as "user_id",
            '.ScoreReputation::ID_IS_SUPPLEMENT.' as "reputation_id",
            COUNT(supplement.id) as "count"
            FROM users u
                INNER JOIN s3_docs supplement
                    ON supplement.foreign_key = u.id
                        AND supplement.model = "User"
                        AND supplement.group = "supplement"
                        '.$sqlStartDay.'
                        '.$sqlFinishDay.'
            WHERE u.view_mode = "outsource"
                '.$sqlUserIds.'
            GROUP BY u.id
            ORDER BY u.id
        ');

        return $records;
    }

    /**
     * 【初】アイコンを設定した回数を取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getIsSettingThumbnail(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = 'AND thumbnail.created >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = 'AND thumbnail.created < "'.$finishTime.'"';
        }

        $records = DB::select('SELECT u.id as "user_id",
            '.ScoreReputation::ID_IS_SETTING_THUMBNAIL.' as "reputation_id",
            COUNT(thumbnail.id) as "count"
            FROM users u
                INNER JOIN s3_docs thumbnail
                    ON thumbnail.foreign_key = u.id
                        AND thumbnail.group = "thumbnail"
                        '.$sqlStartDay.'
                        '.$sqlFinishDay.'
            WHERE u.view_mode = "outsource"
                '.$sqlUserIds.'
            GROUP BY u.id
            ORDER BY u.id
        ');

        return $records;
    }

    /**
     * パートナー申請した回数を取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfApplyPartner(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = 'AND p.created >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = 'AND p.created < "'.$finishTime.'"';
        }

        $records = DB::select('SELECT u.id as "user_id", '.ScoreReputation::ID_APPLY_PARTNER.' as "reputation_id", COUNT(p.id) as "count"
            FROM users u
                INNER JOIN partners p
                    ON p.outsourcer_id = u.id
                    '.$sqlStartDay.'
                    '.$sqlFinishDay.'
            WHERE u.view_mode = "outsource"
                '.$sqlUserIds.'
            GROUP BY u.id
            ORDER BY u.id
        ');

        return $records;
    }

    /**
     * 後払いの代金を支払った回数を取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfPaidDeffer(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = 'AND pd.modified >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = 'AND pd.modified < "'.$finishTime.'"';
        }

        $records = DB::select('SELECT u.id as "user_id", '.ScoreReputation::ID_PAID_DEFFER.' as "reputation_id",
            CASE 
                WHEN COUNT(DISTINCT pl.id) > 0 THEN COUNT(DISTINCT pl.id)
                WHEN COUNT(DISTINCT pl.id) = 0 IS NULL THEN 0
                END as "count"
            FROM users u
                INNER JOIN point_details pd
                    ON u.id = pd.user_id
                    '.$sqlStartDay.'
                    '.$sqlFinishDay.'
                INNER JOIN point_logs pl
                    ON pl.id = pd.point_log_id
                    AND pl.detail = 27
            WHERE u.view_mode = "outsource"
                '.$sqlUserIds.'
            GROUP BY u.id
            ORDER BY u.id
        ');

        return $records;
    }

    /**
     * 開始準備済みが行われた回数を取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfDoneGettingStarted(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = 'AND ad.created_at >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = 'AND ad.created_at < "'.$finishTime.'"';
        }
        $reputationId = ScoreReputation::ID_DONE_GETTING_STARTED;

        $sql = <<<__SQL__
            SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', COUNT(ad.id) as 'count'
                FROM users u
                    INNER JOIN audits ad
                        ON u.id = ad.user_id
                            AND ad.event = 'updated'
                            AND ad.auditable_type = 'User'
                            AND ad.old_values LIKE '%"group_id":7%'
                            {$sqlStartDay}
                            {$sqlFinishDay}
                WHERE u.view_mode = 'outsource'
                    {$sqlUserIds}
                GROUP BY u.id
                ORDER BY u.id
__SQL__;

        return DB::select($sql);
    }

    private static function getUserIds(array $userIds): string
    {
        $userIds = implode(",", $userIds);
        if (! preg_match("/^[0-9]+(,[0-9]+)*$/", $userIds)) {
            throw new Exception('$userIdsの配列内の値が数字ではありません');
        }
        return 'AND u.id in ('.$userIds.')';
    }

    /**
     * 回数を保存する
     *
     * @param array $records stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @return void
     */
    public static function saveByRecords(array $records): void
    {
        if (empty($records)) {
            Log::info('ScoreUserReputationCount::saveByRecords()で保存するレコードはありませんでした');
            return;
        }
        $marks = [];
        $values = [];
        foreach ($records as $record) {
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
