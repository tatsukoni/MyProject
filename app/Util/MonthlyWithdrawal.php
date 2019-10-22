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
    protected $startDate;
    protected $endDate;

    // 結果ごとに処理を分けるためのコード番号
    const INVALID_TARGET_DATE = 1;
    const NOT_EXIST_MONTHLY_WITHDRAWAL = 2;
    const FAIL_GET_BANK_ID = 3;
    const SUCCESS_MONTHLY_WITHDRAWAL = 4;

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
        // 日付の形式を「Y-m-d H:i:s」に統一する
        if (! $this->generateTargetDate()) {
            $this->sendMail(self::INVALID_TARGET_DATE);
            return;
        } else {
            $this->startDate = $this->generateTargetDate()['formatStartDate'];
            $this->endDate = $this->generateTargetDate()['formatEndDate'];
        }

        // 月跨ぎの出金に該当する情報を取得
        $records = $this->getMonthlyWithdrawal();
        if ($records->count() === 0) {
            $this->sendMail(self::NOT_EXIST_MONTHLY_WITHDRAWAL);
            return;
        }

        // CSV作成のための配列を作成する処理
        $resultArray = $this->generateResultsArray($records);
        if (! $resultArray) {
            $this->sendMail(self::FAIL_GET_BANK_ID);
            return;
        }

        // 結果をCSVファイルに出力し、メール送信
        $csv = $this->getCsv($resultArray);
        $this->sendMail(self::SUCCESS_MONTHLY_WITHDRAWAL, $csv);
    }

    /**
     * 入力された日付形式に合わせて「Y-m-d H:i:s」に整形する
     *
     */
    public function generateTargetDate()
    {
        $targetStartDate = Carbon::parse($this->startDate);
        $targetEndDate = Carbon::parse($this->endDate);
        if ($targetStartDate >= $targetEndDate) {
            return false;
        }

        if (Carbon::hasFormat($this->startDate, 'Y-m-d H:i:s')) {
            $formatStartDate = $targetStartDate->format('Y-m-d H:i:s');
        } elseif (Carbon::hasFormat($this->startDate, 'Y-m-d')) {
            $formatStartDate = $targetStartDate->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        } else {
            return false;
        }

        if (Carbon::hasFormat($this->endDate, 'Y-m-d H:i:s')) {
            $formatEndDate = $targetEndDate->format('Y-m-d H:i:s');
        } elseif (Carbon::hasFormat($this->endDate, 'Y-m-d')) {
            $formatEndDate = $targetEndDate->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        } else {
            return false;
        }

        return compact('formatStartDate', 'formatEndDate');
    }

    /**
     * 指定された日付で、月跨ぎの出金に該当する情報を取得する
     *
     * @return Collection
     */
    public function getMonthlyWithdrawal(): Collection
    {
        $query = DB::table('point_logs')
            ->join('point_details', 'point_details.point_log_id', '=', 'point_logs.id')
            ->join('users', 'users.id', '=', 'point_details.user_id')
            ->select(
                'users.id as user_id',
                'users.view_mode',
                'point_details.account_title_id',
                'point_details.withdrawal',
                DB::raw("convert_tz(point_logs.created, '+0:00', '+09:00') AS created")
            )
            ->where('point_logs.detail', PointLog::PERMIT_POINTS_CONVERSION)
            ->where('point_details.account_id', PointDetail::ESCROW_ACCOUNT)
            ->whereRaw(
                "convert_tz(point_logs.created, '+0:00', '+09:00') >= ?",
                $this->startDate
            )
            ->whereRaw(
                "convert_tz(point_logs.created, '+0:00', '+09:00') < ?",
                $this->endDate
            )
            ->orderBy('users.id', 'asc');

        return $query->get();
    }

    /**
     * ユーザーIDに紐付くbank_idを取得し、CSV出力のための配列を作成する
     *
     * @param Collection $result
     */
    public function generateResultsArray(Collection $results)
    {
        $paymentClient = resolve(PaymentClient::class);
        $checkResult = true;
        $resultArray = [];

        foreach($results as $result) {
            // view_modeの設定
            $viewMode = ($result->view_mode === 'contract') ? 'ワーカー' : 'クライアント';
            // 出金種別の設定
            $accountTitle = ($result->account_title_id == PointDetail::PAYMENT) ? '出金額' : '換金手数料';
            // 口座idの取得
            $bank = $paymentClient->getBankAccount($result->user_id);
            if (is_null($bank)) {
                $checkResult = false;
                break; //受け取り金融機関が設定されていない場合は、処理を終了させる
            }
            $bankId = $bank->id;
            // GMO上でのbank_id
            $GmoBankId = '001-000' . $bankId;

            $resultArray[] = [
                $result->user_id,
                $viewMode,
                $accountTitle,
                $result->withdrawal,
                $result->created,
                "$bankId",
                $GmoBankId
            ];
        }

        return $checkResult ? $resultArray : false;
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
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function sendMail(int $resultCode, string $csv = null): void
    {
        $fileName = Carbon::now('Asia/Tokyo')->format('Ym') . '_月跨ぎの出金_口座ID付.csv';
        $options = [
            'mime' => 'text/csv'
        ];
        Mail::queue(new MonthlyWithdrawalReport(
            $resultCode,
            $csv,
            $fileName,
            $options
        ));
    }
}
