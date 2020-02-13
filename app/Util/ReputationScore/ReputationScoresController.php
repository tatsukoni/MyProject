<?php

namespace App\Http\Controllers\V1\Internal\Admin;

use App\Http\Controllers\Controller;
use App\Http\RestResponse;
use App\Http\Requests\Admin\ReputationScoresRequest;
use App\Jobs\Admin\ReputationScoreJob;

class ReputationScoresController extends Controller
{
    use RestResponse;

    protected $resourceType;

    public function __construct()
    {
        $this->resourceType = 'reputation_scores';
    }

    /**
     * @param ReputationScoresRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @link
     */
    public function store(ReputationScoresRequest $request)
    {
        $userIds = $this->getUserIds($request->input('user_ids'));

        if (empty($userIds)) {
            return $this->sendValidationErr(['user_ids' => ['ユーザーidの入力情報を再度ご確認ください']]);
        }

        ReputationScoreJob::dispatch($userIds);
        return $this->sendSuccess(202, $this->formatSuccess());
    }

    // userIdsを、個々のuserIdに分解する
    private function getUserIds(string $inputUsersValue): array
    {
        $usersArray = explode(',', $inputUsersValue);
        $userIds = [];
        
        foreach ($usersArray as $user) {
            $userId = trim($user);
            if (is_numeric($userId)) {
                $userIds[] = (int)$userId;
            } else {
                continue;
            }
        }

        return $userIds;
    }
}
