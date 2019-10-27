<?php

namespace App\Http\Controllers\Components\Admin;

use App\Http\Controllers\Components\TradeState;

class AdminTradeState
{
    // 状態ステータス
    const ADMIN_GROUP_PROPOSAL = 1; // 選考中
    const ADMIN_GROUP_REPROPOSAL = 2; // 継続発注待ち
    const ADMIN_WORK = 3; // 作業中
    const ADMIN_GROUP_NEGOTIATION = 4; // 単価変更依頼中
    const ADMIN_GROUP_QUANTITY = 5; // 数量変更依頼中
    const ADMIN_GROUP_CANCEL = 6; // 取引中止依頼中
    const ADMIN_GROUP_DELIVERY = 7; // 納品
    const ADMIN_GROUP_FINISH = 8; // 評価
    const ADMIN_GROUP_CLOSED = 9; // 取引終了(正常)
    const ADMIN_GROUP_ABNORMALCLOSED = 10; // 取引途中終了・中止

    const STATE_GROUPS_FOR_ADMIN = [
        self::ADMIN_GROUP_PROPOSAL => [ // 選考中
            TradeState::STATE_PROPOSAL
        ],
        self::ADMIN_GROUP_REPROPOSAL => [ // 継続発注待ち
            TradeState::STATE_RE_PROPOSAL
        ],
        self::ADMIN_WORK => [ // 作業中
            TradeState::STATE_WORK,
            TradeState::STATE_OVER,
            TradeState::STATE_REORDER
        ],
        self::ADMIN_GROUP_NEGOTIATION => [ // 単価変更依頼中
            TradeState::STATE_NEGOTIATION_BY_OUTSOURCER,
            TradeState::STATE_NEGOTIATION_BY_CONTRACTOR,
        ],
        self::ADMIN_GROUP_QUANTITY => [ // 数量変更依頼中
            TradeState::STATE_QUANTITY_BY_OUTSOURCER,
        ],
        self::ADMIN_GROUP_CANCEL => [ // 取引中止依頼中
            TradeState::STATE_CANCEL_BY_OUTSOURCER,
            TradeState::STATE_CANCEL_BY_CONTRACTOR,
            TradeState::STATE_RE_PROPOSAL_CANCEL,
            TradeState::STATE_REORDER_CANCEL
        ],
        self::ADMIN_GROUP_DELIVERY => [ // 納品
            TradeState::STATE_DELIVERY,
            TradeState::STATE_PENDING_DELIVERY
        ],
        self::ADMIN_GROUP_FINISH => [ // 評価
            TradeState::STATE_FINISH,
            TradeState::STATE_FINISH_BY_CONTRACTOR,
            TradeState::STATE_FINISH_FOR_RECEIVING_AGENT,
        ],
        self::ADMIN_GROUP_CLOSED => [ // 取引終了(正常)
            TradeState::STATE_CLOSED,
        ],
        self::ADMIN_GROUP_ABNORMALCLOSED => [ // 取引途中終了・中止
            TradeState::STATE_FINISH_REJECTED,
            TradeState::STATE_TERMINATED,
        ]
    ];

    public static function getGroupStateByAdminTradeState(int $state): array
    {
        $groupState = [];
        switch ($state) {
            case in_array($state, self::STATE_GROUPS_FOR_ADMIN[self::ADMIN_GROUP_PROPOSAL]):
                $groupState['state_group_id'] = self::ADMIN_GROUP_PROPOSAL;
                $groupState['state_group_text'] = '選考中';
                break;
            case in_array($state, self::STATE_GROUPS_FOR_ADMIN[self::ADMIN_GROUP_ABNORMALCLOSED]):
                $groupState['state_group_id'] = self::ADMIN_GROUP_ABNORMALCLOSED;
                $groupState['state_group_text'] = '取引中止';
                break;
            case in_array($state, self::STATE_GROUPS_FOR_ADMIN[self::ADMIN_WORK]):
                $groupState['state_group_id'] = self::ADMIN_WORK;
                $groupState['state_group_text'] = '作業中';
                break;
            case in_array($state, self::STATE_GROUPS_FOR_ADMIN[self::ADMIN_GROUP_DELIVERY]):
                $groupState['state_group_id'] = self::ADMIN_GROUP_DELIVERY;
                $groupState['state_group_text'] = '納品中';
                break;
            case in_array($state, self::STATE_GROUPS_FOR_ADMIN[self::ADMIN_GROUP_FINISH]):
                $groupState['state_group_id'] = self::ADMIN_GROUP_FINISH;
                $groupState['state_group_text'] = '評価中';
                break;
            case in_array($state, self::STATE_GROUPS_FOR_ADMIN[self::ADMIN_GROUP_CLOSED]):
                $groupState['state_group_id'] = self::ADMIN_GROUP_CLOSED;
                $groupState['state_group_text'] = '取引終了';
                break;
            case in_array($state, self::STATE_GROUPS_FOR_ADMIN[self::ADMIN_GROUP_REPROPOSAL]):
                $groupState['state_group_id'] = self::ADMIN_GROUP_REPROPOSAL;
                $groupState['state_group_text'] = '継続発注待ち';
                break;
            case in_array($state, self::STATE_GROUPS_FOR_ADMIN[self::ADMIN_GROUP_NEGOTIATION]):
                $groupState['state_group_id'] = self::ADMIN_GROUP_NEGOTIATION;
                $groupState['state_group_text'] = '単価変更依頼中';
                break;
            case in_array($state, self::STATE_GROUPS_FOR_ADMIN[self::ADMIN_GROUP_QUANTITY]):
                $groupState['state_group_id'] = self::ADMIN_GROUP_QUANTITY;
                $groupState['state_group_text'] = '数量変更依頼中';
                break;
            case in_array($state, self::STATE_GROUPS_FOR_ADMIN[self::ADMIN_GROUP_CANCEL]):
                $groupState['state_group_id'] = self::ADMIN_GROUP_CANCEL;
                $groupState['state_group_text'] = '取引中止依頼中';
                break;
            default:
                $groupState['state_group_id'] = 99;
                $groupState['state_group_text'] = '未設定';
                break;
        }
        return $groupState;
    }
}
