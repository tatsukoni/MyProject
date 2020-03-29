<?php

namespace App\Domain\ScoreReputation;

use App\Models\ScoreReputation;

use Carbon\Carbon;
use DB;
use Exception;

/**
 * スコアリング対象の行動回数を取得する（ワーカー）
 */
class WorkerReputationCount implements ReputationCountInterface
{
    use ReputationCountTrait;

    // 対象の行動と、その回数を取得するメソッド名との紐付けを行う
    // 1:1の関係がある行動が対象となっている
    // 何かの行動回数を取得する関数を作成した場合は、下記に追加してください
    const TARGET_REPUTATION_METHODS = [
        ScoreReputation::ID_WORKER_REGISTRATION => 'getCountOfRegistration', // 【初】会員登録する
        ScoreReputation::ID_WORKER_GETTING_STARTED => 'getCountOfGettingStarted', // 【初】開始準備
        ScoreReputation::ID_POST_QUESTION => 'getCountOfPostQuestion', // 質問を投稿する
        ScoreReputation::ID_PROPOSAL => 'getCountOfProposal', // 仕事に応募する
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
     * @param array $conditions 指定条件
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getAllReputationCount(array $conditions): array
    {
        if (! $this->checkConditions($conditions)) {
            throw new Exception('引数で渡された$conditionsが適切でありません');
        }
        $records = [];

        // 全ての行動回数を返却する
        foreach (array_values(self::TARGET_REPUTATION_METHODS) as $targetMethod) {
            $records = array_merge($records, $this->$targetMethod($conditions));
        }

        return $records;
    }

    /**
     * 対象の行動回数を取得する
     *
     * @param array $targetReputations 指定したい行動IDの配列
     * @param array $conditions 指定条件
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getTargetReputationCount(array $targetReputations, array $conditions): array
    {
        if (! $this->checkConditions($conditions)) {
            throw new Exception('引数で渡された$conditionsが適切でありません');
        }
        $records = [];

        // 1:1関係にある行動回数を返却する
        foreach ($targetReputations as $targetReputation) {
            if (array_key_exists($targetReputation, self::TARGET_REPUTATION_METHODS)) {
                $method = self::TARGET_REPUTATION_METHODS[$targetReputation];
                $records = array_merge($records, $this->$method($conditions));
            }
        }

        return $records;
    }

    /**
     * 【初】会員登録したかどうかを取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions 指定条件
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfRegistration(array $conditions): array
    {
        // 指定条件に基づき、条件指定句を変化させる
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 'u.created');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 'u.created');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_WORKER_REGISTRATION;
        // 対象のSQL
        $sql = <<<__SQL__
SELECT u.id as 'user_id', {$reputationId} as 'reputation_id', 1 as 'count'
FROM users u
WHERE u.view_mode = 'contract'
    AND u.active = 1
    AND u.resigned = 0
    {$sqlUserIds}
    {$sqlStartDay}
    {$sqlFinishDay}
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }

    /**
     * 【初】開始準備済みかどうかを取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfGettingStarted(array $conditions): array
    {
        // 指定条件に基づき、条件指定句を変化させる
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 'u.modified');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 'u.modified');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_WORKER_GETTING_STARTED;
        // 対象のSQL
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
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }

    /**
     * 仕事に質問を投稿した回数を取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfPostQuestion(array $conditions)
    {
        // 指定条件に基づき、条件指定句を変化させる
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 'th.created');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 'th.created');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_POST_QUESTION;
        // 対象のSQL
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
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }

    /**
     * 仕事に応募した回数を取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfProposal(array $conditions)
    {
        // 指定条件に基づき、条件指定句を変化させる
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 'jr.created');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 'jr.created');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_PROPOSAL;
        // 対象のSQL
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
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }

    /**
     * タスク：納品した回数を取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfTaskDelivery(array $conditions)
    {
        // 指定条件に基づき、条件指定句を変化させる
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 'tt.created');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 'tt.created');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_TASK_DELIVERY;
        // 対象のSQL
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
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }

    /**
     * タスク：報酬を獲得した回数を取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfTaskGetReward(array $conditions)
    {
        // 指定条件に基づき、条件指定句を変化させる
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 'w.created');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 'w.created');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_TASK_GET_REWARD;
        // 対象のSQL
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
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }

    /**
     * プロジェクト：納品した回数を取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfProjectDelivery(array $conditions)
    {
        // 指定条件に基づき、条件指定句を変化させる
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 't.created');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 't.created');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_PROJECT_DELIVERY;
        // 対象のSQL
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
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }

    /**
     * プロジェクト：報酬を獲得した回数を取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfProjectGetRewards(array $conditions)
    {
        // 指定条件に基づき、条件指定句を変化させる
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 't.modified');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 't.modified');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_PROJECT_GET_REWARD;
        // 対象のSQL
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
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }

    /**
     * プロジェクト：評価した回数を取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfRating(array $conditions)
    {
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 'r.modified');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 'r.modified');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_WORKER_PROJECT_RATING;
        // 対象のSQL
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
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }

    /**
     * プロジェクト：再受注した回数を取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfAcceptReorder(array $conditions)
    {
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 't.modified');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 't.modified');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_PROJECT_ACCEPT_REORDER;
        // 対象のSQL
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
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }

    /**
     * 【初】アイコンが設定されているかどうかを取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfSettingThumbnail(array $conditions)
    {
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 'thumbnail.created');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 'thumbnail.created');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_WORKER_SETTING_THUMBNAIL;
        // 対象のSQL
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
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }

    /**
     * 【初】自己紹介が設定されているかどうかを取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfSetProfile(array $conditions): array
    {
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 'sp.created');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 'sp.created');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_WORKER_SET_PROFILE;
        // 対象のSQL
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
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }

    /**
     * 【初】本人確認を設定したかどうかを取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfSetSupplement(array $conditions)
    {
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 'u.modified');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 'u.modified');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_WORKER_SET_SUPPLEMENT;
        // 対象のSQL
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
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }

    /**
     * 報酬を受け取った回数を取得する
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @param array $conditions
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    private function getCountOfReceiveReward(array $conditions)
    {
        $sqlUserIds = $this->getSqlUserIds($conditions);
        $sqlStartDay = $this->getSqlStartDay($conditions, 'pl.created');
        $sqlFinishDay = $this->getSqlFinishDay($conditions, 'pl.created');
        $sqlChunkQuery = $this->getSqlChunkQuery($conditions);

        $reputationId = ScoreReputation::ID_RECEIVE_REWARD;
        // 対象のSQL
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
{$sqlChunkQuery}
__SQL__;

        return DB::select($sql);
    }
}
