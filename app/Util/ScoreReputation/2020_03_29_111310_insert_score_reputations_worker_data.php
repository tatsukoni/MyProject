<?php

use Carbon\Carbon;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InsertScoreReputationsWorkerData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // ワーカーの行動データをscore_reputationsに挿入する
        $createdAt = Carbon::now();
        $scoreReputations = [
            ['id' => 1, 'reputation_name' => 'No01_【初】会員登録する', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['id' => 2, 'reputation_name' => 'No02_【初】開始準備', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['id' => 3, 'reputation_name' => 'No18_仕事に質問を投稿する', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['id' => 4, 'reputation_name' => 'No20_プロジェクト：仕事に応募する', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['id' => 5, 'reputation_name' => 'No21_タスク：納品する', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['id' => 6, 'reputation_name' => 'No22_タスク：報酬を獲得する', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['id' => 7, 'reputation_name' => 'No25_プロジェクト：納品する', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['id' => 8, 'reputation_name' => 'No26_プロジェクト：報酬を獲得する', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['id' => 9, 'reputation_name' => 'No27_プロジェクト：評価する', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['id' => 10, 'reputation_name' => 'No28_プロジェクト：再受注する', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['id' => 11, 'reputation_name' => 'No29_【初】アイコンを設定する', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['id' => 12, 'reputation_name' => 'No30_【初】自己紹介を設定する', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['id' => 13, 'reputation_name' => 'No39_【初】本人確認を設定する', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['id' => 14, 'reputation_name' => 'No44_報酬を受け取る/出金を行う', 'reputation_attribute' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt],
        ];
        DB::table('score_reputations')->insert($scoreReputations);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // 作成した score_reputations のデータを削除する
        // id=1 〜 id=14 が削除対象に該当する
        for ($index = 1; $index <= 14; $index++) {
            DB::table('score_reputations')
                ->where('id', $index)
                ->delete();
        }
    }
}
