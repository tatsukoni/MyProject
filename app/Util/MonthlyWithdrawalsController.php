<?php

namespace App\Http\Controllers\V1\Internal\Admin;

use App\Http\Controllers\Controller;
use App\Http\RestResponse;
use App\Http\Requests\Admin\MonthlyWithdrawalsRequest;
use App\Jobs\Admin\MonthlyWithdrawal;
use Carbon\Carbon;

class MonthlyWithdrawalsController extends Controller
{
    use RestResponse;

    protected $resourceType;

    public function __construct()
    {
        $this->resourceType = 'monthly_withdrawals';
    }

    /**
     * @param MonthlyWithdrawalsRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @link  https://github.com/uluru/altair/blob/develop/doc/api/v1/internal/admin/monthly_withdrawals_post.md
     */
    public function store(MonthlyWithdrawalsRequest $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (! ($this->checkDateFormat($startDate)) || ! ($this->checkDateFormat($endDate))) {
            return $this->sendValidationErr(['error' => ['日付は「Y-m-d」もしくは「Y-m-d H:i:s」形式で入力してください']]);
        }

        MonthlyWithdrawal::dispatch($startDate, $endDate);
        return $this->sendSuccess(202, $this->formatSuccess());;
    }

    // 「Y-m-d」もしくは「Y-m-d H:i:s」の日付形式だけを許可する
    public function checkDateFormat(string $targetDate): bool
    {
        return ((Carbon::hasFormat($targetDate, 'Y-m-d')) || (Carbon::hasFormat($targetDate, 'Y-m-d H:i:s'))) ? true : false;
    }
}
