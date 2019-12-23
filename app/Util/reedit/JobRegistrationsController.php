<?php

namespace App\Http\Controllers\V1\Internal\Client;

use App\Domain\JobTemporariness\JobDetail\JobDetailService;
use App\Domain\JobTemporariness\ProjectTemporariness;
use App\Domain\JobTemporariness\TaskTemporariness;
use App\Domain\Message\MessageService;
use App\Http\Controllers\Controller;
use App\Http\RestResponse;
use App\Models\BusinessCategory;
use App\Models\Job;
use App\Models\JobDetailRegisterItem;
use App\Models\JobDetailWritingItem;
use App\Models\S3Doc;
use App\Models\Task;
use App\Models\Temporariness;
use App\Models\TradeParameter;
use App\Models\Wall;
use App\Services\PaymentService\PaymentClient;
use App\Transformers\JobShowTransformer;
use DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Log;

class JobRegistrationsController extends Controller
{
    use RestResponse;

    const TIMEOUT = 30;
    const AUDIT_TRADE_PARAMETER = 'tradeParameter';
    const AUDIT_JOB_DETAIL_REGISTER = 'jobDetailRegisterItem';
    const AUDIT_JOB_DETAIL_WRITING = 'jobDetailWritingItem';
    const AUDIT_TABLE = [
        self::AUDIT_TRADE_PARAMETER,
        self::AUDIT_JOB_DETAIL_REGISTER,
        self::AUDIT_JOB_DETAIL_WRITING,
    ];

    const BUSINESS_CATEGORIES_ID_WRITING = 12; // ライティング
    const BUSINESS_CATEGORIES_ID_REGISTER = 1268; // 商品登録

    protected $transformer;
    protected $resourceType;

    private $s3DocService;
    private $detailService;
    private $loginUser;
    private $httpMethod;
    private $step1Value;
    private $temporarinesses;

    private $syncModels = [
        'businessCareers',
        'environments',
        'prefectures',
        'businessSkills',
        'jobTags',
        'partners',
        'users',
    ];

    const INVALID_TEMPORARINESS_ERROR = 'invalid temporariness';

    public function __construct(
        JobShowTransformer $transformer,
        JobDetailService $detailService
    ) {
        $this->resourceType = "job_registrations";
        $this->baseUrl = route('client.job_registrations.index', ['id' => request()->route('id')]);
        $this->loginUser = auth('api')->user();
        $this->transformer = $transformer;
        $this->s3DocService = resolve('App\Services\S3\S3DocService');

        // STEP2 > カテゴリごとの入力項目
        $this->detailService = $detailService;
    }

