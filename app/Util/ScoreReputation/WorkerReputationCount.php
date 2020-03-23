<?php

namespace App\Domain\ScoreReputation;

use App\Domain\ScoreReputation\ReputationCountAbstract;
use App\Models\ScoreReputation;

use Carbon\Carbon;
use DB;

/**
 * スコアリング対象の行動回数を取得する（ワーカー）
 */
class WorkerReputationCount extends ReputationCountAbstract
{
    // 対象の行動と、その回数を取得するメソッド名との紐付けを行う
    // 1:1の関係がある行動が対象となっている
    // 何かの行動回数を取得する関数を作成した場合は、下記に追加してください
    const TARGET_REPUTATION_METHODS = [
        ScoreReputation::ID_WORKER_REGISTRATION => 'getCountOfRegistration', // 【初】会員登録する
        ScoreReputation::ID_WORKER_GETTING_STARTED => 'getCountOfGettingStarted', // 【初】開始準備
        ScoreReputation::ID_POST_QUESTION => 'getCountOfPostQuestion', // 質問を投稿する
        ScoreReputation::ID_PROPOSAL => 'getCounttOfProposal', // 仕事に応募する
        ScoreReputation::ID_TASK_DELIVERY => 'getCountOfTaskDelivery', // タスク：納品する
        ScoreReputation::ID_TASK_GET_REWARD => 'getCountOfTaskGetReward', // タスク：報酬を獲得する
        ScoreReputation::ID_PROJECT_DELIVERY => 'getCountOfProjectDelivery', // プロジェクト：納品する
        ScoreReputation::ID_PROJECT_GET_REWARD => 'getCountOfProjectGetRewards', // プロジェクト：報酬を獲得する
        ScoreReputation::ID_WORKER_PROJECT_RATING => 'getCountOfRating', // プロジェクト：評価する
        ScoreReputation::ID_PROJECT_ACCEPT_REORDER => 'getCountOfAcceptReorder', // プロジェクト：再受注する
        ScoreReputation::ID_WORKER_SETTING_THUMBNAIL => 'getCountOfSettingThumbnail', // 【初】アイコンを設定する
        ScoreReputation::ID_WORKER_SET_PROFILE => 'getCountOfSetProfile', // 【初】自己紹介を設定する
        ScoreReputation::ID_WORKER_SET_SUPPLEMENT => 'getCountOfSetSupplement', // 【初】本人確認を設定する
        ScoreReputation::ID_RECEIVE_REWARD => 'getCountOfReceiveReward', // 報酬を受け取る
    ];

