<?php

namespace App\Console\Commands\Deposit;

use App\Console\BaseCommand;
use App\Domain\Point\Withdrawal;
use App\Models\CreditConversionQueues;
use App\Models\PointLog;
use App\Services\PaymentService\PaymentClient;
use App\Services\PaymentService\PaymentClient\RemoteWithdrawals;
use Carbon\Carbon;
use DB;
use Log;
use App\Services\PaymentService\PaymentClient\RemoteCreditDeposits;

class GmoCreditConversionMonthly extends BaseCommand
{
    /**
     * @var string
     */
    protected $signature = 'deposit:GmoCreditConversionMonthly
                            {run_mode : 実行モード e.g. => dry or run}
                            {target_month : 2020-03}';

    protected $description = '出金成功時に仕分けデータを作成する';
    protected $year;
    protected $targetMonth;
    protected $month;
    protected $paymentDate;

    private $succeedTransfers;

    /**
     * Create a new command instance.
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleCommand()
    {
//        if ($this->argument('run_mode') === 'dry') {
//            // 対象レコードは。。
//        }
//
//        if (!$this->argument( 'target_month')) {
//
//        }

//        list($this->year, $this->month) = explode('-', $this->argument('target_month'));
        $this->targetMonth = $this->argument('target_month');
        // 対象月にクレカ入金処理済みか確認する
        if ($this->checkDateOfCreditPurchase()) {
            throw new \Exception(sprintf('%s の処理は既に終わっています。', $this->argument('target_month')));
        };
        // CreditDepositから成功したレコードをし取得（status = 2 & create = 先月）
        $creditDeposit = $this->getCreditDepositTable();
        if ($creditDeposit) {
            throw new \Exception(sprintf('%s クレカ入金情報テーブルの作成に失敗しました', $this->argument('target_month')));
        }
        // 全てのクレカ情報を取得する
        $creditCards = $this->getCreditDepositTable(); // return string|bool
        if (! $creditCards) {
            throw new \Exception(sprintf('%s クレカ情報テーブルの作成に失敗しました', $this->argument('target_month')));
        }

        // 最新のクレカ仕分けを取得
        $queuedCreditConversion = CreditConversionQueues::where('status', CreditConversionQueues::STATUS_IN_PROGRESS)
            ->where('')->get();
        $targetDate = Carbon::parse($queuedCreditConversion->created)
            ->subMonth(1)->format('Y-m-d h:i');



        if ($queuedCreditConversion->target_date !== $targetDate) {
            // さよなら
        }

        // CreditDepositから成功したレコードをし取得（status = 2 & create = 先月）





        try {
            $this->getCreditPucrchase();
        } catch (\Exception $e) {
            Log::error($e);
        }
    }

    // TODO
    // 対象月の確認 done
    // 対象月に処理済みレ

    // Creditsからクレカを全件取得
    // PointDetails&PointLogs&CreditPurchaseと結合し取得
    // 対象カラムを取得
    // ↑CreditPurchaseの値を取得
    // CreditDepositとCreditCardの有無を確認
    // 入金確定書利用のデータ作成
    // 成功可否
    // 通知

    /**
     * @return bool
     */
    public function checkDateOfCreditPurchase(): bool
    {
        $this->paymentDate = Carbon::parse($this->targetMonth)->subMonth()->endOfMonth()->format('Y-m-d');

        return PointLog::where('detail', PointLog::PURCHASE_CREDIT_CONVERSION)
            ->where('payment_date', $this->paymentDate)->exists();
    }

    public function getCreditDepositTable()
    {
        $startDay = Carbon::parse($this->targetMonth)->subMonth()->format('Y-m-d h:i:s');
        $endDay = Carbon::parse($this->paymentDate . '23:59:59')->format('Y-m-d h:i:s');
        $tableName = (new RemoteCreditDeposits())->pull(
            'hoge',
            [
                'status' => \PaymentService\Deposit::STATUS_SUCCESS,
                'created' => [">={$startDay}", "<={$endDay}"]
            ]
        );

        DD($tableName);
        return false;
        //一次テーブルの作成
//        $tablePrefixByUser = $this->failedTransferPrefix . $userId;
//        $tableName = (new RemoteWithdrawals())->pull(
//            $tablePrefixByUser,¥
//                'user_id' => $userId,
//                'status' => PaymentClient::WITHDRAWAL_STATUS_FAILED
//            ]
//        );
//        if ($tableName === false) {
//            throw new \Exception('Could not retrieve temporary table.');
//        }
        // 一次テーブルを作成していないため、作成する必要がある
    }

    /**
     * return string|bool
     */
    public function getCreditCardsTable()
    {
        $tablePrefix = "credit_cards";
        $conditions = ['with_trashed' => true]; // オプション

        $tableName = (new RemoteCreditCards())->pull($tablePrefix, $conditions);
        return $tableName;
    }


    private function generateTransferSuccess()
    {
        if ($this->succeedTransfers->isEmpty()) {
            $this->info('出金完了仕分けが完了していないレコードはありません');
            return;
        }

        $this->info(
            sprintf(
                '処理件数 : %s',
                number_format($this->succeedTransfers->count(), 0)
            )
        );

        foreach ($this->succeedTransfers as $index => $transfer) {
            if ($this->isDryRun || $index == 0) {
                $this->info(
                    sprintf(
                        'ユーザーID : %d, 仕分け1 : account_title_id -> %d, 金額 -> %s, 仕分け2 : account_title_id -> %d, 金額 -> %s',
                        $transfer->pointLog->pointDetails[0]->user->id,
                        $transfer->pointLog->pointDetails[0]->account_title_id,
                        number_format($transfer->pointLog->pointDetails[0]->withdrawal, 0),
                        $transfer->pointLog->pointDetails[1]->account_title_id,
                        number_format($transfer->pointLog->pointDetails[1]->withdrawal, 0)
                    )
                );
            }
            if ($this->isDryRun) {
                continue;
            }

            try {
                DB::transaction(function () use ($transfer) {
                    (new Withdrawal())->generateSuccessTransfer($transfer);
                });
            } catch (\Exception $e) {
                // 仕分け失敗のためアラート
                Log::alert($e);
            }
        }
    }
}