    /**
     * 下書きのデータから仕事登録を行う
     *
     * @param Request $request
     * @param PaymentClient $paymentClient
     * @see https://github.com/uluru/altair/blob/develop/doc/api/v1/internal/client/job_registrations_post.md
     */
    public function store(Request $request, PaymentClient $paymentClient)
    {
        $this->httpMethod = $request->method();
        $job;
        $chargedPrice = 0;

        try {
            $records = $this->generateJobRecord($request);
            Log::info($records);

            DB::transaction(function () use ($records, &$job, &$chargedPrice, $paymentClient) {
                // 下書きデータから jobs 作成
                $job = Job::create($records['job']);
                unset($records['job']);

                // 添付ファイルデータ取得
                $docs = [];
                if (!empty($records['jobTemporarinessDocs'])) {
                    $docs = $records['jobTemporarinessDocs'];
                    unset($records['jobTemporarinessDocs']);
                }

                foreach ($records as $model => $params) {
                    if (in_array($model, $this->syncModels)) {
                        $job->{$model}()->sync($params);
                    } else {
                        $saved = $job->{$model}()->create($params);

                        if (in_array($model, Job::S3_MODELS) && !empty($docs)) {
                            // 添付ファイルのデータを移動する
                            foreach ($docs as $doc) {
                                // JobTemporarinessDoc -> S3Doc へ移動
                                $saved->s3Docs()->create(
                                    [
                                        's3_path' => $doc->s3_path,
                                        'filename' => $doc->filename,
                                        'group' => S3Doc::GROUP_SUPPLEMENT
                                    ]
                                );
                            }
                        }
                    }
                }
                // 自動承認且つ前払いのみ/タスク登録の決済
                $chargedPrice = $this->prepaidTaskPayment($records, $job, $paymentClient);

                // 後払いOR自動承認且つ前払い/タスク登録エスクロー、後払い上限判定
                $this->escrowTaskPrice($records, $job);

                // 下書き削除
                $this->deleteTemporarinesses();

                // タスク wall 作成
                $this->generateTaskWall($job);

                // メール送信
                $this->sendMail($job, $records);
            });
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['errCode' => $e->getCode()]);

            // クレジットカード決済を取り消す
            $creditDeposit = $paymentClient->getCreatedCreditDeposit();
            if (! is_null($creditDeposit)) {
                if ($paymentClient->deleteCreditDepositRecord($creditDeposit) === false) {
                    Log::error(sprintf(
                        'JobRegistrationsController: Failure delete creditDeposit record. creditDeposit.id = %s',
                        $creditDeposit->id
                    ));
                }
            }

            if ($e instanceof ModelNotFoundException || $e->getMessage() == self::INVALID_TEMPORARINESS_ERROR) {
                // 下書きデータ不足などイレギュラーな場合
                return $this->sendFail(404);
            }
            if ($e instanceof \PaymentService\Error\Base) {
                // 決済サービスのエラー
                return $this->sendFail(400);
            }

            return $this->sendErr();
        }

        if ($chargedPrice > 0 && $job->activated) {
            // 自動承認且つ前払いのみ/着金確認メールを送付する
            \Mail::queue(new \App\Mail\Mails\Deposit\CaughtDeposit(
                $this->loginUser,
                $chargedPrice,
                \App\Mail\Mails\Deposit\CaughtDeposit::DEPOSIT_TITLE_CHARGE . "\n" . $job->name,
                false
            ));
        }

