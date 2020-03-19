<?php

namespace App\Domain\ScoreReputation;

use App\Domain\ScoreReputation\ReputationCountAbstract;
use App\Models\ScoreReputation;

/**
 * スコアリング対象の行動回数を取得する（クライアント）
 */
class ClientReputationCount extends ReputationCountAbstract
{
    // 対象の行動と、その回数を取得するメソッド名との紐付けを行う
    // 1:1の関係がある行動が対象となっている
    const TARGET_REPUTATION_METHODS = [
        ScoreReputation::ID_CLIENT_REGISTRATION => 'getCountOfRegistration', // 【初】会員登録する
        ScoreReputation::ID_CLIENT_GETTING_STARTED　=> 'getCountOfGettingStarted', // 【初】開始準備
        ScoreReputation::ID_CLIENT_INIT_SCREENING => 'getCountOfInitScreening', // 【初】初回審査
        ScoreReputation::ID_CLIENT_SUPPLEMENT => 'getCountOfSupplement', // 【初】本人確認提出
        ScoreReputation::ID_JOB_RE_EDIT => 'getCountOfJobReEdit', // 差し戻された仕事を修正して再申請する
        ScoreReputation::ID_JOB_ACCEPT => 'getCountOfJobAccept', // タスク,プロジェクト：仕事が承認される（前・後払い共通）
        ScoreReputation::ID_CLIENT_SETTING_THUMBNAIL => 'getCountOfSettingThumbnail', // 【初】アイコンを設定する
        ScoreReputation::ID_CLIENT_SET_PROFILE => 'getCountOfSetProfile', // 【初】自己紹介を設定する
        ScoreReputation::ID_APPLY_PARTNER => 'getCountOfApplyPartner', // パートナー申請する
        ScoreReputation::ID_PAID_DEFFER => 'getCountOfPaidDeffer', // 後払いの代金を支払う
    ];

    // getCountOfSomeTaskTrades()で取得される行動回数（= タスク取引関連の行動回数）
    const SOME_TASK_TRADE_REPUTATIONS = [
        ScoreReputation::ID_TASK_ACCEPT_DELIVERY, // タスク：納品物の検品をする（承認）
        ScoreReputation::ID_TASK_REJECT_DELIVERY // タスク：納品物の検品をする（非承認）
    ];

    // getCountOfSomeProjectTrades()で取得される行動回数（= プロジェクト取引関連の行動回数）
    const SOME_PROJECT_TRADE_REPUTATIONS = [
        ScoreReputation::ID_PROJECT_ORDER, // プロジェクト：発注する
        ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY, // プロジェクト：納品物の検品をする（承認）
        ScoreReputation::ID_PROJECT_REJECT_DELIVERY, // プロジェクト：納品物の検品をする（差し戻し）
        ScoreReputation::ID_CLIENT_PROJECT_FINISH, // プロジェクト：評価する
        ScoreReputation::ID_PROJECT_REORDER // プロジェクト：再発注する
    ];

    /**
     * 全ての行動回数を取得する（クライアント)
     */
    public function getAllReputationCount(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $records = [];

        $records = array_merge($records, $this->getCountOfSomeTaskTrades($finishTime, $startTime, $userIds)); // 取引：タスク関連の行動回数
        $records = array_merge($records, $this->getCountOfSomeProjectTrades($finishTime, $startTime, $userIds)); // 取引：プロジェクト関連の行動回数
        $records = array_merge($records, $this->getCountOfRegistration($finishTime, $startTime, $userIds)); // 会員登録したかどうか
        $records = array_merge($records, $this->getCountOfInitScreening($finishTime, $startTime, $userIds)); // 初回審査を行なったかどうか
        $records = array_merge($records, $this->getCountOfSetProfile($finishTime, $startTime, $userIds)); // 自己紹介を設定した回数
        $records = array_merge($records, $this->getCountOfJobAccept($finishTime, $startTime, $userIds)); // 仕事が承認された回数
        $records = array_merge($records, $this->getCountOfJobReEdit($finishTime, $startTime, $userIds)); // 差し戻された仕事を修正して再申請した回数
        $records = array_merge($records, $this->getCountOfSupplement($finishTime, $startTime, $userIds)); // 本人確認資料を提出した回数
        $records = array_merge($records, $this->getCountOfSettingThumbnail($finishTime, $startTime, $userIds)); // アイコンを設定した回数
        $records = array_merge($records, $this->getCountOfApplyPartner($finishTime, $startTime, $userIds)); // パートナー申請した回数
        $records = array_merge($records, $this->getCountOfPaidDeffer($finishTime, $startTime, $userIds)); // 後払いの代金を支払った回数
        $records = array_merge($records, $this->getCountOfGettingStarted($finishTime, $startTime, $userIds)); // 開始準備が行われた回数

        return $records;
    }

