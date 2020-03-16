<?php

namespace App\Services\PaymentService\PaymentClient;

use App\Services\PaymentService\PaymentClient;
use DB;
use Log;

class RemoteCreditCards
{
    /**
     * pull
     *
     * @param string $tablePrefix = 'credit_cards'
     * @param array $conditions = ['with_trashed' => true]
     * @return string|bool
     */
    public function pull($tablePrefix, $conditions)
    {
        // 一時テーブルを作成する
        $tableName = $tablePrefix . '_' . uniqid();
        if (! $this->createTemporaryTable($tableName)) {
            Log::error(__METHOD__ . ': ' . "Can't create table '$tableName'.");
            return false;
        }

        try {
            $page = 1;
            while (true) {
                $conditions['page'] = $page;

                $cards = $this->getCreditCards($conditions);
                if ($cards === false || is_null($cards)) {
                    throw new Exception("Can't pull credits. conditions = " . var_export($conditions, true));
                }
                // 正常だが空のコレクションが返却された場合に、処理を中断する
                if (empty($cards)) {
                    break;
                }

                // 一時テーブルへの保存処置を行う
                foreach ($cards as $card) {
                    $inserted = DB::insert($tableName)->insert([
                        'id' => $card['id'],
                        'brand' => $card['brand']
                    ]);
                    if (! $inserted) {
                        throw new Exception("Can't insert to " . $tableName);
                    }
                }

                $page++; // 処理が成功すれば、次のページ（保存）に進む
            }
        } catch (\Exception $e) {
            Log::error($ex);
            return false;
        }

        return $tableName; // return string
    }

    /**
     * 一時テーブルを作成する
     */
    private function createTemporaryTable()
    {
        $ddl = <<<__DDL__
CREATE TEMPORARY TABLE IF NOT EXISTS $tableName (
    id integer not null primary key,
    brand varchar(255)
)
__DDL__;

        return DB::insert(DB::raw($ddl));
    }

    /**
     * payment service より、credit_cards を取得する
     *
     * @param array $conditions
     */
    private function getCreditCards($conditions = [])
    {
        try {
            $creditCards = \PaymentService\CreditCard::all($conditions);
        } catch (Exception $e) {
            Log::error($e);
            return false;
        }
        return $withdrawals->toArray();
    }
}
