## GET admin/:id/jobs/:job_id/trade_states/:worker_id

指定されたワーカーとの現在のプロジェクト取引状態を返します。

## Parameters

なし

## Result

key|説明
---|---
id|jobs.id
client_id|クライアントのユーザーID
client_name|クライアントのユーザー名
worker_id|ワーカーのユーザーID
worker_name|ワーカーのユーザー名
wall_id|個別連絡ボードのウォールID
is_deferrable|支払い予定額に後払い手数料を含むフラグ
state_group_id|現在のステータスグループid
state_group_txt|現在のステータスグループ
state_id|詳細ステータスid(trades.state)
state_txt|詳細ステータス
expire_date|返答期限日(納品承認、応募承認等の表示で利用を想定)
current_proposed_price|現在の予定単価
current_quantity|現在の納品予定数量
current_payment_price|現在の取引に対して支払予定額(後払いの場合、後払い手数料を含む)|

### state_group 一覧

id|グループ
---|---
1|選考中
6|作業中
3|納品中
4|評価中
5|取引終了(正常)
2|継続発注待ち
8|単価変更依頼中
9|数量変更依頼中
10|取引中止依頼中
11|取引途中終了・中止

## Example Result

```
{
  "links": {
    "self": "http://internal.altair.local/api/v1/admin/12332/jobs/1001/trade_states/1002"
  },
  "data": {
    "type": "trade_states",
    "id": 1001,
    "attributes": {
      "client_id": 11109,
      "client_name": "fire",
      "worker_id": 11108,
      "worker_name": "aqua",
      "wall_id": 2001,
      "is_deferrable": false,
      "state_group_id": 6,
      "state_group_txt": "作業中",
      "state_id": 4,
      "state_txt": "作業開始できます",
      "expire_date": null,
      "current_proposed_price": 100,
      "current_quantity": 3,
      "current_payment_price": 300
    }
  },
  "auth": {
  }
}
```
