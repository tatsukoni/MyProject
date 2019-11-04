<?php

namespace App\Jobs\Admin;

use App\Jobs\BaseJob;
use App\Mail\Mails\Admin\MonthlyWithdrawalReport;
use App\Models\PointDetail;
use App\Models\PointLog;
use App\Services\PaymentService\PaymentClient;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Log;
use Mail;
use PaymentService\Client;

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
    const INVALID_TARGET_DATE = 1;
    const NOT_EXIST_MONTHLY_WITHDRAWAL = 2;
    const FAIL_GET_BANK_ID = 3;
    const SUCCESS_MONTHLY_WITHDRAWAL = 4;

    const TIMEOUT = 30;

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

        $options = [
            'timeout' => self::TIMEOUT,
            'connect_timeout' => self::TIMEOUT
        ];
        $token = config('shufti.payment_service.api_token');
        $endpoint = config('shufti.payment_service.api_endpoint');
        Client::config($token, $endpoint, $options);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 月跨ぎの出金に該当する口座情報の一時テーブルを取得
        $conditions = [
            'paid_at' => [">={$this->startDate}", "<{$this->endDate}"],
            'status' => PaymentClient::WITHDRAWAL_STATUS_SUCCESS,
            'include' => 'bank',
        ];
        $tableName = $this->createBankTemporaryTable($conditions);
        if (! $tableName) {
            $this->sendMail(self::FAIL_GET_BANK_ID);
            return;
        }

        // 月跨ぎの出金に該当する情報を取得し、一時テーブルとの紐付けを行う
        $records = $this->getMonthlyWithdrawal($tableName);
        if ($records->count() === 0) {
            $this->sendMail(self::NOT_EXIST_MONTHLY_WITHDRAWAL);
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
     * 指定された日付で、月跨ぎの出金に該当する口座情報の一時テーブルを取得する
     *
     * @param Array $conditions
     */
    public function createBankTemporaryTable(Array $conditions)
    {
        // 一時テーブルの作成
        $tableName = 'monthly_withdrows_payment' . uniqid();
        if (! $this->createTemporaryTable($tableName)) {
            Log::error(__METHOD__ . ': 一時テーブルの作成に失敗しました。テーブル名: ' . $tableName);
            return false;
        }

        // pageごとに、出金された口座情報を取得
        try {
            $page = 1;
            while (true) {
                $conditions['page'] = $page;
                $withdrawals = $this->getWithdrawals($conditions);
                if (empty($withdrawals)) {
                    break;
                }

                $convertWithdrawals = $this->convertWithdrawals($withdrawals, $tableName);
                if (! $convertWithdrawals) {
                    Log::error(__METHOD__ . ': 出金情報の取得に失敗しました。テーブル名: ' . $tableName);
                    $tableName = false;
                    break;
                }

                $page++;
            }
        } catch (Exception $e) {
            Log::error($e);
            $tableName = false;
        }

        return $tableName;
    }

    private function createTemporaryTable(string $tableName)
    {
        $ddl = <<<__DDL__
CREATE TEMPORARY TABLE IF NOT EXISTS $tableName (
    id integer not null primary key,
    bank_id integer,
    user_id integer
)
__DDL__;

        return DB::insert(DB::raw($ddl));
    }

    private function getWithdrawals(Array $conditions)
    {
        try {
            $withdrawals = \PaymentService\Withdrawal::all($conditions);
        } catch (Exception $e) {
            Log::error($e);
            return false;
        }
        return $withdrawals->toArray();
    }

    private function convertWithdrawals(Array $withdrawals, string $tableName)
    {
        foreach ($withdrawals as $withdrawal) {
            $id = $withdrawal['id'];
            $bankId = $withdrawal['bank_id'];
            $userId = optional($withdrawal['bank'])->user_id;

            $insert = DB::table($tableName)->insert([
                'id' => $id,
                'bank_id' => $bankId,
                'user_id' => $userId
            ]);

            if (! $insert) {
                return false;
            }
        }
        return true;
    }

    /**
     * 指定された日付で、月跨ぎの出金に該当する情報を取得する
     *
     * @return Collection
     */
    public function getMonthlyWithdrawal(string $tableName): Collection
    {
        $records = DB::table($tableName)
            ->select('users.id as user_id')
            ->AddSelect('users.view_mode')
            ->AddSelect('point_details.account_title_id')
            ->AddSelect('point_details.withdrawal')
            ->AddSelect(DB::raw("convert_tz(point_logs.created, '+0:00', '+09:00') AS created"))
            ->AddSelect(DB::raw("$tableName" . '.bank_id'))
            ->join('users', 'users.id', '=', "$tableName" . '.user_id')
            ->join('point_details', function ($join) {
                $join->on('point_details.user_id', '=', 'users.id')
                     ->where('account_id', PointDetail::ESCROW_ACCOUNT);
            })
            ->join('point_logs', function ($join) {
                $join->on('point_logs.id', '=', 'point_details.point_log_id')
                     ->where('detail', PointLog::PERMIT_POINTS_CONVERSION);
            })
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

        return $records->get();
    }

    /**
     * ユーザーIDに紐付くbank_idを取得し、CSV出力のための配列を作成する
     *
     * @param Collection $records
     * @return array
     */
    public function generateResultsArray(Collection $records): array
    {
        $resultArray = [];

        foreach ($records as $record) {
            // view_modeの設定
            $viewMode = ($record->view_mode === 'contract') ? 'ワーカー' : 'クライアント';
            // 出金種別の設定
            $accountTitle = ($record->account_title_id == PointDetail::PAYMENT) ? '出金額' : '換金手数料';
            // 口座idの取得
            if (! is_null($record->bank_id)) {
                $bankId = $record->bank_id;
                $gmoBankId = '001-000' . $bankId;
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
        $fileName = Carbon::parse($this->startDate)->format('Ym') . 'monthly_withdrawals_result.csv';
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