        $return = Job::getJobShow($job->id);
        return $this->sendSuccess(200, $this->formatItem($return));
    }

    /**
     * 下書きのデータから差し戻し再編集を行う
     *
     * @param Request $request
     * @see https://github.com/uluru/altair/blob/develop/doc/api/v1/internal/client/job_registrations_put.md
     */
    public function update(Request $request)
    {
        $this->httpMethod = $request->method();
        $job;

        try {
            $records = $this->generateJobRecord($request);

            DB::transaction(function () use ($records, &$job) {
                // 仕事idから、データベースに登録済みの仕事を取得
                $job = Job::with('tradeParameter', 'task')->findOrFail($this->step1Value->job_id);

                // 差し戻し編集時、仕事タイプに変更があった場合
                if (! empty($this->step1Value->job_id)
                    && $this->step1Value->type !== $job->type
                ) {
                    $this->updateJobTypeData($this->step1Value->type, $job, $records);
                }

                // 差し戻し編集時、仕事カテゴリーに変更があった場合
                if (! empty($this->step1Value->job_id)
                    && $this->step1Value->business_category_id !== $job->business_category_id
                ) {
                    $this->updateCategoryData($this->step1Value->business_category_id, $job, $records);
                }

                // 下書きデータから jobs 作成
                $job->update($records['job']);
                unset($records['job']);

                // S3Doc が紐づくモデル
                if ($this->step1Value->type == Job::TYPE_PROJECT) {
                    $s3Model = 'tradeParameter';
                } else {
                    $s3Model = 'task';
                }

                // 既存の添付ファイル削除
                if (!$job->{$s3Model}->s3Docs->isEmpty()) {
                    $this->s3DocService->deleteS3Object($job->{$s3Model});
                }

                // JobTemporarinessDoc -> S3Doc へ移動
                if (!empty($records['jobTemporarinessDocs'])) {
                    foreach ($records['jobTemporarinessDocs'] as $doc) {
                        $job->{$s3Model}->s3Docs()->create(
                            [
                                's3_path' => $doc->s3_path,
                                'filename' => $doc->filename,
                                'group' => S3Doc::GROUP_SUPPLEMENT
                            ]
                        );
                    }
                    unset($records['jobTemporarinessDocs']);
                }

                foreach ($records as $model => $params) {
                    if (in_array($model, $this->syncModels)) {
                        $job->{$model}()->sync($params);
                    } else {
                        // TODO 新規追加時にネストが深くなるのでリファクタリングしたい。
                        //　Auditsで直でオブジェクトを使いupdateするようにする
                        if (in_array($model, self::AUDIT_TABLE)) {
                            if ($model === self::AUDIT_TRADE_PARAMETER) {
                                $tradeParameter = TradeParameter::where('job_id', $job->id)->firstOrFail();
                                $tradeParameter->update($params);
                            }

                            if ($model === self::AUDIT_JOB_DETAIL_REGISTER) {
                                $jobDetailRegisterItem = JobDetailRegisterItem::where('job_id', $job->id)->firstOrFail();
                                $jobDetailRegisterItem->update($params);
                            }

                            if ($model === self::AUDIT_JOB_DETAIL_WRITING) {
                                $jobDetailWritingItem = JobDetailWritingItem::where('job_id', $job->id)->firstOrFail();
                                $jobDetailWritingItem->update($params);
                            }
                        } else {
                            $job->{$model}()->update($params);
                        }
                    }
                }

                // 下書き削除
                $this->deleteTemporarinesses();

                // メール送信
                $this->sendMail($job, $records);
            });
        } catch (\Exception $e) {
            Log::error($e);

            if ($e instanceof ModelNotFoundException || $e->getMessage() == self::INVALID_TEMPORARINESS_ERROR) {
                // 下書きデータ不足などイレギュラーな場合
                return $this->sendFail(404);
            }
            return $this->sendErr();
        }

        $return = Job::getJobShow($job->id);
        return $this->sendSuccess(200, $this->formatItem($return));
    }

    /**
     * 仕事データを作成する
     *
     * @return array
     */
    private function generateJobRecord($request): array
    {
        // 下書き取得
        $this->getTemporarinesses();

        // STEP1 の値を保持
        $this->step1Value = json_decode($this->temporarinesses[0]->value);

        // メソッド判定
        if ($this->httpMethod == 'POST') {
            if (!empty($this->step1Value->job_id)) {
                // 差し戻し再編集ではない場合には job_id があるのはおかしい
                throw new \Exception(self::INVALID_TEMPORARINESS_ERROR);
            }
        } else {
            // 再編集できる仕事か再度確認
            $canReEdit = Job::canReEdit($this->step1Value->job_id, $this->loginUser->id);
            if (!$canReEdit) {
                throw new \Exception(self::INVALID_TEMPORARINESS_ERROR);
            }
        }

        // STEP1
        $records = $this->getInstance($this->temporarinesses[0])->generateByFirstStep();

        // STEP2
        $records = array_merge_recursive(
            $records,
            $this->getInstance(
                $this->temporarinesses[1],
                $this->step1Value->business_category_id,
                $this->step1Value->type
            )->generateBySecondStep()
        );
        // STEP2 > カテゴリごとの登録項目
        $records = array_merge_recursive(
            $records,
            $this->detailService->getRecord(
                $this->step1Value->business_category_id,
                $this->step1Value->type,
                $this->temporarinesses[1]
            )
        );

        // STEP3
        $records = array_merge_recursive(
            $records,
            $this->getInstance($this->temporarinesses[2])->generateByThirdStep()
        );

        // STEP4
        $records = array_merge_recursive(
            $records,
            $this->getInstance($this->temporarinesses[3])->generateByFourthStep()
        );

        // 下書き以外の情報から設定するデータ
        $records = array_merge_recursive(
            $records,
            $this->getInstance(null)->generateOther()
        );

        return $records;
    }

    /**
     * 下書きデータ取得
     *
     * @return void
     */
    private function getTemporarinesses(): void
    {
        $ids = $this->generateTemporarinessIds();

        $this->temporarinesses = Temporariness::with('jobTemporarinessDocs')
            ->where('user_id', request()->route('id'))
            ->findOrFail($ids)
            ->sortBy('id');

        if (count($this->temporarinesses) != count(Temporariness::JOB_REGISTER_STEPS)) {
            // 基本的に直リンくらいでしか起こり得ない
            throw new \Exception(self::INVALID_TEMPORARINESS_ERROR);
        }
    }

    /**
     * Temporariness の id を生成する
     *
     * @return array
     */
    private function generateTemporarinessIds(): array
    {
        $ids = [];

        for ($step = 1; $step <= count(Temporariness::JOB_REGISTER_STEPS); $step++) {
            if ($this->httpMethod == 'POST') {
                // 仕事登録、複製
                $ids[] = sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    $step,
                    $this->loginUser->id
                );
            } else {
                // 差し戻し再編集
                $ids[] = sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    $step,
                    request()->route('job_registration') // 仕事ID
                );
            }
        }

        return $ids;
    }

    /**
     * temporariness, job_temporariness_docs 削除
     */
    private function deleteTemporarinesses()
    {
        foreach ($this->temporarinesses as $temporariness) {
            $temporariness->delete();
        }
    }

    /**
     * 自動承認の時にメールを送信する
     * @return void
     */
    private function sendMail(Job $job, array $records): void
    {
        if (!$job['activated']) {
            return;
        }

        $job->getMethods()->sendApprovedMail(
            $this->loginUser,
            isset($records['partners']) ? $records['partners'] : []
        );
    }

    /**
     * タスクの新規登録の時にウォールを作成する
     * @param Job $job
     * @return void
     */
    private function generateTaskWall(Job $job): void
    {
        if ($job['type'] === Job::TYPE_PROJECT) {
            return;
        }

        $messageService = resolve(MessageService::class);
        $messageService->createBoard(
            Wall::TYPE_TASK_OUTSOURCER,
            $this->loginUser->id,
            $job['id']
        );
    }

    /**
     * 自動承認/前払いクライアント・タスク登録時のクレカ決済
     * @param array $records
     * @param Job $job
     * @param PaymentClient $paymentClient
     * @return int 決済した金額
     */
    private function prepaidTaskPayment(array $records, Job $job, PaymentClient $paymentClient)
    {
        if ($job->type === Job::TYPE_PROJECT || $this->httpMethod == 'PUT' || $this->loginUser->deferrable || !$job->activated) {
            return 0;
        }

        $price = $records['task']['unit_price'] * $records['task']['quantity'];
        $this->loginUser->getMethods()->generateJobChargeByCredit($price, $job, $paymentClient, null, 0, $price, null);

        return $price;
    }

    /**
     * タスク承認時のエスクロー、task_trades の作成
     * @param array $records
     * @param Job $job
     */
    private function escrowTaskPrice(array $records, Job $job)
    {
        if (!$job->activated || $job->type === Job::TYPE_PROJECT || $this->httpMethod == 'PUT') {
            return;
        }

        $result = $this->loginUser->getMethods()->generateTaskEscrow(
            $records['task']['unit_price'],
            $records['task']['quantity'],
            $job
        );

        if (!$result) {
            throw new \Exception();
        }
    }

    /**
     * @return ProjectTemporariness|TaskTemporariness
     */
    private function getInstance(?Temporariness $temporariness, $businessCategoryId = null, $type = null)
    {
        if ($this->step1Value->type == Job::TYPE_PROJECT) {
            return new ProjectTemporariness($temporariness, $businessCategoryId, $type);
        } else {
            return new TaskTemporariness($temporariness, $businessCategoryId, $type);
        }
    }

    /**
     * 差し戻し編集時、step-1で仕事タイプを変更した場合
     * @param int $newJobType
     * @param Job $job
     * @param array $records
     */
    private function updateJobTypeData($newJobType, $job, &$records)
    {
        // 関連テーブルの既存データを修正する
        // createdを、jobsと合わせる必要はあるか？
        if ($newJobType === Job::TYPE_PROJECT) {
            // タスクからプロジェクトに変更
            $task = Task::where('job_id', $job->id)->firstOrFail();
            $task->delete();

            $job->tradeParameter()->create($records['tradeParameter']);
            unset($records['tradeParameter']);
        } elseif ($newJobType === Job::TYPE_TASK) {
            // プロジェクトからタスクに変更
            $tradeParameter = TradeParameter::where('job_id', $job->id)->firstOrFail();
            $tradeParameter->delete();

            $job->task()->create($records['task']);
            unset($records['task']);
        }
    }

    /**
     * 差し戻し編集時、step-1で仕事カテゴリーを変更した場合
     * @param int $newBusinessCategoryId
     * @param Job $job
     * @param array $records
     */
    private function updateCategoryData($newBusinessCategoryId, $job, &$records)
    {
        // 変更前の仕事カテゴリー
        $isOldCategoryWriting = $this->isWritingCategory($job->business_category_id); // 変更前の仕事カテゴリーが「ライティング」
        $isOldCategoryRegister = ($job->business_category_id === self::BUSINESS_CATEGORIES_ID_REGISTER); // 変更前の仕事カテゴリーが「商品登録」
        $isNewCategoryWriting = $this->isWritingCategory($newBusinessCategoryId); // 変更後の仕事カテゴリーが「ライティング」
        $isNewCategoryRegister = ($newBusinessCategoryId === self::BUSINESS_CATEGORIES_ID_REGISTER); // 変更後の仕事カテゴリーが「商品登録」
        
        if (! ($isOldCategoryWriting || $isOldCategoryRegister || $isNewCategoryWriting || $isNewCategoryRegister)) {
            return;
        }

        // 関連テーブルの削除・更新
        // リレーションで1つにまとめられないか？
        if ($isOldCategoryWriting) {
            $jobDetailWritingItem = JobDetailWritingItem::where('job_id', $job->id)->firstOrFail();
            $jobDetailWritingItem->delete();
        }
        
        if ($isOldCategoryRegister) {
            $jobDetailRegisterItem = JobDetailRegisterItem::where('job_id', $job->id)->firstOrFail();
            $jobDetailRegisterItem->delete();
        }

        if ($isNewCategoryWriting) {
            $job->jobDetailWritingItem()->create($records['jobDetailWritingItem']);
            unset($records['jobDetailWritingItem']);
        }

        if ($isNewCategoryRegister) {
            $job->jobDetailRegisterItem()->create($records['jobDetailRegisterItem']);
            unset($records['jobDetailRegisterItem']);
        }

        // $jobsテーブルに含まれる情報の更新のため
        // 変更前はProcessorに含まれる仕事カテゴリに該当し、変更後は該当しない場合
        if (($isOldCategoryWriting || $isOldCategoryRegister)
            && (! ($isNewCategoryWriting || $isNewCategoryRegister))
        ) {
            $records['job'] += [
                'teachme' => null,
                'recommend' => null,
                'prohibitions' => null,
                'pr_message' => null,
            ];
        }
    }

    private function isWritingCategory(int $businessCategoryId): bool
    {
        $writingCategory = BusinessCategory::where('id', $businessCategoryId)
            ->inParent(self::BUSINESS_CATEGORIES_ID_WRITING)
            ->exists();

        return $writingCategory;
    }
}
