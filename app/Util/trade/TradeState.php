<?php

namespace App\Http\Controllers\Components;

use App\Models\JobRole;

class TradeState
{
    // 取引進行を自動で進めるまでの日にち
    const TRADE_AUTO_PROCEED_DAY = 14;
    // 取引進行のアラートメールを送るまでの日にち
    const TRADE_AUTO_ALERT_DAY   = 7;

    // 状態
    const STATE_PROPOSAL        = 2;  // 応募 // proposal
    // const STATE_ORDER           = 3;  // 発注 // order (order worker to work for a job)
    const STATE_WORK            = 4;  // 作業 // being working
    const STATE_DELIVERY        = 5;  // 納品 // goods delivery
    const STATE_FINISH          = 6;  // クライアントからの評価 // rating from client
    const STATE_FINISH_REJECTED = 7;  // 完了が拒否された // request finish is rejected
    const STATE_CLOSED          = 9;  // 取引完結（正常終了）// trade completion (successful)
    const STATE_TERMINATED      = 10; // 取引の終了（受注を断った場合など）// trade finish (ex: refused order)
    const STATE_PENDING_DELIVERY            = 11; // 納品保留(ポイント・後払い利用上限不足) // Pending delivery (point・deferring use upper limit shortage)
    const STATE_OVER                        = 12; // 取引の終了（一括納品: 1度の納品が終了した）// trade finish (1 time of delivery had completed)
    const STATE_CANCEL_BY_OUTSOURCER        = 21; // 発注者からの取引中止要請 // request cancel trade from outsourcer
    const STATE_CANCEL_BY_CONTRACTOR        = 22; // 受注者からの取引中止要請 // request cancel trade from contractor
    const STATE_NEGOTIATION_BY_OUTSOURCER   = 31; // 発注者からの単価変更交渉 // change price negotiation from outsourcer
    const STATE_NEGOTIATION_BY_CONTRACTOR   = 32; // 受注者からの単価変更交渉 // change price negotiation from contractor
    const STATE_FINISH_BY_CONTRACTOR        = 33; // ワーカーからの評価 // rating from worker
    const STATE_TASK_REGISTERED             = 40; // タスク登録エスクロー // task registration escrow
    const STATE_TASK_QUANTITY_INCREASE      = 41; // タスク数量増加分エスクロー // task quantity increase escrow
    const STATE_CLOSED_BY_WORKER            = 50; // ワーカーによって終了処理された仕事 // task closed by contractor.
    const STATE_QUANTITY_BY_OUTSOURCER      = 51; // 発注者からの納品数変更交渉 // change quantity negotiation from outsourcer
    const STATE_FINISH_FOR_RECEIVING_AGENT  = 52; // 収納代行の仕事の納品後 // rating for receiving agent
    const STATE_RE_PROPOSAL                 = 62; // 再発注の検討中
    const STATE_REORDER                     = 63; // 再発注の作業中
    const STATE_RE_PROPOSAL_CANCEL          = 64; // 再発注可能状態から取引中止依頼
    const STATE_REORDER_CANCEL              = 65; // 再発注の検討状態から取引中止依頼
    const STATE_DO_NOT_UPDATE             = 9999; // Trade を更新しない"状態" // Do not update Trade's state

