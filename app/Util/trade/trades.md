## GET admin/:id/jobs/:job_id/trades

:job_idに関する取引情報を返します。

* ソートは current_trades.created desc

## Parameters

key|必須/任意|説明|例
---|---|---|---
state_group_id|任意|取引ステータスグループ|下記「state_group_id 一覧」参照（デフォルト：すべて）
limit|任意|表示件数|10(デフォルト:20, 最大値: 100)
page|任意|ページ番号|2(デフォルト:1)

### state_group_id 一覧

id|グループ
---|---
1|選考中
2|継続発注待ち
3|作業中
4|単価変更依頼中
5|数量変更依頼中
6|取引中止依頼中
7|納品中
8|評価中
9|取引終了(正常)
10|取引途中終了・中止

## Result

key|説明|備考
---|---|---
id|trades.id|
job_id|仕事ID|
worker_id|ワーカーのユーザーID|
worker_name|ワーカーのユーザー名|
state_group_id|取引ステータスID|AdminTradeStateComponent::STATE_GROUPS
state_group_text|取引ステータス|
wall_id|walls.id|個別連絡ボードのウォールID
current_proposed_price|現在の予定単価
current_quantity|現在の納品予定数量
current_payment_price|現在の取引に対して支払予定額(後払いの場合、後払い手数料を含む)
unread_count|未読数|ワーカーからのメッセージでクライアントが未読のメッセージ
pinned_count|ピンしたメッセージ数|クライアントがピンしたメッセージ


## Example Result

```
{
  "links": {
    "self": "http://internal.altair.local/api/v1/admin/12332/jobs/56789/trades?state_group_id=6",
    "first": "http://internal.altair.local/api/v1/admin/12332/jobs/56789/trades?state_group_id=6&page=1",
    "prev": null,
    "next": "http://internal.altair.local/api/v1/admin/12332/jobs/56789/trades?state_group_id=6&page=2",
    "last": "http://internal.altair.local/api/v1/admin/12332/jobs/56789/trades?state_group_id=6&page=7"
  },
  "data": {
    {
      "type": "trades",
      "id": 10000,
      "attributes": {
        "job_id": 1001,
        "worker_id": 3001,
        "worker_name": "aqua",
        "state_group_id": 3,
        "state_group_text": "作業中",
        "wall_id": 2001,
        "current_proposed_price": 10,
        "current_quantity": 0,
        "current_payment_price": 0,
        "unread_count": 3,
        "pinned_count": null,
      }
    },
    {
      "type": "trades",
      "id": 10000,
      "attributes": {
        "job_id": 1001,
        "worker_id": 3002,
        "worker_name": "aqua2",
        "state_group_id": 3,
        "state_group_text": "作業中",
        "wall_id": 2002,
        "current_proposed_price": 10,
        "current_quantity": 1,
        "current_payment_price": 10,
        "unread_count": 3,
        "pinned_count": null,
      }
    },
    {
      "type": "trades",
      "id": 10000,
      "attributes": {
        "job_id": 1001,
        "worker_id": 3003,
        "worker_name": "aqua3",
        "state_group_id": 3,
        "state_group_text": "作業中",
        "wall_id": 2003,
        "current_proposed_price": 10,
        "current_quantity": 2,
        "current_payment_price": 20,
        "unread_count": 3,
        "pinned_count": null,
      }
    },
  },
  "auth": {
  },
  "meta": {
    "total": 102,
    "per_page": 15,
    "current_page": 1,
    "last_page": 7
  }
}
```
