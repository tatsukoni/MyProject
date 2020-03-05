<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InsertScoreScoresData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 行動Idが変わったので、score_scores の既存データは先に削除する
        DB::table('score_scores')->delete();

        // 新たに追加される行動ごとのスコアを既存データとして作成する
        $createdAt = Carbon::now();
        $scoreScores = [
            ['score_reputation_id' => 201, 'is_every_time' => 0, 'score' => 1, 'bonus_count' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 【初】会員登録する：初回のみ得点付与
            ['score_reputation_id' => 202, 'is_every_time' => 0, 'score' => 1, 'bonus_count' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 【初】開始準備：初回のみ得点付与
            ['score_reputation_id' => 203, 'is_every_time' => 0, 'score' => 1, 'bonus_count' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 【初】初回審査：初回のみ得点付与
            ['score_reputation_id' => 204, 'is_every_time' => 0, 'score' => 1, 'bonus_count' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 【初】本人確認提出：初回のみ得点付与
            ['score_reputation_id' => 205, 'is_every_time' => 1, 'score' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 差し戻された仕事を修正して再申請する
            ['score_reputation_id' => 206, 'is_every_time' => 1, 'score' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // タスク,プロジェクト：仕事が承認される（前・後払い共通）
            ['score_reputation_id' => 207, 'is_every_time' => 0, 'score' => 5, 'bonus_count' => 20, 'created_at' => $createdAt, 'updated_at' => $createdAt], // タスク：納品物の検品をする（承認）：20回目の特別ボーナススコア
            ['score_reputation_id' => 207, 'is_every_time' => 0, 'score' => 10, 'bonus_count' => 30, 'created_at' => $createdAt, 'updated_at' => $createdAt], // タスク：納品物の検品をする（承認）：30回目の特別ボーナススコア
            ['score_reputation_id' => 207, 'is_every_time' => 0, 'score' => 25, 'bonus_count' => 50, 'created_at' => $createdAt, 'updated_at' => $createdAt], // タスク：納品物の検品をする（承認）：50回目の特別ボーナススコア
            ['score_reputation_id' => 209, 'is_every_time' => 1, 'score' => 5, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：発注する：毎回加算スコア
            ['score_reputation_id' => 209, 'is_every_time' => 0, 'score' => 20, 'bonus_count' => 20, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：発注する：20回目の特別ボーナススコア
            ['score_reputation_id' => 209, 'is_every_time' => 0, 'score' => 30, 'bonus_count' => 30, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：発注する：30回目の特別ボーナススコア
            ['score_reputation_id' => 209, 'is_every_time' => 0, 'score' => 50, 'bonus_count' => 50, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：発注する：50回目の特別ボーナススコア
            ['score_reputation_id' => 210, 'is_every_time' => 1, 'score' => 8, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：納品物の検品をする（承認）：毎回加算スコア
            ['score_reputation_id' => 210, 'is_every_time' => 0, 'score' => 20, 'bonus_count' => 10, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：納品物の検品をする（承認）：10回目の特別ボーナススコア
            ['score_reputation_id' => 210, 'is_every_time' => 0, 'score' => 40, 'bonus_count' => 20, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：納品物の検品をする（承認）：20回目の特別ボーナススコア
            ['score_reputation_id' => 210, 'is_every_time' => 0, 'score' => 60, 'bonus_count' => 30, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：納品物の検品をする（承認）：30回目の特別ボーナススコア
            ['score_reputation_id' => 210, 'is_every_time' => 0, 'score' => 100, 'bonus_count' => 50, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：納品物の検品をする（承認）：50回目の特別ボーナススコア
            ['score_reputation_id' => 211, 'is_every_time' => 1, 'score' => 3, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：納品物の検品をする（差し戻し）
            ['score_reputation_id' => 212, 'is_every_time' => 1, 'score' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：評価する
            ['score_reputation_id' => 213, 'is_every_time' => 1, 'score' => 10, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：再発注する：毎回加算スコア
            ['score_reputation_id' => 213, 'is_every_time' => 0, 'score' => 20, 'bonus_count' => 10, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：再発注する：10回目の特別ボーナススコア
            ['score_reputation_id' => 213, 'is_every_time' => 0, 'score' => 40, 'bonus_count' => 20, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：再発注する：20回目の特別ボーナススコア
            ['score_reputation_id' => 213, 'is_every_time' => 0, 'score' => 60, 'bonus_count' => 30, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：再発注する：30回目の特別ボーナススコア
            ['score_reputation_id' => 213, 'is_every_time' => 0, 'score' => 100, 'bonus_count' => 50, 'created_at' => $createdAt, 'updated_at' => $createdAt], // プロジェクト：再発注する：50回目の特別ボーナススコア
            ['score_reputation_id' => 214, 'is_every_time' => 0, 'score' => 1, 'bonus_count' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 【初】アイコンを設定する：初回のみ得点付与
            ['score_reputation_id' => 215, 'is_every_time' => 0, 'score' => 1, 'bonus_count' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 【初】自己紹介を設定する：初回のみ得点付与
            ['score_reputation_id' => 216, 'is_every_time' => 1, 'score' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // パートナー申請する
            ['score_reputation_id' => 217, 'is_every_time' => 1, 'score' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt], // 後払いの代金を支払う
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
        // 新たに追加されたレコードを削除する
        for ($index = 201; $index <= 217; $index++) {
            DB::table('score_scores')
                ->where('id', $index)
                ->delete();
        }

        // 実行前に存在していたレコードを作成する
        $createdAt = Carbon::now();
        $score = [
            'score_reputation_id' => 1,
            'is_every_time' => 1,
            'score' => 1,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
        DB::table('score_scores')->insert($score);
    }
}
