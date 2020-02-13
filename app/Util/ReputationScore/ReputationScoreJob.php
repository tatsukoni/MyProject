<?php

namespace App\Jobs\Admin;

use App\Jobs\BaseJob;
use Carbon\Carbon;
use Mail;

use App\Mail\Mails\Admin\ReputationScoreReport;
use App\Models\ScoreUserReputationCount;
use App\Models\ScoreScore;

class ReputationScoreJob extends BaseJob
{
    // ジョブがタイムアウトになるまでの時間
    public $timeout = 600;

    public $failGetUsers;
    protected $userIds;

    // 処理結果に応じてメール送信処理を分ける
    const FAIL_ALL_TARGET_USERS = 1;
    const FAIL_SOME_TARGET_USERS = 2;
    const SUCCESS_REPUTATION_SCORE = 3;

    /**
     * Create new job instance.
     *
     * @param string $startDate
     * @param string $endDate
     * @return void
     */
    public function __construct(array $userIds)
    {
        $this->failGetUsers = [];
        $this->userIds = $userIds;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $resultArray = $this->getResultArray($this->userIds);

        if (empty($resultArray)) { // 0件だった場合は、CSVを添付せず失敗メールを送信する
            $this->sendMail(self::FAIL_ALL_TARGET_USERS, $this->failGetUsers);
            return;
        }

        $headerArray = [
            'user_id',
            'シュフティスコア'
        ];
        $csvData = $this->getCsv($headerArray, $resultArray);

        if (empty($this->failGetUsers)) {
            $this->sendMail(self::SUCCESS_REPUTATION_SCORE, $this->failGetUsers, $csvData);
        } else {
            $this->sendMail(self::FAIL_SOME_TARGET_USERS, $this->failGetUsers, $csvData);
        }
    }

    private function getResultArray(array $userIds): array
    {
        $resultArray = [];

        foreach ($userIds as $userId) {
            $userScore = ScoreUserReputationCount::getUserScore($userId);
            if (! $userScore) {
                $this->failGetUsers[] = $userId;
                continue;
            }
            $resultArray[] = [
                $userId,
                $userScore
            ];
        }

        return $resultArray;
    }

    private function getCsv(array $headerArray, array $resultArray): string
    {
        $stream = fopen('php://temp', 'w');

        // ヘッダー部分
        fputcsv($stream, $headerArray);
        // 中身の部分の作成
        foreach ($resultArray as $result) {
            fputcsv($stream, $result);
        }

        rewind($stream);
        $csv = mb_convert_encoding(str_replace(PHP_EOL, "\r\n", stream_get_contents($stream)), 'SJIS-win', 'UTF-8');
        fclose($stream);

        return $csv;
    }

    private function sendMail(int $resultCode, array $failGetUsers, ?string $csv = null): void
    {
        $fileName = Carbon::now('Asia/Tokyo')->format('Ymd') . '_reputation_scores.csv';

        Mail::send(new ReputationScoreReport(
            $resultCode,
            $failGetUsers,
            $csv,
            $fileName
        ));
    }
}