    /**
     * 対象の行動回数を取得する（クライアント or ワーカー）
     */
    public function getTargetReputationCount(array $targetReputations): array
    {
        $records = [];

        // 1:1関係にある行動回数を返却する
        foreach ($targetReputations as $targetReputation) {
            if (array_key_exists($targetReputation, self::TARGET_REPUTATION_METHODS)) {
                $method = self::TARGET_REPUTATION_METHODS[$targetReputation];
                $records = array_merge($records, $this->$method($finishTime, $startTime, $userIds));
            }
        }
        // タスク取引関連の行動回数が取得される場合
        if (array_intersect($targetReputations, self::SOME_TASK_TRADE_REPUTATIONS)) {
            $records = array_merge($records, $this->getCountOfSomeTaskTrades($finishTime, $startTime, $userIds));
        }
        // プロジェクト取引関連の行動回数が取得される場合
        if (array_intersect($targetReputations, self::SOME_PROJECT_TRADE_REPUTATIONS)) {
            $records = array_merge($records, $this->getCountOfSomeProjectTrades($finishTime, $startTime, $userIds));
        }

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
     * @throws Exception
     */
    public function getCountOfSomeTaskTrades(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDayJobRoles = '';
        $sqlStartDayTaskTrades = '';
        $sqlFinishDayJobRoles = '';
        $sqlFinishDayTaskTrades = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDayJobRoles = "AND CONVERT_TZ(jr.modified, '+00:00', '+09:00') >= "."'".$startTime."'";
            $sqlStartDayTaskTrades = "AND CONVERT_TZ(tt.modified, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDayJobRoles = "AND CONVERT_TZ(jr.modified, '+00:00', '+09:00') < "."'".$finishTime."'";
            $sqlFinishDayTaskTrades = "AND CONVERT_TZ(tt.modified, '+00:00', '+09:00') < "."'".$finishTime."'";
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
            AND tt.state = 5
            AND tt.selected IN (122, 123)
            {$sqlStartDayTaskTrades}
            {$sqlFinishDayTaskTrades}
WHERE u.view_mode = 'outsource'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
GROUP BY u.id
ORDER BY u.id
__SQL__;

        $records = DB::select($sql); // この段階では、対象となる行動回数が全て含まれている
        return $this->getRecords($records); // 各行動ごとに切り分けた形に整形し、返却する
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
     * @throws Exception
     */
    public function getCountOfSomeProjectTrades(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDayJobRoles = '';
        $sqlStartDayTrades = '';
        $sqlFinishDayJobRoles = '';
        $sqlFinishDayTrades = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDayJobRoles = "AND CONVERT_TZ(jr.modified, '+00:00', '+09:00') >= "."'".$startTime."'";
            $sqlStartDayTrades = "AND CONVERT_TZ(t.modified, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDayJobRoles = "AND CONVERT_TZ(jr.modified, '+00:00', '+09:00') < "."'".$finishTime."'";
            $sqlFinishDayTrades = "AND CONVERT_TZ(t.modified, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $idProjectOrder = ScoreReputation::ID_PROJECT_ORDER;
        $idProjectAcceptDelivery = ScoreReputation::ID_PROJECT_ACCEPT_DELIVERY;
        $idProjectRejectDelivery = ScoreReputation::ID_PROJECT_REJECT_DELIVERY;
        $idProjectFinish = ScoreReputation::ID_CLIENT_PROJECT_FINISH;
        $idProjectReorder = ScoreReputation::ID_PROJECT_REORDER;

        $sql = <<<__SQL__
SELECT u.id as 'user_id',
SUM(CASE WHEN t.state = 2 AND selected = 103 THEN 1 ELSE 0 END) as '{$idProjectOrder}',
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
            AND t.state IN (2, 5, 6, 63)
            {$sqlStartDayTrades}
            {$sqlFinishDayTrades}
WHERE u.view_mode = 'outsource'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
GROUP BY u.id
ORDER BY u.id
__SQL__;

        $records = DB::select($sql); // この段階では、対象となる行動回数が全て含まれている
        return $this->getRecords($records); // 各行動ごとに切り分けた形に整形し、返却する
    }

    /**
     * 【初】会員登録したかどうかを取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getCountOfRegistration(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(u.created, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(u.created, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_CLIENT_REGISTRATION;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
WHERE u.view_mode = 'outsource'
    AND u.active = 1
    AND u.resigned = 0
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
     * @throws Exception
     */
    public function getCountOfInitScreening(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(u.antisocial_check_date, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(u.antisocial_check_date, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_CLIENT_INIT_SCREENING;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
WHERE u.view_mode = 'outsource'
    AND u.antisocial_check_date IS NOT NULL
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
    {$sqlStartDay}
    {$sqlFinishDay}
GROUP BY u.id
ORDER BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * 自己紹介が設定されているかどうかを取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getCountOfSetProfile(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(sp.modified, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(sp.modified, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_CLIENT_SET_PROFILE;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
    INNER JOIN selling_points sp
        ON sp.user_id = u.id
            {$sqlStartDay}
            {$sqlFinishDay}
WHERE u.view_mode = 'outsource'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
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
     * @throws Exception
     */
    public function getCountOfJobAccept(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(j.activated_date, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(j.activated_date, '+00:00', '+09:00') < "."'".$finishTime."'";
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
    AND u.active = 1
    AND u.resigned = 0
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
     * @throws Exception
     */
    public function getCountOfJobReEdit(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(ad.created_at, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(ad.created_at, '+00:00', '+09:00') < "."'".$finishTime."'";
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
    AND u.active = 1
    AND u.resigned = 0
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
     * @throws Exception
     */
    public function getCountOfSupplement(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(u.modified, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(u.modified, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_CLIENT_SUPPLEMENT;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
WHERE u.view_mode = 'outsource'
    AND u.verification_expiration IS NOT NULL
    AND u.active = 1
    AND u.resigned = 0
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
     * @throws Exception
     */
    public function getCountOfSettingThumbnail(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(thumbnail.created, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(thumbnail.created, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_CLIENT_SETTING_THUMBNAIL;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', COUNT(thumbnail.id) as 'count'
FROM users u
    INNER JOIN s3_docs thumbnail
        ON thumbnail.foreign_key = u.id
            AND thumbnail.group = 'thumbnail'
            {$sqlStartDay}
            {$sqlFinishDay}
WHERE u.view_mode = 'outsource'
    AND u.active = 1
    AND u.resigned = 0
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
     * @throws Exception
     */
    public function getCountOfApplyPartner(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(p.created, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(p.created, '+00:00', '+09:00') < "."'".$finishTime."'";
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
    AND u.active = 1
    AND u.resigned = 0
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
     * @throws Exception
     */
    public function getCountOfPaidDeffer(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(pd.modified, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(pd.modified, '+00:00', '+09:00') < "."'".$finishTime."'";
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
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
GROUP BY u.id
ORDER BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * 開始準備済みかどうかを取得する
     *
     * @param null|Carbon $finishTime 集計終了時
     * @param null|Carbon $startTime 集計開始時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getCountOfGettingStarted(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null): array
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(u.modified, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(u.modified, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_CLIENT_GETTING_STARTED;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
WHERE u.view_mode = 'outsource'
    AND u.group_id <> 7
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
    {$sqlStartDay}
    {$sqlFinishDay}
GROUP BY u.id
ORDER BY u.id
__SQL__;

        return DB::select($sql);
    }
}
