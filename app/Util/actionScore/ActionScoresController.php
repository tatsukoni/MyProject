<?php

namespace App\Http\Controllers\V1\Internal\Admin;

use App\Http\Controllers\Controller;
use App\Http\RestResponse;
use App\Http\Requests\Admin\MonthlyWithdrawalsRequest;
use App\Jobs\Admin\ActionScoreJob;
use Carbon\Carbon;

class ActionStoresController extends Controller
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
    public function store(ActionStoresRequest $request)
    {
        $targetData = $request->input('target_data');
        $userIds = $this->getUserIds($request->input('user_ids'));

        if (empty($userIds)) {
            return $this->sendValidationErr(['user_ids' => ['ユーザーidの入力情報を再度ご確認ください']]);
        }

        ActionScoreJob::dispatch($targetData, $userIds);
        return $this->sendSuccess(202, $this->formatSuccess());
    }

    // userIdsを、個々のuserIdに分解する
    private function getUserIds(string $inputUsersInfo): array
    {
        $usersArray = explode(',', $inputUsersInfo);
        $userIds = [];
        
        foreach ($usersArray as $user) {
            $userId = trim($user);
            if (is_numeric($userId)) {
                $userIds += (int)$userId;
            } else {
                continue;
            }
        }

        return $userIds;
    }
}
