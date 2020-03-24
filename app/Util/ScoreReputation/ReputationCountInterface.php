<?php

namespace App\Domain\ScoreReputation;

use Carbon\Carbon;

interface ReputationCountInterface
{
    /**
     * 全ての行動回数を取得する（クライアント or ワーカー）
     *
     * @param array $conditions 指定条件
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getAllReputationCount(array $conditions): array;

    /**
     * 対象の行動回数を取得する（クライアント or ワーカー）
     *
     * @param array $targetReputations
     * @param array $conditions 指定条件
     * @return array stdClassに格納したuser_id（ユーザーID）とreputation_id（行動ID）とcount（行動数）の配列
     * @throws Exception
     */
    public function getTargetReputationCount(array $targetReputations, array $conditions): array;
}
