# ディレクトリ構成

```
app/Domain/ScoreReputation/
├── ClientReputationCount.php        # クライアントの行動回数を取得する処理を集約（未実装）
├── ReputationCount.php              # 外部からの呼び出しを受け付ける・行動回数の保存処理
├── ReputationCountInterface.php     # インタフェース
├── ReputationCountTrait.php         # 行動回数を取得する際に共通で利用されるメソッドを部品化
└── WorkerReputationCount.php        # ワーカーの行動回数を取得する処理を集約
```

## 実装時、対象の行動を追加・修正する場合

- ワーカーの行動回数取得処理は、```WorkerReputationCount.php```に追加してください。
- クライアントの行動回数取得処理は、```ClientReputationCount.php```に追加してください。
- 行動追加後は```TARGET_REPUTATION_METHODS```にて、行動回数とメソッド名との紐付けを行うことで、外部からの呼び出しが可能となります。

## 対象の行動回数取得処理を呼び出す場合

- ```ReputationCount.php```をインスタンス化し、左記に定義された呼び出しメソッド越しに、行動回数取得処理を呼び出します。
- 全ての行動回数を取得する際は、```getAll...```以下のメソッドを呼び出します。
- 特定の行動回数を取得する際は、```getTarget...```以下のメソッドを呼び出します。取得対象の行動は、```$targetReputations```にて、行動IDを格納した配列として引き渡します。
- ワーカー・クライアントごとに上記メソッドが定義されています。

## 条件指定して行動回数を呼び出す場合

- 条件指定する場合は、引数```$conditions```に下記のように条件を引き渡します。
- 条件を何も指定しない場合は、```$conditions```を空の配列として、メソッドに引き渡します。

```
$conditions = [
    'startTime' => Carbon / 集計開始時,
    'finishTime' => Carbon / 集計終了時,
    'userIds' => array / 指定したいユーザーIDの配列,
    'limit' => int / 取得レコードの上限数（レコードを分割して取得したい場合に指定する),
    'offset' => int / 取得レコードの取得開始位置（レコードを分割して取得したい場合に指定する),
]
```

## 共通で用いるメソッドの切り出し先

- ワーカー・クライアントの両方で利用するメソッド群は、```ReputationCountTrait.php```に切り出しています。
