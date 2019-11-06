<?php

namespace App\Jobs\Admin;

use App\Jobs\BaseJob;
use App\Mail\Mails\Admin\MonthlyWithdrawalReport;
use App\Models\PointDetail;
use App\Models\PointLog;
use App\Services\PaymentService\PaymentClient;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Mail;

class MonthlyWithdrawal extends BaseJob
{
    /**
     * ジョブがタイムアウトになるまでの秒数
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes

    protected $startDate;
    protected $endDate;
    protected $failGetBunkId = false; // bank_idの取得に失敗したレコードがある場合true

    // 結果ごとに処理を分けるためのコード番号
    const NOT_EXIST_MONTHLY_WITHDRAWAL = 1;
    const MAX_COUNT_MONTHLY_WITHDRAWAL = 2;
    const FAIL_GET_BANK_ID = 3;
    const SUCCESS_MONTHLY_WITHDRAWAL = 4;

    const MAX_DATA_COUNT = 1000; // 取得するデータ件数の上限値

    /**
     * Create new job instance.
     *
     * @param string $startDate
     * @param string $endDate
     * @return void
     */
    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 月跨ぎの出金に該当する情報を取得
        $records = $this->getMonthlyWithdrawal();
        if ($records->count() === 0) { // 該当するデータ件数が0件
            $this->sendMail(self::NOT_EXIST_MONTHLY_WITHDRAWAL);
            return;
        }
        if ($records->count() >= self::MAX_DATA_COUNT) { // 該当するデータ件数が上限（1000件）以上
            $this->sendMail(self::MAX_COUNT_MONTHLY_WITHDRAWAL);
            return;
        }

        // CSV作成のための配列を作成する処理
        $resultArray = $this->generateResultsArray($records);

        // 結果をCSVファイルに出力し、メール送信
        $csv = $this->getCsv($resultArray);
        if ($this->failGetBunkId) {
            $this->sendMail(self::FAIL_GET_BANK_ID, $csv);
        } else {
            $this->sendMail(self::SUCCESS_MONTHLY_WITHDRAWAL, $csv);
        }
    }

    /**
     * 指定された日付で、月跨ぎの出金に該当する情報を取得する
     *
     * @return Collection
     */
    public function getMonthlyWithdrawal(): Collection
    {
        $record = DB::table('point_logs')
            ->select('users.id as user_id')
            ->AddSelect('users.view_mode')
            ->AddSelect('point_details.account_title_id')
            ->AddSelect('point_details.withdrawal')
            ->AddSelect(DB::raw("convert_tz(point_logs.created, '+0:00', '+09:00') AS created"))
            ->join('point_details', function ($join) {
                $join->on('point_details.point_log_id', '=', 'point_logs.id')
                     ->where('account_id', PointDetail::ESCROW_ACCOUNT);
            })
            ->join('users', function ($join) {
                $join->on('users.id', '=', 'point_details.user_id');
            })
            ->where('point_logs.detail', PointLog::PERMIT_POINTS_CONVERSION)
            ->whereRaw(
                "convert_tz(point_logs.created, '+0:00', '+09:00') >= ?",
                $this->startDate
            )
            ->whereRaw(
                "convert_tz(point_logs.created, '+0:00', '+09:00') < ?",
                $this->endDate
            )
            ->orderBy('users.view_mode', 'asc')
            ->orderBy('users.id', 'asc');

        return $record->get();
    }

    /**
     * ユーザーIDに紐付くbank_idを取得し、CSV出力のための配列を作成する
     *
     * @param Collection $records
     * @return array
     */
    public function generateResultsArray(Collection $records): array
    {
        $paymentClient = resolve(PaymentClient::class);
        $resultArray = [];

        foreach ($records as $record) {
            // view_modeの設定
            $viewMode = ($record->view_mode === 'contract') ? 'ワーカー' : 'クライアント';
            // 出金種別の設定
            $accountTitle = ($record->account_title_id == PointDetail::PAYMENT) ? '出金額' : '換金手数料';
            // 口座idの取得
            $bank = $paymentClient->getBankAccount($record->user_id);
            if (! is_null($bank)) {
                $bankId = $bank->id;
                $gmoBankId = $this->generateGmoBankId($bankId) . $bankId;
            } else {
                $this->failGetBunkId = true;
                $bankId = '口座未設定もしくは取得失敗';
                $gmoBankId = '口座未設定もしくは取得失敗';
            }

            $resultArray[] = [
                $record->user_id,
                $viewMode,
                $accountTitle,
                $record->withdrawal,
                $record->created,
                "$bankId",
                $gmoBankId
            ];
        }

        return $resultArray;
    }

    private function generateGmoBankId(int $bankId): string
    {
        $gmoBankIdHead = '001-';
        $bankIdLength = strlen($bankId);
        if ($bankIdLength >= 8) {
            return $gmoBankIdHead;
        }

        $targetCount = 8 - $bankIdLength;
        $executeCount = 1;
        // 桁数の数に応じて、0を付与する
        while (true) {
            $gmoBankIdHead .= '0';
            if ($executeCount === $targetCount) {
                break;
            }

            $executeCount++;
        }

        return $gmoBankIdHead;
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
        $options = [
            'mime' => 'text/csv'
        ];
        Mail::send(new MonthlyWithdrawalReport(
            $resultCode,
            $csv,
            $fileName,
            $options
        ));
    }
}
