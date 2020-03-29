<?php

namespace App\Console\Commands\ScoreReputation;

use App\Console\BaseCommand;
use App\Domain\ScoreReputation\ReputationCount;
use App\Models\ScoreReputation;

use Carbon\Carbon;
use Exception;
use Log;

class SaveWorkerAllReputationCount extends BaseCommand
{
    protected $signature = 'score:save_worker_all_reputation_count
                            {run_mode : 実行モード e.g. => dry or run}';

    protected $description = 'ワーカーの行動回数を全期間で保存する';

    const SQL_LIMIT_COUNTS = 15000; // 分割するレコード数

    // 保存対象の行動
    // 全ての行動が対象であるが、負荷分散のために一度配列に格納する
    const TARGET_REPUTATIONS = [
        ScoreReputation::ID_WORKER_REGISTRATION,
        ScoreReputation::ID_WORKER_GETTING_STARTED,
        ScoreReputation::ID_POST_QUESTION,
        ScoreReputation::ID_PROPOSAL,
        ScoreReputation::ID_TASK_DELIVERY,
        ScoreReputation::ID_TASK_GET_REWARD,
        ScoreReputation::ID_PROJECT_DELIVERY,
        ScoreReputation::ID_PROJECT_GET_REWARD,
        ScoreReputation::ID_WORKER_PROJECT_RATING,
        ScoreReputation::ID_PROJECT_ACCEPT_REORDER,
        ScoreReputation::ID_WORKER_SETTING_THUMBNAIL,
        ScoreReputation::ID_WORKER_SET_PROFILE,
        ScoreReputation::ID_WORKER_SET_SUPPLEMENT,
        ScoreReputation::ID_RECEIVE_REWARD
    ];

    protected function handleCommand()
    {
        // finishTime を設定する
        // 一括保存が実行される前に、前日分の回数は保存されるので、その期間は除外する
        $finishTime = Carbon::yesterday('Asia/Tokyo'); // 00:00:00
        $reputationCount = new ReputationCount();

        try {
            $targetRecordsCount = 0;
            foreach (self::TARGET_REPUTATIONS as $targetReputation) { // 各行動を順に分割して行う
                $offset = 0;
                while (true) {
                    $conditions = [
                        'finishTime' => $finishTime,
                        'limit' => self::SQL_LIMIT_COUNTS,
                        'offset' => $offset
                    ];
                    // 件数を分割し、レコードを取得する
                    $records = $reputationCount->getTargetWorkerReputationCount([$targetReputation], $conditions);
    
                    // 対象レコードがなくなった段階で、ループから抜ける
                    if (empty($records)) {
                        break;
                    }
    
                    if ($this->isDryRun) {
                        $targetRecordsCount += count($records);
                    } else {
                        // 保存処理を実行
                        $reputationCount->saveByRecords($records);
                    }
    
                    unset($records); // メモリ節約のため、保存終了後に解放する
                    $offset += self::SQL_LIMIT_COUNTS;
                }
            }

            // 処理終了後、動作モードに応じて終了通知を行う
            if ($this->isDryRun) {
                $this->info(sprintf(
                    'save_worker_all_reputation_count : dry モードで実行しました。対象レコード数 : %d 件',
                    $targetRecordsCount
                ));
            } else {
                $this->info('save_worker_all_reputation_count : 保存処理に成功しました。');
            }
        } catch (Exception $e) {
            Log::error('コマンド[' . $this->name . ']でエラーが発生しました ' . $e);
            return 2;
        }
    }
}
