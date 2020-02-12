<?php

namespace App\Jobs\Admin;

use App\Jobs\BaseJob;
use App\Domain\Admin\BalanceSheet;
use App\Mail\Mails\Admin\BalanceSheetReport;
use Carbon\Carbon;
use Mail;

/**
 * B/S を生成し、メールで送るジョブ
 */
class ActionScoreJob extends BaseJob
{
    // ジョブがタイムアウトになるまでの時間
    // 10 minutes
    public $timeout = 600;

    protected $targetData;
    protected $userIds;

    // 取得したい行動スコアリングデータの種類
    const USER_ACTION_COUNTS = 1;
    const USER_ACTION_SCORE = 2;

    /**
     * Create new job instance.
     *
     * @param string $startDate
     * @param string $endDate
     * @return void
     */
    public function __construct(int $targetData, array $userIds)
    {
        $this->targetData = $targetData;
        $this->userIds = $userIds;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 件数を取得する（可変）
        // return array

        // 結果をCSVファイルに出力し、メール送信
        $csv = $this->getCsv($resultArray);
        if ($this->failGetBunkId) {
            $this->sendMail(self::FAIL_GET_BANK_ID, $csv);
        } else {
            $this->sendMail(self::SUCCESS_MONTHLY_WITHDRAWAL, $csv);
        }
    }

    public function getCsv(array $resultArray): string
    {
        $stream = fopen('php://temp', 'w');
        // ヘッダー部分
        fputcsv($stream, ['user_id', '種別', '出金種別', '金額', 'created', '口座id', 'GMO上の口座id']);
        // 中身の部分の作成
        foreach ($resultArray as $result) {
            fputcsv($stream, $result);
        }

        rewind($stream);
        $csv = mb_convert_encoding(str_replace(PHP_EOL, "\r\n", stream_get_contents($stream)), 'SJIS-win', 'UTF-8');
        fclose($stream);

        return $csv;
    }

    private function sendMail(int $resultCode, string $csv = null): void
    {
        $targetMonth = Carbon::parse($this->startDate)->subMonth()->format('Ym');
        $fileName = $targetMonth . 'monthly_withdrawals_result.csv';

        Mail::send(new MonthlyWithdrawalReport(
            $resultCode,
            $csv,
            $fileName
        ));
    }
}
