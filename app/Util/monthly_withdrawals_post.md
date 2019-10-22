## POST admin/:id/balance_sheets

貸借対照表(B/S)の生成を指示します。

生成されたCSVファイルはメールで送信されます。

## Parameters

key|必須/任意|説明|例
---|---|---|---
start_date|必須|開始日|2019-02-01 11:22:33(時刻未指定の場合は00:00:00となる)|
end_date|必須|終了日|2019-02-15 11:22:33(時刻未指定の場合は00:00:00となる)|

## Example Result

```
{
  "links": {
    "self": "http://internal.altair.local/api/v1/admin/12332/smbc_account/1"
  },
  "data": {
    "message": "success."
  },
  "auth": {
  }
}
```

場合 |ステータス
---  |---
正常に生成が開始されたとき | 202
バリデーションエラーになった場合 | 422
