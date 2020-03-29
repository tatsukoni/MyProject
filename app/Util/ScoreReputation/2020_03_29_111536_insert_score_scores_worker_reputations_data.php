<?php

use Carbon\Carbon;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InsertScoreScoresWorkerReputationsData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 新たに追加されるワーカーの行動ごとのスコアを既存データとして作成する
        $createdAt = Carbon::now();
        $scoreScores = [
            ['score_reputation_id' => 1, 'is_every_time' => 0, 'score' => 1, 'bonus_count' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 【初】会員登録する：初回のみ得点付与
            ['score_reputation_id' => 2, 'is_every_time' => 0, 'score' => 1, 'bonus_count' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 【初】開始準備：初回のみ得点付与
            ['score_reputation_id' => 3, 'is_every_time' => 1, 'score' => 1, 'bonus_count' => null, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 仕事に質問を投稿する
            ['score_reputation_id' => 4, 'is_every_time' => 1, 'score' => 1, 'bonus_count' => null, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 仕事に応募する
            ['score_reputation_id' => 5, 'is_every_time' => 1, 'score' => 1, 'bonus_count' => null, 'created_at' => $createdAt, 'updated_at' => $createdAt], // タスク：納品する
            ['score_reputation_id' => 6, 'is_every_time' => 0, 'score' => 5, 'bonus_count' => 20, 'created_at' => $createdAt, 'updated_at' => $createdAt], // タスク：報酬を獲得する：20回目の特別ボーナススコア
            ['score_reputation_id' => 6, 'is_every_time' => 0, 'score' => 10, 'bonus_count' => 30, 'created_at' => $createdAt, 'updated_at' => $createdAt], // タスク：報酬を獲得する：30回目のボーナススコア
            ['score_reputation_id' => 6, 'is_every_time' => 0, 'score' => 15, 'bonus_count' => 50, 'created_at' => $createdAt, 'updated_at' => $createdAt], // タスク：報酬を獲得する：50回目のボーナススコア
            ['score_reputation_id' => 7, 'is_every_time' => 1, 'score' => 5, 'bonus_count' => null, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：納品する
            ['score_reputation_id' => 8, 'is_every_time' => 1, 'score' => 5, 'bonus_count' => null, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：報酬を獲得する：毎回加算スコア
            ['score_reputation_id' => 8, 'is_every_time' => 0, 'score' => 10, 'bonus_count' => 10, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：報酬を獲得する：10回目の特別ボーナススコア
            ['score_reputation_id' => 8, 'is_every_time' => 0, 'score' => 20, 'bonus_count' => 20, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：報酬を獲得する：20回目の特別ボーナススコア
            ['score_reputation_id' => 8, 'is_every_time' => 0, 'score' => 30, 'bonus_count' => 30, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：報酬を獲得する：30回目の特別ボーナススコア
            ['score_reputation_id' => 8, 'is_every_time' => 0, 'score' => 50, 'bonus_count' => 50, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：報酬を獲得する：50回目の特別ボーナススコア
            ['score_reputation_id' => 9, 'is_every_time' => 1, 'score' => 1, 'bonus_count' => null, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：評価する
            ['score_reputation_id' => 10, 'is_every_time' => 1, 'score' => 10, 'bonus_count' => null, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：再受注する：毎回加算スコア
            ['score_reputation_id' => 10, 'is_every_time' => 0, 'score' => 20, 'bonus_count' => 10, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：再受注する：10回目の特別ボーナススコア
            ['score_reputation_id' => 10, 'is_every_time' => 0, 'score' => 40, 'bonus_count' => 20, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：再受注する：20回目の特別ボーナススコア
            ['score_reputation_id' => 10, 'is_every_time' => 0, 'score' => 60, 'bonus_count' => 30, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：再受注する：30回目の特別ボーナススコア
            ['score_reputation_id' => 10, 'is_every_time' => 0, 'score' => 100, 'bonus_count' => 50, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：再受注する：50回目の特別ボーナススコア
            ['score_reputation_id' => 11, 'is_every_time' => 0, 'score' => 1, 'bonus_count' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 【初】アイコンを設定する
            ['score_reputation_id' => 12, 'is_every_time' => 0, 'score' => 1, 'bonus_count' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 【初】自己紹介を設定する
            ['score_reputation_id' => 13, 'is_every_time' => 0, 'score' => 1, 'bonus_count' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 【初】本人確認を設定する
            ['score_reputation_id' => 14, 'is_every_time' => 1, 'score' => 1, 'bonus_count' => null, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 報酬を受け取る/出金を行う
        ];
        DB::table('score_scores')->insert($scoreScores);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // 作成した score_reputations のデータを削除する
        // score_reputation_id=1 〜 score_reputation_id=14 が削除対象に該当する
        for ($index = 1; $index <= 14; $index++) {
            DB::table('score_scores')
                ->where('score_reputation_id', $index)
                ->delete();
        }
    }
}
