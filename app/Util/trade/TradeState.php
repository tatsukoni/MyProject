<?php

// 変更区分
// 管理画面・仕事一覧の取引ステータス表示用
const STATE_GROUPS_FOR_ADMIN = [
    'groupProposal' => [ // 応募中
        self::STATE_PROPOSAL
    ],
    'groupReProposal' => [ // 再発注・継続依頼待ち
        self::STATE_RE_PROPOSAL
    ],
    'groupWork' => [ // 作業中
        self::STATE_WORK,
        self::STATE_OVER,
        self::STATE_REORDER
    ],
    'groupNegotiation' => [ // 単価変更依頼中
        self::STATE_NEGOTIATION_BY_OUTSOURCER,
        self::STATE_NEGOTIATION_BY_CONTRACTOR,
    ],
    'groupQuantity' => [ // 数量変更依頼中
        self::STATE_QUANTITY_BY_OUTSOURCER,
    ],
    'groupCancel' => [ // 取引中止依頼中
        self::STATE_CANCEL_BY_OUTSOURCER,
        self::STATE_CANCEL_BY_CONTRACTOR,
        self::STATE_RE_PROPOSAL_CANCEL,
        self::STATE_REORDER_CANCEL
    ],
    'groupDelivery' => [ // 納品
        self::STATE_DELIVERY,
        self::STATE_PENDING_DELIVERY
    ],
    'groupFinish' => [ // 評価
        self::STATE_FINISH,
        self::STATE_FINISH_BY_CONTRACTOR,
        self::STATE_FINISH_FOR_RECEIVING_AGENT,
    ],
    'groupNormalClose' => [ // 取引終了(正常)
        self::STATE_CLOSED,
    ],
    'groupAbnormalClose' => [ // 取引中止・見積もりお断りなどで終了した
        self::STATE_FINISH_REJECTED,
        self::STATE_TERMINATED,
    ]
];
