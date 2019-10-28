<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Http\Controllers\Components\TradeState;
use App\Models\Concerns\Common;

/**
 *  view(current_trades)に対応するモデルです。
 */
class CurrentTrade extends Model
{
    use Common;

    const CREATED_AT = 'created';
    const UPDATED_AT = 'modified';

    protected $casts = [
        'job_id' => 'integer',
        'contractor_id' => 'integer',
        'state' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'selected' => 'integer',
        'advance' => 'boolean',
        'hold_proposal' => 'boolean',
        'proposed_price' => 'integer',
        'project_approve_auto' => 'boolean',
        'shipping_costs' => 'integer',
        'reject_reason_id' => 'integer',
        'reorder' => 'integer',
    ];

    /**
     * Relation to User(contractor).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function contractor()
    {
        return $this->belongsTo(User::class, 'contractor_id');
    }

    public function job()
    {
        return $this->belongsTo('App\Models\Job');
    }

    public function thread()
    {
        return $this->hasOne('App\Models\Thread', 'trade_id')->withDefault();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'job_roles', 'job_id', 'user_id', 'job_id')
            ->withTimestamps('created', 'modified');
    }

    public function outsourcer()
    {
        return $this->users()->wherePivot('role_id', JobRole::OUTSOURCER);
    }

    public function scopeOfTradeGroups($query, $jobId, $stateGroupId)
    {
        $query->where($this->getQualifiedColumn('job_id'), $jobId);
        if ($stateGroupId == TradeState::GROUP_ACTIVE) {
            $query->whereNotIn(
                $this->getQualifiedColumn('state'),
                TradeState::STATE_GROUPS[TradeState::GROUP_CLOSED]
            );
        } else {
            $query->whereIn(
                $this->getQualifiedColumn('state'),
                TradeState::STATE_GROUPS[$stateGroupId]
            );
        }
        return $query;
    }

    /*
     * 指定された仕事の取引グループに該当する取引を抽出する
     */
    public static function getTradesByStateGroup($jobId, $stateGroupId, $limit)
    {
        $trades = self::select('current_trades.*', 'job_roles.id as job_role_id')
            ->with(['contractor' => function ($q) {
                $q->select('id', 'username', 'thumbnail_url', 'resigned', 'active');
            }])
            ->join('job_roles', function ($join) {
                $join->on('job_roles.job_id', '=', 'current_trades.job_id')
                ->on('job_roles.user_id', '=', 'current_trades.contractor_id')
                ->where('job_roles.role_id', JobRole::CONTRACTOR);
            })
            ->leftJoin('bookmarks', function ($join) {
                $join->on('job_roles.id', '=', 'bookmarks.foreign_key')
                ->where('bookmarks.model', 'JobRole');
            })
            ->OfTradeGroups($jobId, $stateGroupId)
            ->orderBy('bookmarks.created', 'DESC')
            ->orderBy('current_trades.modified', 'DESC')
            ->paginate($limit);
        return $trades;
    }

    /*
     * 指定された仕事の最新状態を取得する
     *
     * @param integer $jobId
     * @param integer $userId
     * @return integer
     */
    public static function getCurrentState(int $jobId, int $userId): int
    {
        $trade = self::select('current_trades.state')
            ->where('current_trades.job_id', $jobId)
            ->where('current_trades.contractor_id', $userId)
            ->first();
        return $trade->state;
    }

    /**
     * 指定した仕事の取引が全て終了しているか取得する
     *
     * @param integer $jobId
     * @return bool
     */
    public static function isFinishedAll4Outsourcer(int $jobId): bool
    {
        $result = self::where('current_trades.job_id', $jobId)
            ->whereNotIn('current_trades.state', TradeState::STATE_GROUPS[TradeState::GROUP_CLOSED])
            ->exists();

        return ! $result;
    }

    /**
     * 返答期限を返す
     */
    public function getExpireDate()
    {
        $expireDate = null;
        // 検収 取引中止 応募 評価
        if (in_array($this->state, TradeState::STATE_WITH_AUTO_LIST)
            && is_null($this->selected)) {
            $expireDate = $this->created->addDays(TradeState::TRADE_AUTO_PROCEED_DAY)
                ->setTimezone('Asia/Tokyo')->format('Y/m/d');
        }
        return $expireDate;
    }

    /**
     * 現在の取引に対して支払予定額(後払いの場合、後払い手数料を含む)を返す
     * @param $proposedPrice
     * @param $quantity
     * @return float|int
     */
    public function getCurrentPaymentPrice($proposedPrice, $quantity)
    {
        $outsourcer = optional($this->job->outsourcer)->first();
        $currentPaymentPrice = $proposedPrice * $quantity;
        if ($this->job->deferrable && $outsourcer) {
            // 後払いの場合、後払い手数料を含める
            $deferringFee = $outsourcer->deferringFee->generateDeferringFee($currentPaymentPrice);
            $currentPaymentPrice = $currentPaymentPrice + $deferringFee;
        }
        return $currentPaymentPrice;
    }
}
