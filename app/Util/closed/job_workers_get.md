## GET client/:id/jobs/:my_job_id/workers

:my_job_idに参加しているワーカー一覧を返す。

* 指定したstate_group_idに応じて戻り値の形式が変わる

## Parameters

key|必須/任意|説明|例
---|---|---|---
state_group_id|必須|取引ステータスグループ|※1|
limit|任意|表示件数|3(デフォルト:20, 最大値: 1000)
page|任意|ページ数|2(デフォルト:1)|

※1 [GET client/:id/jobs/:my_job_id/trade_groups](https://github.com/uluru/altair/blob/develop/doc/api/v1/internal/client/trade_groups_get.md) の「state_group_id 一覧」を参照。
ただし、このAPIでは「7 終了以外(アクティブな取引)」には対応しない。

## Result

「共通項目」+「指定したstate_group_idに対応する追加項目」が返却される。

### 共通項目

key|説明|備考・例
---|---|---
id|users.id
state_group_id|取引ステータスグループ
state|trades.state
selected|trades.selected
username|ワーカー名
thumbnail_url|サムネイル画像
unread_count|未読メッセージ数

### 追加項目(選考中 state_group_id = 1)

key|説明|備考・例
---|---|---
rating_all_avg|評価
related_project_count|プロジェクト実績
related_task_count|タスク実績
verified|本人確認
workable_time|作業可能時間|idではなく文字列で返却
message|応募メッセージ
proposed_datetime|応募日時
expire_date|返答期限

### Example Result(選考中 state_group_id = 1)

```
{
  "links": {
    "self": "http://internal.altair.local/api/v1/client/359238/jobs/1001/workers?state_group_id=1"
  },
  "data": {
    {
      "type": "job_workers",
      "id": 101,
      "attributes": {
        "state_group_id": 1,
        "state": 2,
        "selected": null,
        "username": "worker1",
        "thumbnail_url": "https://s3-ap-northeast-1.amazonaws.com/shufti/User/thumbnail/1c/1d/d4/b3263c21d7bb620bd4e7ae9c664554a592c9b42871cef526ae3467acd1",
        "unread_count": 0,
        "rating_all_avg": 1.5,
        "related_project_count": 1000,
        "related_task_count": 30,
        "verified": true,
        "workable_time": "30分未満",
        "message": "初心者ですがよろしくお願いします。",
        "proposed_datetime": '2019-11-14 17:02:03',
        "expire_date":  '2019-11-21'
      }
    },
    {
      "type": "job_workers",
      "id": 102,
      "attributes": {
        "state_group_id": 1,
        "state": 2,
        "selected": null,
        "username": "worker2",
        "thumbnail_url": "https://s3-ap-northeast-1.amazonaws.com/shufti/User/thumbnail/1c/1d/d4/b3263c21d7bb620bd4e7ae9c664554a592c9b42871cef526ae3467acd1",
        "unread_count": 2,
        "rating_all_avg": null,
        "related_project_count": 0,
        "related_task_count": 50,
        "verified": false,
        "workable_time": "５時間以上",
        "message": "気合入れます！",
        "proposed_datetime": '2019-11-14 17:02:03',
        "expire_date":  '2019-11-21'
      }
    },
    {
      "type": "job_workers",
      "id": 103,
      "attributes": {
        "state_group_id": 1,
        "state": 2,
        "selected": null,
        "username": "worker3",
        "thumbnail_url": "https://s3-ap-northeast-1.amazonaws.com/shufti/User/thumbnail/1c/1d/d4/b3263c21d7bb620bd4e7ae9c664554a592c9b42871cef526ae3467acd1",
        "unread_count": 1,
        "rating_all_avg": 1.5,
        "related_project_count": 100,
        "related_task_count": 200,
        "verified": true,
        "workable_time": "問わない",
        "message": "がんばります！",
        "proposed_datetime": '2019-11-14 17:02:03',
        "expire_date":  '2019-11-21'
      }
    },
  },
  "auth": {
  }
}
```

### 追加項目(発注中 state_group_id = 6)

key|説明|備考・例
---|---|---
unit_price|現在の予定単価|
quantity|現在の納品予定数量|
payment_price|現在の取引に対して支払予定額|後払いの場合、後払い手数料を含む
new_unit_price|変更後単価|単価変更交渉で利用
new_quantity|変更後納品数|納品変更交渉で利用
new_payment_price|変更後発注額

### Example Result(発注中 state_group_id = 6)

TODO


### 追加項目(納品中 state_group_id = 3)

key|説明|備考・例
---|---|---
s3_docs|納品ファイル|ファイル情報の配列
expire_date|返答期限日
delivered_datetime|納品日時
unit_price|単価
quantity|納品数
payment_price|お支払金額

### Example Result(納品中 state_group_id = 3)

TODO


### 追加項目(継続発注まち state_group_id = 2)

key|説明|備考・例
---|---|---
reorder|継続発注希望|1: ぜひ、受けたい, 2: 相談してほしい, 3: これで終わりにする
s3_docs|納品ファイル
unit_price|単価
quantity|納品数
payment_price|お支払金額
actual_worked_time_id|ワーカーから作業時間のフィードバック(ID)|1: 時間指定, 2: 1分以下, 3: 31日以上, 4: 答えられない
actual_minutes|ワーカーから作業時間のフィードバック(分)|30|
delivered_datetime|納品日時

### Example Result(継続発注まち state_group_id = 2)

TODO


### 追加項目(評価中 state_group_id = 4)

key|説明|備考・例
---|---|---
expire_date|返答期限日
unit_price|単価
quantity|納品数
payment_price|お支払金額
actual_worked_time_id|ワーカーから作業時間のフィードバック(ID)|1: 時間指定, 2: 1分以下, 3: 31日以上, 4: 答えられない
actual_minutes|ワーカーから作業時間のフィードバック(分)|30|
accepted_datetime|検収日時

### Example Result(評価中 state_group_id = 4)

TODO


### 追加項目(取引終了 state_group_id = 5)

key|説明|備考・例
---|---|---
rating_by_worker_point|ワーカーからの評価ポイント
rating_by_worker_msg|ワーカーからの評価メッセージ
unit_price|単価
quantity|納品数
payment_price|お支払金額
actual_worked_time_id|ワーカーから作業時間のフィードバック(ID)|1: 時間指定, 2: 1分以下, 3: 31日以上, 4: 答えられない
actual_minutes|ワーカーから作業時間のフィードバック(分)|30|
closed_datetime|取引終了日時
reject_reason_id|お断り理由|9: 必要な条件を満たしていないため, 10: 別のワーカーへの発注が決まったため, 11: その他の理由
reject_reason_txt|応募お断り理由(テキスト)
reject_reason_txt_detail|応募お断り理由(詳細)|11: その他の理由の場合のみ入る
partner_candidate|パートナー候補かどうか|bool
trade_closed_detail|取引終了理由詳細

### Example Result(取引終了 state_group_id = 5)

TODO
