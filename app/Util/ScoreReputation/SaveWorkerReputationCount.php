<?php

namespace App\Console\Commands\ScoreReputation;

use App\Console\BaseCommand;
use App\Domain\ScoreReputation\ReputationCount;

use Carbon\Carbon;
use Exception;
use Log;

class SaveWorkerReputationCount extends BaseCommand
{
    protected $signature = 'score:save_worker_reputation_count
                            {run_mode : 実行モード e.g. => dry or run}
                            {finishTime? : 集計終了日時}';

    protected $description = '集計対象の1日間でのワーカーの行動回数を保存する。
                              デフォルトでは前日分のワーカーの行動回数を保存する。';

    protected function handleCommand()
    {
        // 集計対象期間を設定する
        $period = $this->getTargetPeriod();
        if (empty($period)) { // 入力されたフォーマットが「Y-m-d」形式でない場合
            return 1;
        }

        $reputationCount = new ReputationCount();
        $conditions = [
            'startTime' => $period['startTime'],
            'finishTime' => $period['finishTime']
        ];

        try {
            // 集計対象期間で、全てのワーカーの行動回数レコードを取得する
            $records = $reputationCount->getAllWorkerReputationCount($conditions);
            $targetRecordCount = count($records);

            // dry run 実行時
            if ($this->isDryRun) {
                $this->info(sprintf(
                    'score:save_worker_reputation_count : dry モードで実行しました。対象レコード数 : %d 件',
                    $targetRecordCount
                ));
                return;
            }
            // 保存対象のレコードが存在しなかった場合
            if ($targetRecordCount === 0) {
                $this->info('score:save_worker_reputation_count : 保存対象のレコードは存在しませんでした');
                return;
            }

            // 保存処理を実行する
            $reputationCount->saveByRecords($records);
            $this->info(sprintf(
                'score:save_worker_reputation_count : 保存処理に成功しました。対象レコード数 : %d 件',
                $targetRecordCount
            ));
        } catch (Exception $e) {
            Log::error('コマンド[' . $this->name . ']でエラーが発生しました ' . $e);
            return 2;
        }
    }

    /**
     * 集計対象期間を配列として返却する
     *
     * @return array
     */
    private function getTargetPeriod(): array
    {
        $period = [];

        if ($this->argument('finishTime')) {
            if (! Carbon::hasFormat($this->argument('finishTime'), 'Y-m-d')) {
                $this->error('finishTime は Y-m-d の形式で指定してください');
            } else {
                $period['finishTime'] = Carbon::parse($this->argument('finishTime'), 'Asia/Tokyo')
                    ->setTime(0, 0, 0);
                $period['startTime'] = $period['finishTime']->copy()->subDay(); // 集計終了日時の1日前
            }
        } else {
            $period['finishTime'] = Carbon::today('Asia/Tokyo'); // 今日の 00:00:00 デフォルト値
            $period['startTime'] = Carbon::yesterday('Asia/Tokyo'); // 昨日の 00:00:00 デフォルト値
        }

        return $period;
    }
}
