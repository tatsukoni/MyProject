<?php

namespace App\Models;

use App\Http\Controllers\Components\TradeState;
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

    const INSERT_LIMIT = 5000; // bulk insert の1回あたりの上限レコード数

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
        $records = array_merge($records, self::getCountOfSomeTaskTrades($finishTime, $startTime, $userIds)); // 取引：タスク関連の行動回数
        $records = array_merge($records, self::getCountOfSomeProjectTrades($finishTime, $startTime, $userIds)); // 取引：プロジェクト関連の行動回数
        $records = array_merge($records, self::getCountOfRegistration($finishTime, $startTime, $userIds)); // 会員登録したかどうか
        $records = array_merge($records, self::getCountOfInitScreening($finishTime, $startTime, $userIds)); // 初回審査を行なったかどうか
        $records = array_merge($records, self::getCountOfSetProfile($finishTime, $startTime, $userIds)); // 自己紹介を設定した回数
        $records = array_merge($records, self::getCountOfJobAccept($finishTime, $startTime, $userIds)); // 仕事が承認された回数
        $records = array_merge($records, self::getCountOfJobReEdit($finishTime, $startTime, $userIds)); // 差し戻された仕事を修正して再申請した回数
        $records = array_merge($records, self::getCountOfSupplement($finishTime, $startTime, $userIds)); // 本人確認資料を提出した回数
        $records = array_merge($records, self::getCountOfSettingThumbnail($finishTime, $startTime, $userIds)); // アイコンを設定した回数
        $records = array_merge($records, self::getCountOfApplyPartner($finishTime, $startTime, $userIds)); // パートナー申請した回数
        $records = array_merge($records, self::getCountOfPaidDeffer($finishTime, $startTime, $userIds)); // 後払いの代金を支払った回数
        $records = array_merge($records, self::getCountOfGettingStarted($finishTime, $startTime, $userIds)); // 開始準備が行われた回数
        return $records;
    }

    /**
     * 下記の行動回数を取得する
     *
     * タスク：納品物の検品をする（承認）
     * タスク：納品物の検品をする（非承認）
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfSomeTaskTrades(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDayJobRoles = '';
        $sqlStartDayTaskTrades = '';
        $sqlFinishDayJobRoles = '';
        $sqlFinishDayTaskTrades = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDayJobRoles = 'AND jr.modified >= "'.$startTime.'"';
            $sqlStartDayTaskTrades = 'AND tt.modified >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDayJobRoles = 'AND jr.modified < "'.$finishTime.'"';
            $sqlFinishDayTaskTrades = 'AND tt.modified < "'.$finishTime.'"';
        }
        $idTaskAcceptDelivery = ScoreReputation::ID_TASK_ACCEPT_DELIVERY;
        $idTaskRejectDelivery = ScoreReputation::ID_TASK_REJECT_DELIVERY;

        $sql = <<<__SQL__
SELECT u.id as 'user_id',
SUM(CASE WHEN tt.state = 5 AND tt.selected = 122 THEN 1 ELSE 0 END) as '{$idTaskAcceptDelivery}',
SUM(CASE WHEN tt.state = 5 AND tt.selected = 123 THEN 1 ELSE 0 END) as '{$idTaskRejectDelivery}'
FROM users u
    INNER JOIN job_roles jr
        ON jr.user_id = u.id
            AND jr.role_id = 1
            {$sqlStartDayJobRoles}
            {$sqlFinishDayJobRoles}
    INNER JOIN task_trades tt
        ON tt.job_id = jr.job_id
            AND tt.state IN (4, 5)
            {$sqlStartDayTaskTrades}
            {$sqlFinishDayTaskTrades}
WHERE u.view_mode = 'outsource'
    {$sqlUserIds}
GROUP BY u.id
ORDER BY u.id
__SQL__;

        $records = DB::select($sql); // この段階では、対象となる行動回数が全て含まれている
        $formatRecords = self::getRecords($records); // 各行動ごとに切り分けた形に整形する

        return $formatRecords;
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
        $sqlStartDayJobRoles = '';
        $sqlStartDayTrades = '';
        $sqlFinishDayJobRoles = '';
        $sqlFinishDayTrades = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDayJobRoles = 'AND jr.modified >= "'.$startTime.'"';
            $sqlStartDayTrades = 'AND t.modified >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDayJobRoles = 'AND jr.modified < "'.$finishTime.'"';
            $sqlFinishDayTrades = 'AND t.modified < "'.$finishTime.'"';
        }
        $idProjectOrder = ScoreReputation::ID_PROJECT_ORDER;
        $idProjectAcceptDelivery = ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY;
        $idProjectRejectDelivery = ScoreReputation::ID_PROJECT_REJECT_DELIVERY;
        $idProjectFinish = ScoreReputation::ID_PROJECT_FINISH;
        $idProjectReorder = ScoreReputation::ID_PROJECT_REORDER;

        $sql = <<<__SQL__
SELECT u.id as 'user_id',
SUM(CASE WHEN t.state = 4 THEN 1 ELSE 0 END) as '{$idProjectOrder}',
SUM(CASE WHEN t.state = 5 AND t.selected IN (122, 126) THEN 1 ELSE 0 END) as '{$idProjectAcceptDelivery}',
SUM(CASE WHEN t.state = 5 AND t.selected = 123 THEN 1 ELSE 0 END) as '{$idProjectRejectDelivery}',
SUM(CASE WHEN t.state = 6 THEN 1 ELSE 0 END) as '{$idProjectFinish}',
SUM(CASE WHEN t.state = 63 THEN 1 ELSE 0 END) as '{$idProjectReorder}'
FROM users u
    INNER JOIN job_roles jr
        ON jr.user_id = u.id
            AND jr.role_id = 1
            {$sqlStartDayJobRoles}
            {$sqlFinishDayJobRoles}
    INNER JOIN trades t
        ON t.job_id = jr.job_id
            AND t.state IN (4, 5, 6, 63)
            {$sqlStartDayTrades}
            {$sqlFinishDayTrades}
WHERE u.view_mode = 'outsource'
    {$sqlUserIds}
GROUP BY u.id
ORDER BY u.id
__SQL__;

        $records = DB::select($sql); // この段階では、対象となる行動回数が全て含まれている
        $formatRecords = self::getRecords($records); // 各行動ごとに切り分けた形に整形する

        return $formatRecords;
    }

    /**
     * 【初】会員登録したかどうかを取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfRegistration(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = 'AND u.created >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = 'AND u.created < "'.$finishTime.'"';
        }
        $reputationId = ScoreReputation::ID_REGISTRATION;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
WHERE u.view_mode = 'outsource'
    {$sqlUserIds}
    {$sqlStartDay}
    {$sqlFinishDay}
GROUP BY u.id
ORDER BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * 【初】初回審査 を行なったかどうかを取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfInitScreening(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = 'AND u.antisocial_check_date >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = 'AND u.antisocial_check_date < "'.$finishTime.'"';
        }
        $reputationId = ScoreReputation::ID_INIT_SCREENING;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
WHERE u.view_mode = 'outsource'
    AND u.antisocial_check_date IS NOT NULL
    {$sqlUserIds}
    {$sqlStartDay}
    {$sqlFinishDay}
GROUP BY u.id
ORDER BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * 自己紹介を「設定」した回数を取得する（更新は除外）
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfSetProfile(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = 'AND sp.modified >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = 'AND sp.modified < "'.$finishTime.'"';
        }
        $reputationId = ScoreReputation::ID_SET_PROFILE;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', COUNT(sp.id) as 'count'
FROM users u
    INNER JOIN selling_points sp
        ON sp.user_id = u.id
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
     * 仕事が承認された回数を取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfSomeClientReputations(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
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
        $reputationId = ScoreReputation::ID_JOB_ACCEPT;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', COUNT(j.id) as 'count'
FROM users u
    INNER JOIN job_roles jr
        ON u.id = jr.user_id
            AND jr.role_id = 1
    INNER JOIN jobs j
        ON j.id = jr.job_id
            AND j.activated = 1
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
     * 【初】本人確認を行なったかどうかを取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfSupplement(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = self::getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = 'AND u.created >= "'.$startTime.'"';
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = 'AND u.created < "'.$finishTime.'"';
        }
        $reputationId = ScoreReputation::ID_SUPPLEMENT;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
WHERE u.view_mode = 'outsource'
    AND u.verification_expiration IS NOT NULL
    {$sqlUserIds}
    {$sqlStartDay}
    {$sqlFinishDay}
GROUP BY u.id
ORDER BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * 【初】アイコンを設定した回数を取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfSettingThumbnail(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
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
        $reputationId = ScoreReputation::ID_SETTING_THUMBNAIL;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', COUNT(thumbnail.id) as 'count'
FROM users u
    INNER JOIN s3_docs thumbnail
        ON thumbnail.foreign_key = u.id
            AND thumbnail.group = 'thumbnail'
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
        $reputationId = ScoreReputation::ID_APPLY_PARTNER;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', COUNT(p.id) as 'count'
FROM users u
    INNER JOIN partners p
        ON p.outsourcer_id = u.id
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
        $reputationId = ScoreReputation::ID_PAID_DEFFER;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id',
CASE 
    WHEN COUNT(DISTINCT pl.id) > 0 THEN COUNT(DISTINCT pl.id)
    WHEN COUNT(DISTINCT pl.id) = 0 IS NULL THEN 0
    END as 'count'
FROM users u
    INNER JOIN point_details pd
        ON u.id = pd.user_id
            {$sqlStartDay}
            {$sqlFinishDay}
    INNER JOIN point_logs pl
        ON pl.id = pd.point_log_id
            AND pl.detail = 27
WHERE u.view_mode = 'outsource'
    {$sqlUserIds}
GROUP BY u.id
ORDER BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * 開始準備済みが行われた回数を取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     */
    public static function getCountOfGettingStarted(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
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
        $reputationId = ScoreReputation::ID_GETTING_STARTED;

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

    /**
     * ユーザーidが指定された際に、条件指定で用いるsql句を返却する
     *
     * @param array $userIds
     * @return string
     */
    private static function getUserIds(array $userIds): string
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
    private static function getRecords(array $targetDatas): array
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

    /**
     * 回数を保存する
     *
     * @param array $records stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @return void
     */
    public static function saveByRecords(array $records): void
    {
        // 保存対象のレコードが存在しないケース
        if (empty($records)) {
            Log::info('ScoreUserReputationCount::saveByRecords()で保存するレコードはありませんでした');
            return;
        }

        // bulk insert できる数に限りがあるので、あらかじめレコードを分割する
        $chunkRecorrds = array_chunk($records, self::INSERT_LIMIT);
        foreach ($chunkRecorrds as $targetRecorrds) {
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
