<?php

namespace App\Http\Controllers\V1\Internal\Admin;

use App\Http\Controllers\Controller;
use App\Http\RestResponse;
use App\Models\AntisocialStatus;
use App\Models\JobRole;
use App\Transformers\Admin\JobCountTransformer;
use DB;
use Illuminate\Database\Query\Builder;

class JobsCountController extends Controller
{
    use RestResponse;

    protected $transformer;
    protected $resourceType;
    protected $baseUrl;

    public function __construct()
    {
        $this->resourceType = "jobs_count";
        $this->baseUrl = route('admin.jobs_count.index', ['id' => request()->route('id')]);
    }

    public function index(JobCountTransformer $transformer)
    {
        $this->transformer = $transformer;

        $jobCounts = [
            'waitingJobCount' => $this->baseQuery()->count(),
            'checkedAntisocialCount' => $this->getAntisocialCount()['checkedAntisocialCount'],
            'unCheckedAntisocialCount' => $this->getAntisocialCount()['unCheckedAntisocialCount']
        ];

        return $this->sendSuccess(200, $this->formatItem($jobCounts));
    }

    private function baseQuery(): Builder
    {
        $query = DB::table('jobs')
            ->join('job_roles', function ($join) {
                $join->on('jobs.id', '=', 'job_roles.job_id')
                    ->where('job_roles.role_id', JobRole::OUTSOURCER);
            })
            ->join('users', 'job_roles.user_id', '=', 'users.id')
            ->where('activated', false)
            ->where('rejected', false)
            ->where('closed', false);

        return $query;
    }

    private function getAntisocialCount(): array
    {
        $checkedAntisocialCount = $this->baseQuery()
            ->where('antisocial', AntisocialStatus::CHECK_OK)
            ->where('antisocial_check_date', '<>', null)
            ->count();

        $unCheckedAntisocialCount = $this->baseQuery()
            ->where('antisocial', AntisocialStatus::UNCHECKED)
            ->where('antisocial_check_date', null)
            ->count();

        return compact('checkedAntisocialCount', 'unCheckedAntisocialCount');
    }
}