    // アクション
    // このアクション名から JobsController 内で呼ばれるメソッドが決まる．
    // ex) ACTION_ACCEPT_PROPOSAL => _acceptProposal()
    // action
    // From action name, determine action will be called in JobsController
    // ex) ACTION_ACCEPT_PROPOSAL => call _acceptProposal() action
    const ACTION_PROPOSE                   = 101;  // 応募 // submitted a proposal
    const ACTION_PROPOSE_REVISED           = 102;  // 応募内容見直し // revise a proposal
    const ACTION_ACCEPT_PROPOSAL           = 103;  // 応募OK，発注 // proposal OK, order
    const ACTION_REJECT_PROPOSAL           = 104;  // 応募お断り // reject a proposal
    const ACTION_CANCEL_PROPOSAL           = 105;  // 応募キャンセル // cancel proposal
    const ACTION_HOLD_PROPOSAL             = 106;  // 見積り保留 // hold proposal
    const ACTION_AUTO_TERMINATED           = 107;  // 応募の時間切れによる自動終了 // automatic termination due to expiraion of proposal
    // 取引中止の時間切れによる自動終了 // automatic termination due to expiraion of cancel trade
    // const ACTION_ACCEPT_ORDER     = 111;  // 受注 // order
    // const ACTION_REJECT_ORDER     = 112;  // 発注お断り // reject order
    const ACTION_DELIVER                   = 121;  // 納品 // good delivery
    const ACTION_ACCEPT_DELIVERY           = 122;  // 検収OK // accept
    const ACTION_REJECT_DELIVERY           = 123;  // 検収NG // reject
    const ACTION_CANCEL_DELIVERY           = 124;  // 納品キャンセル // cancel delivery
    const ACTION_SELECT_PAYMENT            = 125;  // お支払い方法選択 // payment method selection
    const ACTION_ACCEPT_DELIVERY_REORDER   = 126;  // 繰り返し発注希望 検収OK // accept
    const ACTION_ACCEPT_RE_PROPOSAL        = 127;  // 再発注画面に移動する
    const ACTION_REJECT_RE_PROPOSAL        = 128;  // 再発注しないで終了する
    const ACTION_REORDER                   = 129;  // 再発注する
    const ACTION_REORDER_STOP              = 130;  // 再発注しないで終了する
    const ACTION_FINISH                    = 131;  // 終了 // finish
    const ACTION_ACCEPT_FINISH             = 132;  // 終了OK // finish OK
    const ACTION_REJECT_FINISH             = 133;  // 終了NG // finish NG
    const ACTION_CANCEL_FINISH             = 134;  // 終了＆評価キャンセル // finish and cancel rating
    const ACTION_AUTO_FINISH               = 135;  // 評価中の時間切れによる自動終了 // automatic termination due to expiration during rating
    const ACTION_AUTO_FINISH_NO_PUNISHMENT = 136;  // 時間切れによる自動終了（評価の減点なし） // automatic termination due to expiration ()
    const ACTION_AUTO_FINISH_BY_PENALTY    = 137;  // automatic termination due to penalty user
    const ACTION_CANCEL                    = 141;  // 取引中止 // cancel trade
    const ACTION_ACCEPT_CANCEL             = 142;  // 取引中止OK // cancel trade OK
    const ACTION_REJECT_CANCEL             = 143;  // 取引中止NG // cancel trade NG
    const ACTION_CANCEL_CANCEL             = 144;  // 取引中止キャンセル // cancel "cancel trade"
    const ACTION_NEGOTIATE                 = 151;  // 単価変更 // change price negotiation
    const ACTION_ACCEPT_NEGOTIATION        = 152;  // 単価変更OK // change price negotiation OK
    const ACTION_REJECT_NEGOTIATION        = 153;  // 単価変更NG // change price negotiation NG
    const ACTION_CANCEL_NEGOTIATION        = 154;  // 単価変更キャンセル // cancel change price negotiation
    const ACTION_ACCEPT_DELIVER_DEMO       = 155;  // デモ案件 検収OK // demonstation project acceptance OK
    // tasks_controllerで $pendingStatusCode = 156 が使用されている為、156をスキップ
    // Because we using the $pendingStatusCode = 156 in tasks_controller, Skip 156.
    const ACTION_ACCEPT_PROPOSAL_DEMO      = 157; // デモ案件見積りOK
    const ACTION_PARTIAL_ACCEPT_DELIVERY   = 158;  // 部分的に検収OK
    const ACTION_QUANTITY                  = 159;  // 納品数量変更
    const ACTION_ACCEPT_QUANTITY           = 160;  // 納品数量変更OK
    const ACTION_REJECT_QUANTITY           = 161;  // 納品数量変更NG
    const ACTION_CANCEL_QUANTITY           = 162;  // 納品数量変更キャンセル
    const ACTION_RE_PROPOSAL_CANCEL        = 170; //再発注可能状態から取引中止
    const ACTION_ACCEPT_RE_PROPOSAL_CANCEL = 171; // 再発注可能状態から取引中止OK
    const ACTION_REJECT_RE_PROPOSAL_CANCEL = 172; // 再発注可能状態から取引中止NG
    const ACTION_CANCEL_RE_PROPOSAL_CANCEL = 173; // 再発注可能状態から取引中止キャンセル
    const ACTION_REORDER_CANCEL            = 174; // 再発注の検討状態から取引中止
    const ACTION_ACCEPT_REORDER_CANCEL     = 175; // 再発注の検討状態から取引中止OK
    const ACTION_REJECT_REORDER_CANCEL     = 176; // 再発注の検討状態から取引中止NG
    const ACTION_CANCEL_REORDER_CANCEL     = 177; // 再発注の検討状態から取引中止キャンセル
    // 収納代行前に登録された仕事の取引を強制終了する際に使用
    // Trade::save()を使用して直接更新するため、他のアクションと違い、forceFinishメソッド等は無い
    const ACTION_FORCE_FINISH              = 180; // 取引を強制終了
    const ACTION_ADMIN_FORCE_FINISH        = 190; // 事務局により強制取引中止
    const ACTION_ADMIN_FORCE_PAYMENT       = 191; // 事務局により強制支払い
    const ACTION_ADMIN_FORCE_CANCEL        = 192; // 事務局により強制取り消し

    // 状態グループ
    // group state
    const GROUP_PROPOSAL = 1;  // 選考 // selection
    const GROUP_WORK     = 6;  // 作業 // being working
    const GROUP_DELIVERY = 3;  // 納品 // goods delivery
    const GROUP_FINISH   = 4;  // 評価 // rating
    const GROUP_CLOSED   = 5;  // 終了 // finish

    // 管理画面に表示するために、新たに追加する状態グループ
    const GROUP_REPROPOSAL = 2; // 継続発注待ち
    const GROUP_NEGOTIATION = 8; // 単価変更依頼中
    const GROUP_QUANTITY = 9; // 数量変更依頼中
    const GROUP_CANCEL = 10; // 取引中止依頼中
    const GROUP_TERMINATED = 11; // 取引途中終了・中止