    /**
     * 全ての行動回数を取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getAllReputationCount(
        Carbon $finishTime = null,
        Carbon $startTime = null,
        array $userIds = null
    ): array {
        $records = [];

        foreach (self::TARGET_REPUTATION_METHODS as $targetReputation => $targetMethod) {
            $records = array_merge($records, $this->$targetMethod($finishTime, $startTime, $userIds));
        }

        return $records;
    }

    /**
     * 対象の行動回数を取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getTargetReputationCount(
        array $targetReputations,
        Carbon $finishTime = null,
        Carbon $startTime = null,
        array $userIds = null
    ): array {
        $records = [];

        // 1:1関係にある行動回数を返却する
        foreach ($targetReputations as $targetReputation) {
            if (array_key_exists($targetReputation, self::TARGET_REPUTATION_METHODS)) {
                $method = self::TARGET_REPUTATION_METHODS[$targetReputation];
                $records = array_merge($records, $this->$method($finishTime, $startTime, $userIds));
            }
        }

        return $records;
    }

    /**
     * 【初】会員登録したかどうかを取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
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
        $reputationId = ScoreReputation::ID_WORKER_REGISTRATION;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
WHERE u.view_mode = 'contract'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
    {$sqlStartDay}
    {$sqlFinishDay}
__SQL__;

        return DB::select($sql);
    }

    /**
     * 【初】開始準備済みかどうかを取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
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
        $reputationId = ScoreReputation::ID_WORKER_GETTING_STARTED;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
WHERE u.view_mode = 'contract'
    AND u.group_id <> 7
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
    {$sqlStartDay}
    {$sqlFinishDay}
__SQL__;

        return DB::select($sql);
    }

    /**
     * 仕事に質問を投稿した回数を取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getCountOfPostQuestion(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(th.created, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(th.created, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_POST_QUESTION;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', count(th.id) as 'count'
FROM users u
    INNER JOIN threads th
        ON u.id = th.user_id
            AND th.job_id IS NOT NULL
            {$sqlStartDay}
            {$sqlFinishDay}
    INNER JOIN walls w
        ON th.wall_id = w.id
            AND w.wall_type_id = 4
WHERE u.view_mode = 'contract'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
GROUP BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * 仕事に応募した回数を取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getCounttOfProposal(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(jr.created, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(jr.created, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_PROPOSAL;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', count(jr.id) as 'count'
FROM users u
    INNER JOIN job_roles jr
        ON u.id = jr.user_id
            AND jr.role_id = 2
            {$sqlStartDay}
            {$sqlFinishDay}
WHERE u.view_mode = 'contract'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
GROUP BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * タスク：納品した回数を取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getCountOfTaskDelivery(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(tt.created, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(tt.created, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_TASK_DELIVERY;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', count(tt.id) as 'count'
FROM users u
    INNER JOIN task_trades tt
        ON u.id = tt.contractor_id
            AND tt.state = 5
            {$sqlStartDay}
            {$sqlFinishDay}
WHERE u.view_mode = 'contract'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
GROUP BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * タスク：報酬を獲得した回数を取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getCountOfTaskGetReward(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(w.created, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(w.created, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_TASK_GET_REWARD;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', count(w.id) as 'count'
FROM users u
    INNER JOIN worker_rewards w
        ON u.id = w.user_id
            {$sqlStartDay}
            {$sqlFinishDay}
    INNER JOIN jobs j
        ON w.job_id = j.id
            AND j.type = 2
WHERE u.view_mode = 'contract'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
GROUP BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * プロジェクト：納品した回数を取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getCountOfProjectDelivery(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(t.created, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(t.created, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_PROJECT_DELIVERY;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', count(t.id) as 'count'
FROM users u
    INNER JOIN trades t
        ON u.id = t.contractor_id
            AND t.state = 5
            {$sqlStartDay}
            {$sqlFinishDay}
WHERE u.view_mode = 'contract'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
GROUP BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * プロジェクト：報酬を獲得した回数を取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getCountOfProjectGetRewards(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(t.modified, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(t.modified, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_PROJECT_GET_REWARD;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', count(t.id) as 'count'
FROM users u
    INNER JOIN trades t
        ON u.id = t.contractor_id
            AND t.state = 5
            AND t.selected IN (122, 126)
            {$sqlStartDay}
            {$sqlFinishDay}
WHERE u.view_mode = 'contract'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
GROUP BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * プロジェクト：評価した回数を取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getCountOfRating(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(r.modified, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(r.modified, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_WORKER_PROJECT_RATING;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', count(r.id) as 'count'
FROM users u
    INNER JOIN ratings r
        ON u.id = r.respondent
            {$sqlStartDay}
            {$sqlFinishDay}
WHERE u.view_mode = 'contract'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
GROUP BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * プロジェクト：再受注した回数を取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getCountOfAcceptReorder(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(t.modified, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(t.modified, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_PROJECT_ACCEPT_REORDER;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', count(t.id) as 'count'
FROM users u
    INNER JOIN trades t
        ON u.id = t.contractor_id
            AND t.selected = 129
            {$sqlStartDay}
            {$sqlFinishDay}
WHERE u.view_mode = 'contract'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
GROUP BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * 【初】アイコンが設定されているかどうかを取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
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
        $reputationId = ScoreReputation::ID_WORKER_SETTING_THUMBNAIL;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
    INNER JOIN s3_docs thumbnail
        ON thumbnail.foreign_key = u.id
            AND thumbnail.model = 'User'
            AND thumbnail.group = 'thumbnail'
            {$sqlStartDay}
            {$sqlFinishDay}
WHERE u.view_mode = 'contract'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
__SQL__;

        return DB::select($sql);
    }

    /**
     * 【初】自己紹介が設定されているかどうかを取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
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
            $sqlStartDay = "AND CONVERT_TZ(sp.created, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(sp.created, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_WORKER_SET_PROFILE;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
    INNER JOIN selling_points sp
        ON sp.user_id = u.id
            {$sqlStartDay}
            {$sqlFinishDay}
WHERE u.view_mode = 'contract'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
__SQL__;

        return DB::select($sql);
    }

    /**
     * 【初】本人確認を設定したかどうかを取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getCountOfSetSupplement(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
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
        $reputationId = ScoreReputation::ID_WORKER_SET_SUPPLEMENT;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
WHERE u.view_mode = 'contract'
    AND (u.verified = 1 OR u.verification_expiration IS NOT NULL)
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
    {$sqlStartDay}
    {$sqlFinishDay}
GROUP BY u.id
__SQL__;

        return DB::select($sql);
    }

    /**
     * 報酬を受け取った回数を取得する
     *
     * @param null|Carbon $startTime 集計開始時
     * @param null|Carbon $finishTime 集計終了時
     * @param null|array $userIds ユーザーIDの配列
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getCountOfReceiveReward(Carbon $finishTime = null, Carbon $startTime = null, array $userIds = null)
    {
        $sqlUserIds = '';
        $sqlStartDay = '';
        $sqlFinishDay = '';

        if (! is_null($userIds)) {
            $sqlUserIds = $this->getUserIds($userIds);
        }
        if (! is_null($startTime)) {
            $sqlStartDay = "AND CONVERT_TZ(pl.created, '+00:00', '+09:00') >= "."'".$startTime."'";
        }
        if (! is_null($finishTime)) {
            $sqlFinishDay = "AND CONVERT_TZ(pl.created, '+00:00', '+09:00') < "."'".$finishTime."'";
        }
        $reputationId = ScoreReputation::ID_RECEIVE_REWARD;

        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', count(pd.id) as 'count'
FROM users u
    INNER JOIN point_details pd
        ON u.id = pd.user_id
            AND pd.account_id = 4
            AND pd.account_title_id = 11
    INNER JOIN point_logs pl
        ON pd.point_log_id = pl.id
            AND pl.detail = 3
            {$sqlStartDay}
            {$sqlFinishDay}
WHERE u.view_mode = 'contract'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
GROUP BY u.id
__SQL__;

        return DB::select($sql);
    }
}
