## GET client/:id/thread_tracks

未読の一覧を返します。

## Parameters

key|必須/任意|説明|例
---|---|---|---
job_id|任意|仕事ID|1001
exclude_sys_msg|任意|取引アクションの際,システムで自動挿入されるメッセージを除外する|true/false(デフォルト: false)
need_state|任意|取引アクションの際、取引ステータスに関する情報を返すかどうか|true/false(デフォルト: false)
limit|任意|表示件数|10(デフォルト: 5, 最大値: 100)
page|任意|ページ番号|2(デフォルト: 1)|

* job_idの指定は「個別連絡ボード＞未読一覧」での利用を想定しています。
* job_idの指定がない場合、:idのクライアントが属する全ての個別連絡ボードから、未読の一覧を返します。
* 「システムで自動挿入されるメッセージ」の例: 「納品しました」「納品を受け取りました」等

## Result

key|説明|備考
---|---|---
id|thread_tracks.id|
job_id|仕事ID|
job_name|仕事名|
message|連絡内容|
worker_id|ワーカーのユーザーID|
worker_name|ワーカー名|
thumbnail_url|ワーカーのサムネイル|
modified|更新日時||


## Example Result

```
{
  "links": {
    "self": "http://internal.altair.local/api/v1/client/359238/thread_tracks",
    "first": "http://internal.altair.local/api/v1/client/359238/thread_tracks?page=1",
    "prev": null,
    "next": "http://internal.altair.local/api/v1/client/359238/thread_tracks?page=2",
    "last": "http://internal.altair.local/api/v1/client/359238/thread_tracks?page=7"
  },
  "data": {
    {
      "type": "thread_tracks",
      "id": 1,
      "attributes": {
        "job_id": 1001,
        "job_name": "仕事名１",
        "message": "連絡内容連絡内容連絡内容",
        "worker_id": 3000,
        "worker_name": "aqua",
        "thumbnail_url": "https://s3-ap-northeast-1.amazonaws.com/s3-test.shufti.jp/User/7f/0a/93",
        "modified": "2017/01/03",
        "state_group_id": null,
        "state": null
      },
    },
    {
      "type": "thread_tracks",
      "id": 2,
      "attributes": {
        "job_id": 1002,
        "job_name": "仕事名２",
        "message": "連絡内容連絡内容連絡内容",
        "worker_id": 3000,
        "worker_name": "aqua",
        "thumbnail_url": "https://s3-ap-northeast-1.amazonaws.com/s3-test.shufti.jp/User/7f/0a/93",
        "modified": "2017/01/02",
        "state_group_id": null,
        "state": null
      },
    },
    {
      "type": "thread_tracks",
      "id": 3,
      "attributes": {
        "job_id": 1003,
        "job_name": "仕事名３,
        "message": "連絡内容連絡内容連絡内容",
        "worker_id": 3000,
        "worker_name": "aqua",
        "thumbnail_url": "https://s3-ap-northeast-1.amazonaws.com/s3-test.shufti.jp/User/7f/0a/93",
        "modified": "2017/01/01",
        "state_group_id": null,
        "state": null
      },
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