    // アクティブな取引をまとめて抽出する際に使用
    // GROUP_CLOSED以外のstateを対象とする為、STATE_GROUPSへ定義を設けていない
    const GROUP_ACTIVE   = 7;  // 終了以外（アクティブな取引）

    /**
     * 取引を5段階の状態に分類する
     * Classify transaction into 5 level of state
     */
    const STATE_GROUPS = [
        self::GROUP_PROPOSAL => [
            self::STATE_PROPOSAL,
            self::STATE_RE_PROPOSAL,
            self::STATE_REORDER,
            self::STATE_RE_PROPOSAL_CANCEL,
            self::STATE_REORDER_CANCEL
        ],
        self::GROUP_WORK => [
            self::STATE_WORK,
            self::STATE_OVER,
            self::STATE_FINISH_REJECTED,
            self::STATE_CANCEL_BY_OUTSOURCER,
            self::STATE_CANCEL_BY_CONTRACTOR,
            self::STATE_NEGOTIATION_BY_OUTSOURCER,
            self::STATE_NEGOTIATION_BY_CONTRACTOR,
            self::STATE_QUANTITY_BY_OUTSOURCER,
        ],
        self::GROUP_DELIVERY => [
            self::STATE_DELIVERY,
            self::STATE_PENDING_DELIVERY
        ],
        self::GROUP_FINISH => [
            self::STATE_FINISH,
            self::STATE_FINISH_BY_CONTRACTOR,
            self::STATE_FINISH_FOR_RECEIVING_AGENT,
        ],
        self::GROUP_CLOSED => [
            self::STATE_CLOSED,
            self::STATE_FINISH_REJECTED,
            self::STATE_TERMINATED
        ],
    ];

    /**
     * 管理画面表示用
     * 取引を10段階の状態に分類する
     */
    const STATE_GROUPS_FOR_ADMIN = [
        self::GROUP_PROPOSAL => [ // 選考中
            self::STATE_PROPOSAL
        ],
        self::GROUP_REPROPOSAL => [ // 継続発注待ち
            self::STATE_RE_PROPOSAL
        ],
        self::GROUP_WORK => [ // 作業中
            self::STATE_WORK,
            self::STATE_OVER,
            self::STATE_FINISH_REJECTED,
            self::STATE_REORDER
        ],
        self::GROUP_NEGOTIATION => [ // 単価変更依頼中
            self::STATE_NEGOTIATION_BY_OUTSOURCER,
            self::STATE_NEGOTIATION_BY_CONTRACTOR
        ],
        self::GROUP_QUANTITY => [ // 数量変更依頼中
            self::STATE_QUANTITY_BY_OUTSOURCER,
        ],
        self::GROUP_CANCEL => [ // 取引中止依頼中
            self::STATE_CANCEL_BY_OUTSOURCER,
            self::STATE_CANCEL_BY_CONTRACTOR,
            self::STATE_RE_PROPOSAL_CANCEL,
            self::STATE_REORDER_CANCEL
        ],
        self::GROUP_DELIVERY => [ // 納品
            self::STATE_DELIVERY,
            self::STATE_PENDING_DELIVERY
        ],
        self::GROUP_FINISH => [ // 評価
            self::STATE_FINISH,
            self::STATE_FINISH_BY_CONTRACTOR,
            self::STATE_FINISH_FOR_RECEIVING_AGENT
        ],
        self::GROUP_CLOSED => [ // 取引終了(正常)
            self::STATE_CLOSED,
        ],
        self::GROUP_TERMINATED => [ // 取引途中終了・中止
            self::STATE_FINISH_REJECTED,
            self::STATE_TERMINATED
        ]
    ];

    const STATE_GROUPS_TEXT = [
        self::GROUP_PROPOSAL => '選考中',
        self::GROUP_REPROPOSAL => '継続発注待ち',
        self::GROUP_WORK => '作業中',
        self::GROUP_NEGOTIATION => '単価変更依頼中',
        self::GROUP_QUANTITY => '数量変更依頼中',
        self::GROUP_CANCEL => '取引中止依頼中',
        self::GROUP_DELIVERY => '納品中',
        self::GROUP_FINISH => '評価中',
        self::GROUP_CLOSED => '取引終了',
        self::GROUP_TERMINATED => '取引中止'
    ];

    /**
     * 管理画面表示用
     * $stateに該当する状態グループを取得する
     * @param int $state
     * @return array
     */
    public static function getGroupState(int $state): array
    {
        $groupState = [];
        $stateGroups = self::STATE_GROUPS_FOR_ADMIN;
        foreach ($stateGroups as $groupStateId => $stateGroup) {
            if (in_array($state, $stateGroup)) {
                $groupState['state_group_id'] = $groupStateId;
                $groupState['state_group_text'] = self::STATE_GROUPS_TEXT[$groupStateId];
                break;
            }
        }

        // 該当する取引ステータスが存在しなかった場合
        if (empty($groupState)) {
            $groupState['state_group_id'] = null;
            $groupState['state_group_text'] = '未定義';
        }

        return $groupState;
    }
}
