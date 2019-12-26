<?php

namespace Tests\Feature\Controllers\V1\Internal\Client;

use Carbon\Carbon;
use Mockery;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Controllers\V1\Internal\JobTemporariness;
use App\Models\BusinessCategory;
use App\Models\BusinessCareer;
use App\Models\BusinessSkill;
use App\Models\Environment;
use App\Models\Job;
use App\Models\JobRole;
use App\Models\JobTag;
use App\Models\JobTagValue;
use App\Models\Partner;
use App\Models\Prefecture;
use App\Models\Temporariness;
use App\Models\User;
use App\Models\WorkableTime;

class JobTemporarinessControllerTest extends TestCase
{
    use DatabaseTransactions;
    use JobTemporariness; // 下書きテンプレート共有のため

    protected $url;
    const API_TYPE  = 'job_temporarinesses';
    const UPLOAD_FILE_LIMIT = 3;
    const UPLOAD_FILE_SIZE_LIMIT_MEGA = 25;

    private function setUrl(User $user, $temporarinessId = null)
    {
        if (is_null($temporarinessId)) {
            $this->url = $this->internalDomain . '/api/v1/client/' . $user->id . '/job_temporarinesses';
        } else {
            $this->url = $this->internalDomain . '/api/v1/client/' . $user->id . '/job_temporarinesses/' . $temporarinessId;
        }
    }

    public function testShow1stStep200NoData()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();

        $this->setUrl($user, 1);
        $this->setAuthHeader($user);
        $jobTemporariness = $this->get($this->url, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => null
                ]
            ]
        );
    }

    public function testShow1stStep200()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();

        $temporariness = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        $this->setUrl($user, $temporariness->id);
        $this->setAuthHeader($user);

        // Act
        $jobTemporariness = $this->get($this->url, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(
                        Temporariness::JOB_REGISTER_ID_FORMAT,
                        1,
                        $user->id
                    ),
                    'attributes' => [
                        'business_category_id' => $this->getStep1ProjectValue()['business_category_id'],
                        'type' => $this->getStep1ProjectValue()['type']
                    ]
                ]
            ]
        );
    }

    public function testShowOtherTemporariness()
    {
        // Arrange
        $users = factory(User::class, 2)->states('client')->create();
        $firstStep = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $users[0]->id
                ),
                'user_id' => $users[0]->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        $secondStep = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    2,
                    $users[0]->id
                ),
                'user_id' => $users[0]->id,
                'value' => json_encode($this->getStep2ProjectValue())
            ]
        );
        $this->setAuthHeader($users[1]);

        // Act
        // 他人の下書きにアクセス
        $this->setUrl($users[1], $firstStep->id);
        $jobTemporariness = $this->get($this->url, $this->headers)->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => null
                ]
            ]
        );

        $this->setUrl($users[1], $secondStep->id);
        $jobTemporariness = $this->get($this->url, $this->headers)->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => null
                ]
            ]
        );
    }

    public function testStore1stStep200()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        $businessCategory = factory(BusinessCategory::class)->create(
            ['parent_id' => 1]
        );
        $this->setUrl($user);
        $this->setAuthHeader($user);

        // Act
        // 仕事登録下書き
        $params = [
            'step_id' => 1,
            'type' => Job::TYPE_PROJECT,
            'business_category_id' => $businessCategory->id
        ];
        $jobTemporariness = $this->post($this->url, $params, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(Temporariness::JOB_REGISTER_ID_FORMAT, 1, $user->id),
                    'attributes' => [
                        'business_category_id' => $businessCategory->id,
                        'type' => Job::TYPE_PROJECT
                    ]
                ]
            ]
        );

        // 同一ユーザーが同一ステップで登録するとエラーになる
        $this->post($this->url, $params, $this->headers)->assertStatus(500);
    }

    public function testStore1stStep422()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        $this->doInput422Test1stStep('post', $user);
    }

    public function testUpdate1stStep200()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        $currentJobTemporariness = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        $businessCategory = factory(BusinessCategory::class)->create([
            'id' => 2,
            'parent_id' => 1,
            'task_format' => 1
        ]);
        $this->setUrl($user, $currentJobTemporariness->id);
        $this->setAuthHeader($user);

        // Act
        // 仕事登録下書き
        $params = [
            'step_id' => 1,
            'type' => Job::TYPE_TASK,
            'business_category_id' => $businessCategory->id
        ];
        $jobTemporariness = $this->put($this->url, $params, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => $currentJobTemporariness->id,
                    'attributes' => [
                        'business_category_id' => $params['business_category_id'],
                        'type' => $params['type']
                    ]
                ]
            ]
        );
    }

    public function testUpdate1stStep422()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        $currentJobTemporariness = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        $this->doInput422Test1stStep('put', $user, $currentJobTemporariness->id);

        // 差し戻し再編集時
        $job = factory(Job::class)->states('project', 'not_active', 're_edit')->create();
        factory(JobRole::class)->create(
            [
                'job_id' => $job->id,
                'user_id' => $user->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        // STEP1
        $currentJobTemporariness = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    1,
                    $job->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        $this->doInput422Test1stStep('put', $user, $currentJobTemporariness->id, $job->id, true);
    }

    private function doInput422Test1stStep($method, User $user, ?string $stepId, ?int $jobId, ?bool $reedit = false)
    {
        // Arrange
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => 1]
        );
        $businessCategoryChildNotTaskable = factory(BusinessCategory::class)->create(
            ['parent_id' => 1, 'task_format' => null]
        );
        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChildNonActive = factory(BusinessCategory::class)->create(
            ['parent_id' => 1, 'listed' => 0]
        );
        if ($method == 'post') {
            $this->setUrl($user);
        } else {
            $this->setUrl($user, $stepId);
        }
        $this->setAuthHeader($user);

        // Act
        // step_id が未指定
        $params = [
            'type' => Job::TYPE_PROJECT,
            'business_category_id' => $businessCategoryChild->id
        ];
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('step_id', $response->decodeResponseJson());

        // 親カテゴリ
        $params = [
            'step_id' => 1,
            'type' => Job::TYPE_PROJECT,
            'business_category_id' => $businessCategoryParent->id
        ];
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('business_category_id', $response->decodeResponseJson());

        // 非アクティブカテゴリ
        $params = [
            'step_id' => 1,
            'type' => Job::TYPE_TASK,
            'business_category_id' => $businessCategoryChildNonActive->id
        ];
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('business_category_id', $response->decodeResponseJson());

        // タスク登録できないカテゴリ
        $params = [
            'step_id' => 1,
            'type' => Job::TYPE_TASK,
            'business_category_id' => $businessCategoryChildNotTaskable->id
        ];
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('business_category_id', $response->decodeResponseJson());

        // 存在しない type_id
        $params = [
            'step_id' => 1,
            'type' => 3,
            'business_category_id' => $businessCategoryChild->id
        ];
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('type', $response->decodeResponseJson());

        // 差し戻し再編集時、決済済みの仕事に対して仕事タイプを変更しようとした場合
        if ($reedit) {
            factory(JobCharge::class)->states('credit')->create([
                'job_id' => $jobId
            ]);
            $params = [
                'step_id' => 1,
                'job_id' => $jobId,
                'type' => Job::TYPE_TASK,
                'business_category_id' => $businessCategoryChild->id
            ];
            $response = $this->{$method}($this->url, $params, $this->headers);
            $response->assertStatus(422);
            $this->assertArrayHasKey('job_id', $response->decodeResponseJson());
        }
    }

    public function testStore2ndStepProject200()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        $jobTags = factory(JobTag::class, JobTagValue::SETTING_LIMIT)->create();
        $workableTime = factory(WorkableTime::class)->create();

        // 新規下書き STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );

        // 差し戻し再編集 STEP1
        $job = factory(Job::class)->states('project', 'not_active', 're_edit')->create();
        factory(JobRole::class)->create(
            [
                'job_id' => $job->id,
                'user_id' => $user->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    1,
                    $job->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );

        $this->setUrl($user);
        $this->setAuthHeader($user);

        // Act & Assert
        // 新規下書き STEP2
        // 閾値チェック混み
        $params = $this->getStep2ProjectValue();
        $params['job_tag_ids'] = $jobTags->pluck('id')->all();
        $params['workable_time_id'] = $workableTime->id;
        $params['period_type'] = 1;
        $params['period'] = Carbon::today('Asia/Tokyo')->format('Y-m-d');
        $params['unit_price_other'] = 100000000;
        $params['orders_per_worker_other'] = 100000000;
        $params['capacity_other'] = 100000000;

        $jobTemporariness = $this->post($this->url, $params, $this->headers);
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(Temporariness::JOB_REGISTER_ID_FORMAT, 2, $user->id),
                    'attributes' => $params
                ]
            ]
        );

        // 差し戻し再編集 STEP2
        $params += [
            'job_id' => $job->id,
        ];
        $jobTemporariness = $this->post($this->url, $params, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(Temporariness::JOB_REEDIT_ID_FORMAT, 2, $job->id),
                    'attributes' => $params
                ]
            ]
        );
    }

    /**
     * teachme、recommend、prohibitionsの空、null要素を除いて処理していることを確認
     */
    public function testStore2ndStepRemoveNull200()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        $workableTime = factory(WorkableTime::class)->create();

        // 新規下書き STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );

        $this->setUrl($user);
        $this->setAuthHeader($user);

        // Act & Assert
        // 新規下書き STEP2
        // 閾値チェック混み
        $params = $this->getStep2ProjectValue();
        $params['job_tag_ids'] = [];
        $params['workable_time_id'] = $workableTime->id;
        $params['period_type'] = 1;
        $params['period'] = Carbon::today('Asia/Tokyo')->format('Y-m-d');
        $params['unit_price_other'] = 100000000;
        $params['orders_per_worker_other'] = 100000000;
        $params['capacity_other'] = 100000000;
        $params['teachme'] = [null, ''];
        $params['recommend'] = ['1', null];
        $params['prohibitions'] = [null, '2', null];

        // Act
        $jobTemporariness = $this->post($this->url, $params, $this->headers);

        // Assert
        $params['teachme'] = [];
        $params['recommend'] = ['1'];
        $params['prohibitions'] = ['2'];
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(Temporariness::JOB_REGISTER_ID_FORMAT, 2, $user->id),
                    'attributes' => $params
                ]
            ]
        );
    }

    public function testStore2ndStepTask200()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        $jobTag = factory(JobTag::class)->create();
        $workableTime = factory(WorkableTime::class)->create();

        // STEP1 下書き
        // 新規下書き STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1TaskValue())
            ]
        );

        // 差し戻し再編集 STEP1
        $job = factory(Job::class)->states('task', 'not_active', 're_edit')->create();
        factory(JobRole::class)->create(
            [
                'job_id' => $job->id,
                'user_id' => $user->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    1,
                    $job->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1TaskValue())
            ]
        );

        $this->setUrl($user);
        $this->setAuthHeader($user);

        // Act & Assert
        // 新規下書き STEP2
        // 閾値チェック混み
        $params = $this->getStep2TaskValue();
        $params['job_tag_ids'] = [$jobTag->id];
        $params['end_date'] = Carbon::today('Asia/Tokyo')->format('Y-m-d');
        $params['unit_price'] = 100000000;
        $params['quantity'] = 100000000;
        $params['max_delivery_type'] = 2;
        $params['max_delivery'] = 100000000;
        $jobTemporariness = $this->post($this->url, $params, $this->headers);
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(Temporariness::JOB_REGISTER_ID_FORMAT, 2, $user->id),
                    'attributes' => $params
                ]
            ]
        );

        // 差し戻し再編集 STEP2
        $params += [
            'job_id' => $job->id,
        ];
        $jobTemporariness = $this->post($this->url, $params, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(Temporariness::JOB_REEDIT_ID_FORMAT, 2, $job->id),
                    'attributes' => $params
                ]
            ]
        );
    }

    public function testUpdate2ndStepProject200()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        $jobTag = factory(JobTag::class)->create();
        $workableTime = factory(WorkableTime::class)->create();

        // 新規下書き
        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        // STEP2
        $newJobStep2 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    2,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode(['hoge' => 'hogehoge'])
            ]
        );

        // 差し戻し再編集
        $job = factory(Job::class)->states('project', 'not_active', 're_edit')->create();
        factory(JobRole::class)->create(
            [
                'job_id' => $job->id,
                'user_id' => $user->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    1,
                    $job->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        // STEP2
        $reeditJobStep2 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    2,
                    $job->id
                ),
                'user_id' => $user->id,
                'value' => json_encode(['fuga' => 'fugafuga'])
            ]
        );

        $this->setAuthHeader($user);

        // Act & Assert
        // 新規下書き STEP2
        $this->setUrl($user, $newJobStep2->id);
        $params = $this->getStep2ProjectValue();
        $params['job_tag_ids'] = [$jobTag->id];
        $params['workable_time_id'] = $workableTime->id;
        $params['end_date'] = Carbon::tomorrow('Asia/Tokyo')->format('Y-m-d');

        $jobTemporariness = $this->put($this->url, $params, $this->headers);
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => $newJobStep2->id,
                    'attributes' => $params
                ]
            ]
        );

        // 差し戻し再編集 STEP2
        $this->setUrl($user, $reeditJobStep2->id);
        $params += [
            'job_id' => $job->id,
        ];
        $jobTemporariness = $this->put($this->url, $params, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => $reeditJobStep2->id,
                    'attributes' => $params
                ]
            ]
        );
    }

    /**
     * teachme、recommend、prohibitionsの空、null要素を除いて処理していることを確認
     */
    public function testUpdate2ndStepProjectRemoveNull200()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        $workableTime = factory(WorkableTime::class)->create();

        // 新規下書き
        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        // STEP2
        $newJobStep2 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    2,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode(['hoge' => 'hogehoge'])
            ]
        );

        $this->setAuthHeader($user);
        $this->setUrl($user, $newJobStep2->id);

        // Act
        // 新規下書き STEP2
        $params = $this->getStep2ProjectValue();
        $params['job_tag_ids'] = [];
        $params['workable_time_id'] = $workableTime->id;
        $params['end_date'] = Carbon::tomorrow('Asia/Tokyo')->format('Y-m-d');
        $params['teachme'] = ['', null];
        $params['recommend'] = [null, '1'];
        $params['prohibitions'] = [null, '2', null];
        $jobTemporariness = $this->put($this->url, $params, $this->headers);

        // Assert
        $params['teachme'] = [];
        $params['recommend'] = ['1'];
        $params['prohibitions'] = ['2'];
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => $newJobStep2->id,
                    'attributes' => $params
                ]
            ]
        );
    }

    public function testUpdate2ndStepTask200()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        $jobTag = factory(JobTag::class)->create();
        $workableTime = factory(WorkableTime::class)->create();

        // 新規下書き
        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1TaskValue())
            ]
        );
        // STEP2
        $newJobStep2 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    2,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode(['hoge' => 'hogehoge'])
            ]
        );

        // 差し戻し再編集
        $job = factory(Job::class)->states('task', 'not_active', 're_edit')->create();
        factory(JobRole::class)->create(
            [
                'job_id' => $job->id,
                'user_id' => $user->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    1,
                    $job->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1TaskValue())
            ]
        );
        // STEP2
        $unitPrice = 22;
        $quantity = 33;
        $reeditJobStep2 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    2,
                    $job->id
                ),
                'user_id' => $user->id,
                'value' => json_encode([
                    'fuga' => 'fugafuga',
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity
                ])
            ]
        );

        $this->setAuthHeader($user);

        // Act & Assert
        // 新規下書き STEP2
        $this->setUrl($user, $newJobStep2->id);
        $params = $this->getStep2TaskValue();
        $params['job_tag_ids'] = [$jobTag->id];
        $params['workable_time_id'] = $workableTime->id;
        $params['end_date'] = Carbon::tomorrow('Asia/Tokyo')->format('Y-m-d');

        $jobTemporariness = $this->put($this->url, $params, $this->headers);
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => $newJobStep2->id,
                    'attributes' => $params
                ]
            ]
        );

        // 差し戻し再編集 STEP2
        $this->setUrl($user, $reeditJobStep2->id);
        $params += [
            'job_id' => $job->id,
        ];
        // タスクの差し戻し再編集の場合、単価、募集件数は更新されないこと
        $params['unit_price'] = $unitPrice;
        $params['quantity'] = $quantity;
        $jobTemporariness = $this->put($this->url, $params, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => $reeditJobStep2->id,
                    'attributes' => $params
                ]
            ]
        );
    }

    public function testStore2ndStepProject422()
    {
        // Arrange
        // 新規下書き
        $user = factory(User::class)->states('client')->create();
        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        $this->doInput422Test2ndStepProject('post', $user);

        // 差し戻し再編集
        $job = factory(Job::class)->states('project', 'not_active', 're_edit')->create();
        factory(JobRole::class)->create(
            [
                'job_id' => $job->id,
                'user_id' => $user->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    1,
                    $job->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        $this->doInput422Test2ndStepProject('post', $user, $job->id);
    }

    public function testUpdate2ndStepProject422()
    {
        // Arrange
        // 新規下書き
        $user = factory(User::class)->states('client')->create();
        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        // STEP2
        $step2 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    2,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode(['hoge' => 'hogehoge'])
            ]
        );
        $this->doInput422Test2ndStepProject('put', $user, null, $step2->id);

        // 差し戻し再編集
        $job = factory(Job::class)->states('project', 'not_active', 're_edit')->create();
        factory(JobRole::class)->create(
            [
                'job_id' => $job->id,
                'user_id' => $user->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    1,
                    $job->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        // STEP2
        $step2 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    2,
                    $job->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        $this->doInput422Test2ndStepProject('put', $user, $job->id, $step2->id);
    }

    private function doInput422Test2ndStepProject(string $method, User $user, ?int $jobId = null, ?string $id = null)
    {
        if ($method == 'post') {
            $this->setUrl($user);
        } else {
            $this->setUrl($user, $id);
        }
        $this->setAuthHeader($user);

        // Act & Assert
        $defaultParams = $this->getStep2ProjectValue();
        if (!empty($jobId)) {
            $defaultParam['job_id'] = $jobId;
        }

        // 存在しない job_tag_id, 存在しない workable_time_id
        $response = $this->{$method}($this->url, $defaultParams, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('job_tag_ids', $response->decodeResponseJson());
        $this->assertArrayHasKey('workable_time_id', $response->decodeResponseJson());

        // 上限以上の job_tag_id
        $jobTags = factory(JobTag::class, JobTagValue::SETTING_LIMIT + 1)->create(
            ['active' => true]
        );
        $params = $defaultParams;
        $params['job_tag_ids'] = $jobTags->pluck('id')->all();
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('job_tag_ids', $response->decodeResponseJson());

        // Active じゃない job_tag_id
        $jobTags = factory(JobTag::class, 3)->create(
            ['active' => false]
        );
        $params = $defaultParams;
        $params['job_tag_ids'] = $jobTags->pluck('id')->all();
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('job_tag_ids', $response->decodeResponseJson());

        // 削除された workable_time_id
        $workableTime = factory(WorkableTime::class)->create(
            ['deleted_at' => Carbon::yesterday()]
        );
        $params = $defaultParams;
        $params['workable_time_id'] = $workableTime->id;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('workable_time_id', $response->decodeResponseJson());

        // unit_price_other 閾値
        $params = $defaultParams;
        $params['unit_price_other'] = 0;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('unit_price_other', $response->decodeResponseJson());

        $params['unit_price_other'] = 100000001;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('unit_price_other', $response->decodeResponseJson());

        // orders_per_worker_other 閾値
        $params = $defaultParams;
        $params['unit_price_other'] = 0;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('unit_price_other', $response->decodeResponseJson());

        $params['orders_per_worker_other'] = 100000001;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('unit_price_other', $response->decodeResponseJson());

        // capacity_other 閾値
        $params = $defaultParams;
        $params['capacity_other'] = 0;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('capacity_other', $response->decodeResponseJson());

        $params['capacity_other'] = 100000001;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('capacity_other', $response->decodeResponseJson());

        // period 閾値
        $params = $defaultParams;
        $params['period_type'] = 1;
        $params['period'] = Carbon::yesterday()->format('Y-m-d');
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('period', $response->decodeResponseJson());

        // estimated_working_time_idが未入力
        $params = $defaultParams;
        $params['estimated_working_time_id'] = null;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('estimated_working_time_id', $response->decodeResponseJson());

        // 存在しないestimated_working_time_id
        $params = $defaultParams;
        $params['estimated_working_time_id'] = 5;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('estimated_working_time_id', $response->decodeResponseJson());

        // estimated_minutesがintじゃない
        $params = $defaultParams;
        $params['estimated_minutes'] = str_random();
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('estimated_minutes', $response->decodeResponseJson());

        // estimated_minutesが未入力
        $params = $defaultParams;
        $params['estimated_minutes'] = null;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('estimated_minutes', $response->decodeResponseJson());

        // estimated_minutesが１未満
        $params = $defaultParams;
        $params['estimated_minutes'] = 0;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('estimated_minutes', $response->decodeResponseJson());

        // estimated_minutesが43201以上
        $params = $defaultParams;
        $params['estimated_minutes'] = 43201;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('estimated_minutes', $response->decodeResponseJson());
    }

    public function testStore2ndStepTask422()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        // 新規下書き
        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1TaskValue())
            ]
        );
        $this->doInput422Test2ndStepTask('post', $user);

        // 差し戻し再編集
        $job = factory(Job::class)->states('task', 'not_active', 're_edit')->create();
        factory(JobRole::class)->create(
            [
                'job_id' => $job->id,
                'user_id' => $user->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    1,
                    $job->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1TaskValue())
            ]
        );
        $this->doInput422Test2ndStepTask('post', $user, $job->id);
    }

    public function testUpdate2ndStepTask422()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        // 新規下書き
        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1TaskValue())
            ]
        );
        // STEP2
        $step2 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    2,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep2TaskValue())
            ]
        );
        $this->doInput422Test2ndStepTask('put', $user, null, $step2->id);

        // 差し戻し再編集
        $job = factory(Job::class)->states('task', 'not_active', 're_edit')->create();
        factory(JobRole::class)->create(
            [
                'job_id' => $job->id,
                'user_id' => $user->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    1,
                    $job->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1TaskValue())
            ]
        );
        // STEP2
        $step2 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    2,
                    $job->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep2TaskValue())
            ]
        );
        $this->doInput422Test2ndStepTask('post', $user, $job->id, $step2->id);
    }

    private function doInput422Test2ndStepTask(string $method, User $user, ?int $jobId = null, ?string $id = null)
    {
        if ($method == 'post') {
            $this->setUrl($user);
        } else {
            $this->setUrl($user, $id);
        }
        $this->setAuthHeader($user);

        // Act & Assert
        $defaultParams = $this->getStep2TaskValue();
        if (!empty($jobId)) {
            $defaultParam['job_id'] = $jobId;
        }

        // 存在しない job_tag_id
        $response = $this->{$method}($this->url, $defaultParams, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('job_tag_ids', $response->decodeResponseJson());

        // 上限以上の job_tag_id
        $jobTags = factory(JobTag::class, JobTagValue::SETTING_LIMIT + 1)->create(
            ['active' => true]
        );
        $params = $defaultParams;
        $params['job_tag_ids'] = $jobTags->pluck('id')->all();
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('job_tag_ids', $response->decodeResponseJson());

        // Active じゃない job_tag_id
        $jobTags = factory(JobTag::class, 3)->create(
            ['active' => false]
        );
        $params = $defaultParams;
        $params['job_tag_ids'] = $jobTags->pluck('id')->all();
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('job_tag_ids', $response->decodeResponseJson());

        // unit_price 閾値
        $params = $defaultParams;
        $params['unit_price'] = 0;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('unit_price', $response->decodeResponseJson());

        $params['unit_price'] = 100000001;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('unit_price', $response->decodeResponseJson());

        // quantity 閾値
        $params = $defaultParams;
        $params['quantity'] = 9;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('quantity', $response->decodeResponseJson());

        $params['quantity'] = 100000001;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('quantity', $response->decodeResponseJson());

        // max_delivery 閾値
        $params = $defaultParams;
        $params['max_delivery_type'] = 1;
        $params['max_delivery'] = 0;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('max_delivery', $response->decodeResponseJson());

        $params['max_delivery'] = 100000001;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('max_delivery', $response->decodeResponseJson());

        // max_deliveryがquantityを超える
        $params['max_delivery'] = $params['quantity'] + 1;
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('max_delivery', $response->decodeResponseJson());

        // end_date 閾値
        $params = $defaultParams;
        $params['end_date_type'] = 1;
        $params['end_date'] = Carbon::yesterday()->format('Y-m-d');
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('end_date', $response->decodeResponseJson());

        // キーワード指定にチェックがあるのに項目がない
        $params = $defaultParams;
        $params['questions'][1]['keywords'][] = [
            'checked' => true,
            'keyword' => null,
            'repeat_count' => null
        ];
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('questions.1.keywords.3.keyword', $response->decodeResponseJson());
        $this->assertArrayHasKey('questions.1.keywords.3.repeat_count', $response->decodeResponseJson());
    }

    // 添付ファイルのテストのみ
    public function testUploadFileOn3rdStep()
    {
        $user = factory(User::class)->states('client')->create();
        $jobTag = factory(JobTag::class)->create();
        $workableTime = factory(WorkableTime::class)->create();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        // S3ClientのMock化
        $s3ClientMock = $this->getS3ClientMock();
        // 既存データ作成用にMockのstoreS3Object戻り値を設定
        $s3ClientMock->shouldReceive('storeS3Object')->times(6)->andReturn(true);
        $s3ClientMock->shouldReceive('deleteS3Object')->times(3)->andReturn(true);
        $s3ClientMock->shouldReceive('getS3ObjectUrlByPath')->times(6)->andReturn(true);

        $this->setAuthHeader($user);
        $this->setUrl($user);
        $thirdStepId = sprintf(
            Temporariness::JOB_REGISTER_ID_FORMAT,
            3,
            $user->id
        );

        // Act
        // ファイル数閾値 over（異常）
        $files = [];
        for ($index = 0; $index < self::UPLOAD_FILE_LIMIT + 1; $index++) {
            $files[] = UploadedFile::fake()->image("file{$index}.png");
        }
        $params['step_id'] = 3;
        $params['files'] = $files;
        $jobTemporariness = $this->post($this->url, $params, $this->headers);
        $jobTemporariness->assertStatus(422);
        $this->assertArrayHasKey('files', $jobTemporariness->decodeResponseJson());

        // ファイル数、ファイルサイズ閾値（正常）
        $files = [];
        for ($index = 0; $index < self::UPLOAD_FILE_LIMIT; $index++) {
            $files[] = UploadedFile::fake()->image("file{$index}.png", 1024 * self::UPLOAD_FILE_SIZE_LIMIT_MEGA);
        }
        $params['files'] = $files;
        $jobTemporariness = $this->post($this->url, $params, $this->headers);
        $jobTemporariness->assertStatus(200);
        foreach ($params['files'] as $file) {
            $this->assertDatabaseHas(
                'job_temporariness_docs',
                [
                    'temporariness_id' => $thirdStepId,
                    'filename' => $file->name
                ]
            );
        }

        // 更新
        // ファイルを一部変更する
        $id = sprintf(
            Temporariness::JOB_REGISTER_ID_FORMAT,
            3,
            $user->id
        );
        $this->setUrl($user, $id);
        $params['files'][2] = UploadedFile::fake()->image("add_file.png", 1024 * self::UPLOAD_FILE_SIZE_LIMIT_MEGA);
        $jobTemporariness = $this->put($this->url, $params, $this->headers);
        $jobTemporariness->assertStatus(200);
        foreach ($params['files'] as $file) {
            $this->assertDatabaseHas(
                'job_temporariness_docs',
                [
                    'temporariness_id' => $thirdStepId,
                    'filename' => $file->name
                ]
            );
        }
    }

    public function testStore2ndStep404()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        $jobTag = factory(JobTag::class)->create();
        $workableTime = factory(WorkableTime::class)->create();

        $params = $this->getStep2ProjectValue();
        $params['job_tag_ids'] = [$jobTag->id];
        $params['workable_time_id'] = $workableTime->id;

        $this->setAuthHeader($user);

        // Act & Assert
        // STEP1 が存在しない状態で STEP2 の登録
        $this->setUrl($user);
        $this->post($this->url, $params, $this->headers)->assertStatus(404);

        // STEP1 が存在しない状態で STEP2 の更新
        $temporariness = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    2,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode(['hoge' => 'hogehoge'])
            ]
        );
        $this->setUrl($user, $temporariness->id);
        $this->put($this->url, $params, $this->headers)->assertStatus(404);
    }

    /**
     * S3ClientをMock化する
     *
     * @return \Mockery\MockInterface
     */
    private function getS3ClientMock()
    {
        $targetClass = 'App\Services\S3\S3Client';
        $mock = Mockery::mock($targetClass);
        $this->app->instance($targetClass, $mock);
        return $mock;
    }

    public function testShow2ndStep200()
    {
        // Arrange
        $user = factory(User::class)->states('client')->create();
        $temporariness = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    2,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($this->getStep2ProjectValue())
            ]
        );
        $this->setUrl($user, $temporariness->id);
        $this->setAuthHeader($user);

        // Act
        $jobTemporariness = $this->get($this->url, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(
                        Temporariness::JOB_REGISTER_ID_FORMAT,
                        2,
                        $user->id
                    ),
                    'attributes' => [
                        'step_id' => 2,
                        'name' => 'プロジェクトSTEP2',
                        'detail' => 'プロジェクト案件STEP2',
                        'job_tag_ids' => [
                            1
                        ],
                        'workable_time_id' => 1,
                        'unit_price_other' => 1,
                        'orders_per_worker_other' => 100,
                        'capacity_other' => 5,
                        'period_type' => 1,
                        'period' => Carbon::tomorrow('Asia/Tokyo')->format('Y-m-d'),
                    ]
                ]
            ]
        );
    }

    public function testShow2ndStepOldRecord200()
    {
        $user = factory(User::class)->states('client')->create();

        // Act
        // 旧下書きデータデータから再現
        $value = [
            'step_id' => 2,
            'name' => '【9月分】チェック作業費',
            'detail' => '9月分作業費です。' . "\n" . 'よろしくお願いいたします。'
        ];
        $temporariness = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    2,
                    $user->id
                ),
                'user_id' => $user->id,
                'value' => json_encode($value)
            ]
        );

        $this->setUrl($user, $temporariness->id);
        $this->setAuthHeader($user);

        $jobTemporariness = $this->get($this->url, $this->headers);
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(
                        Temporariness::JOB_REGISTER_ID_FORMAT,
                        2,
                        $user->id
                    ),
                    'attributes' => [
                        'step_id' => 2,
                        'name' => '【9月分】チェック作業費',
                        'detail' => '9月分作業費です。<br>よろしくお願いいたします。'
                    ],
                ]
            ]
        );
    }


    public function test4thStepProject200()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $workers = factory(User::class, Temporariness::JOB_PARTNER_COUNT_LIMIT)->states('worker')->create();
        $partners = [];
        foreach ($workers as $worker) {
            $partners[] = factory(Partner::class)->create(
                [
                    'outsourcer_id' => $client->id,
                    'contractor_id' => $worker->id,
                    'state' => Partner::STATE_ACCEPTED
                ]
            );
        }
        $businessSkills = factory(BusinessSkill::class, 3)->create();
        $prefectures = factory(Prefecture::class, 2)->create(
            ['name' => str_random(3)]
        );
        $businessCareer1 = factory(BusinessCareer::class)->create([
            'id' => 10001,
            'parent_id' => 100
        ]);
        $businessCareer2 = factory(BusinessCareer::class)->create([
            'id' => 10101,
            'parent_id' => 100
        ]);
        $businessCareers = [$businessCareer1, $businessCareer2];
        $environments = factory(Environment::class, 2)->states('device')->create();

        // 新規下書き STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $client->id
                ),
                'user_id' => $client->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        $this->setAuthHeader($client);

        // Act
        // Store
        $this->setUrl($client);
        $params = $this->getStep4Value();
        $params['limited_type'] = Temporariness::JOB_LIMIT_TYPE_PARTNERS;
        $params['partner_ids'] = collect($partners)->pluck('id')->all();
        $params['business_skill_ids'] = $businessSkills->pluck('id')->all();
        $params['prefecture_ids'] = $prefectures->pluck('id')->all();
        $params['business_career_ids'] = collect($businessCareers)->pluck('id')->all();
        $params['environment_ids'] = $environments->pluck('id')->all();
        $jobTemporariness = $this->post($this->url, $params, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(
                        Temporariness::JOB_REGISTER_ID_FORMAT,
                        4,
                        $client->id
                    ),
                    'attributes' => [
                        'step_id' => 4,
                        'limited_type' => Temporariness::JOB_LIMIT_TYPE_PARTNERS,
                        'partner_ids' =>  $params['partner_ids'],
                        'business_skill_ids' => $params['business_skill_ids'],
                        'prefecture_ids' => $params['prefecture_ids'],
                        'business_career_ids' => $params['business_career_ids'],
                        'environment_ids' => $params['environment_ids']
                    ]
                ]
            ]
        );

        // Act
        // Update
        $id = sprintf(
            Temporariness::JOB_REGISTER_ID_FORMAT,
            4,
            $client->id
        );
        $params = $this->getStep4Value();
        $this->setUrl($client, $id);

        $jobTemporariness = $this->put($this->url, $params, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(
                        Temporariness::JOB_REGISTER_ID_FORMAT,
                        4,
                        $client->id
                    ),
                    'attributes' => [
                        'step_id' => 4,
                        'limited_type' => Temporariness::JOB_LIMIT_TYPE_ALL
                    ]
                ]
            ]
        );

        // Act
        // Get
        $jobTemporariness = $this->get($this->url, $this->headers);
        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(
                        Temporariness::JOB_REGISTER_ID_FORMAT,
                        4,
                        $client->id
                    ),
                    'attributes' => [
                        'step_id' => 4,
                        'limited_type' => Temporariness::JOB_LIMIT_TYPE_ALL
                    ]
                ]
            ]
        );
    }

    public function test4thStepTask200()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $worker = factory(User::class)->states('worker')->create();
        $partner = factory(Partner::class)->create(
            [
                'outsourcer_id' => $client->id,
                'contractor_id' => $worker->id,
                'state' => Partner::STATE_ACCEPTED
            ]
        );
        $businessSkill = factory(BusinessSkill::class)->create();
        $prefecture = factory(Prefecture::class)->create(
            ['name' => 'hoge']
        );
        $businessCareer1 = factory(BusinessCareer::class)->create([
            'id' => 10001,
            'parent_id' => 100
        ]);
        $businessCareer2 = factory(BusinessCareer::class)->create([
            'id' => 10101,
            'parent_id' => 100
        ]);
        $environment = factory(Environment::class)->states('device')->create();

        // 新規下書き STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $client->id
                ),
                'user_id' => $client->id,
                'value' => json_encode($this->getStep1TaskValue())
            ]
        );

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // Act
        $params = $this->getStep4Value();
        $params['limited_type'] = Temporariness::JOB_LIMIT_TYPE_PARTNERS;
        $params['partner_ids'] = [$partner->id];
        $params['business_skill_ids'] = [$businessSkill->id];
        $params['prefecture_ids'] = [$prefecture->id];
        $params['business_career_ids'] = [$businessCareer1->id, $businessCareer2->id];
        $jobTemporariness = $this->post($this->url, $params, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(
                        Temporariness::JOB_REGISTER_ID_FORMAT,
                        4,
                        $client->id
                    ),
                    'attributes' => [
                        'step_id' => 4,
                        'limited_type' => Temporariness::JOB_LIMIT_TYPE_PARTNERS,
                        'partner_ids' =>  [$partner->id],
                        'prefecture_ids' => [$prefecture->id],
                        'business_career_ids' => [$businessCareer1->id, $businessCareer2->id],
                        'business_skill_ids' => [$businessSkill->id],
                    ]
                ]
            ]
        );

        // Act
        // Update
        $id = sprintf(
            Temporariness::JOB_REGISTER_ID_FORMAT,
            4,
            $client->id
        );
        $params = $this->getStep4Value();
        $this->setUrl($client, $id);

        $jobTemporariness = $this->put($this->url, $params, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(
                        Temporariness::JOB_REGISTER_ID_FORMAT,
                        4,
                        $client->id
                    ),
                    'attributes' => [
                        'step_id' => 4,
                        'limited_type' => Temporariness::JOB_LIMIT_TYPE_ALL
                    ]
                ]
            ]
        );

        // Act
        // Get
        $jobTemporariness = $this->get($this->url, $this->headers);

        // Assert
        $jobTemporariness->assertStatus(200);
        $jobTemporariness->assertJson(
            [
                'data' => [
                    'type' => self::API_TYPE,
                    'id' => sprintf(
                        Temporariness::JOB_REGISTER_ID_FORMAT,
                        4,
                        $client->id
                    ),
                    'attributes' => [
                        'step_id' => 4,
                        'limited_type' => Temporariness::JOB_LIMIT_TYPE_ALL
                    ]
                ]
            ]
        );
    }

    public function testStoreProject4thStep422()
    {
        // Arrange
        // 新規下書き
        $client = factory(User::class)->states('client')->create();

        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $client->id
                ),
                'user_id' => $client->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );
        $this->doInput422Test4thStep('post', $client);
        $this->doInput422Test4thStepProject('post', $client);
    }

    public function testStoreTask4thStep422()
    {
        // Arrange
        // 新規下書き
        $client = factory(User::class)->states('client')->create();

        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $client->id
                ),
                'user_id' => $client->id,
                'value' => json_encode($this->getStep1TaskValue())
            ]
        );
        $this->doInput422Test4thStep('post', $client);
    }

    public function testUpdateProject4thStep422()
    {
        // Arrange
        // 新規下書き
        $client = factory(User::class)->states('client')->create();

        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $client->id
                ),
                'user_id' => $client->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );

        // STEP4
        $step4 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    4,
                    $client->id
                ),
                'user_id' => $client->id,
                'value' => json_encode($this->getStep4Value())
            ]
        );
        $this->doInput422Test4thStep('put', $client, null, $step4->id);
        $this->doInput422Test4thStepProject('put', $client, null, $step4->id);

        // 差し戻し再編集
        // STEP1
        $job = factory(Job::class)->states('project', 'not_active', 're_edit')->create();
        factory(JobRole::class)->create(
            [
                'job_id' => $job->id,
                'user_id' => $client->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    1,
                    $job->id
                ),
                'user_id' => $client->id,
                'value' => json_encode($this->getStep1ProjectValue())
            ]
        );

        // Step4
        $step4 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    4,
                    $job->id
                ),
                'user_id' => $client->id,
                'value' => json_encode($this->getStep4Value())
            ]
        );
        $this->doInput422Test4thStep('put', $client, null, $step4->id);
        $this->doInput422Test4thStepProject('put', $client, $job->id, $step4->id);
    }

    public function testUpdateTask4thStep422()
    {
        // Arrange
        // 新規下書き
        $client = factory(User::class)->states('client')->create();

        // STEP1
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    1,
                    $client->id
                ),
                'user_id' => $client->id,
                'value' => json_encode($this->getStep1TaskValue())
            ]
        );

        // STEP4
        $step4 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REGISTER_ID_FORMAT,
                    4,
                    $client->id
                ),
                'user_id' => $client->id,
                'value' => json_encode($this->getStep1TaskValue())
            ]
        );
        $this->doInput422Test4thStep('put', $client, null, $step4->id);

        // 差し戻し再編集
        // STEP1
        $job = factory(Job::class)->states('task', 'not_active', 're_edit')->create();
        factory(JobRole::class)->create(
            [
                'job_id' => $job->id,
                'user_id' => $client->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    1,
                    $job->id
                ),
                'user_id' => $client->id,
                'value' => json_encode($this->getStep1TaskValue())
            ]
        );

        // Step4
        $step4 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    Temporariness::JOB_REEDIT_ID_FORMAT,
                    4,
                    $job->id
                ),
                'user_id' => $client->id,
                'value' => json_encode($this->getStep4Value())
            ]
        );
        $this->doInput422Test4thStep('put', $client, $job->id, $step4->id);
    }

    private function doInput422Test4thStep(string $method, User $client, ?int $jobId = null, ?string $id = null)
    {
        $partnerWorkers = factory(User::class, 2)->states('worker')->create();
        $partners = [];
        foreach ($partnerWorkers as $worker) {
            $partners[] = factory(Partner::class)->create(
                [
                    'outsourcer_id' => $client->id,
                    'contractor_id' => $worker->id,
                    'state' => Partner::STATE_ACCEPTED
                ]
            );
        }
        $otherWorker = factory(User::class)->states('worker')->create();
        $notPartner = factory(Partner::class)->create(
            [
                'outsourcer_id' => $client->id,
                'contractor_id' => $worker->id,
                'state' => Partner::STATE_DISSOLVED_BY_OUTSOURCER
            ]
        );
        $businessSkill = factory(BusinessSkill::class)->make();
        $prefecture = factory(Prefecture::class)->make(
            ['name' => str_random(3)]
        );
        $businessCareer = factory(BusinessCareer::class)->create([
            'id' => random_int(10000, 20000),
            'parent_id' => 100
        ]);

        $businessCareerDeleted = factory(BusinessCareer::class)->create([
            'id' => random_int(10000, 20000),
            'parent_id' => 100,
            'deleted_at' => '2018-01-01 00:00:00'
        ]);
        if ($method == 'post') {
            $this->setUrl($client);
        } else {
            $this->setUrl($client, $id);
        }
        $this->setAuthHeader($client);

        // Act & Assert
        $defaultParams = $this->getStep4Value();
        if (!empty($jobId)) {
            $defaultParam['job_id'] = $jobId;
        }
        $params = $defaultParams;

        // パートナーじゃない人のみ
        $params['partner_ids'] = [$notPartner->id];
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('partner_ids', $response->decodeResponseJson());

        // 一部パートナーでない人が含まれる
        $params['partner_ids'] = array_merge($params['partner_ids'], collect($partners)->pluck('id')->all());
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('partner_ids', $response->decodeResponseJson());

        // 招待パートナー数閾値
        $partnerWorkers = factory(User::class, Temporariness::JOB_PARTNER_COUNT_LIMIT + 1)->states('worker')->create();
        $partners = [];
        foreach ($partnerWorkers as $worker) {
            $partners[] = factory(Partner::class)->create(
                [
                    'outsourcer_id' => $client->id,
                    'contractor_id' => $worker->id,
                    'state' => Partner::STATE_ACCEPTED
                ]
            );
        }
        $params = $defaultParams;
        $params['partner_ids'] = collect($partners)->pluck('id')->all();
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('partner_ids', $response->decodeResponseJson());

        // 存在しない business_skill
        $params = $defaultParams;
        $params['business_skill_ids'] = [$businessSkill->id];
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('business_skill_ids', $response->decodeResponseJson());

        // 存在しない prefecture
        $params = $defaultParams;
        $params['prefecture_ids'] = [$prefecture->id];
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('prefecture_ids', $response->decodeResponseJson());

        // 存在しない business_careersを指定した場合
        $params = $defaultParams;
        $params['business_career_ids'] = [ $businessCareer->id + 1 ];
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);

        // 入力値が配列以外の場合
        $params = $defaultParams;
        $params['business_career_ids'] = 'notArray';
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);

        //論理削除された場合
        $params = $defaultParams;
        $params['business_career_ids'] = [$businessCareerDeleted->id];
        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
    }

    private function doInput422Test4thStepProject(string $method, User $client, ?int $jobId = null, ?string $id = null)
    {
        // プロジェクトのみのテスト
        $environment = factory(Environment::class)->make();
        if ($method == 'post') {
            $this->setUrl($client);
        } else {
            $this->setUrl($client, $id);
        }
        $this->setAuthHeader($client);

        $params = $this->getStep4Value();
        $params['environment_ids'] = [$environment->id];

        $response = $this->{$method}($this->url, $params, $this->headers);
        $response->assertStatus(422);
        $this->assertArrayHasKey('environment_ids', $response->decodeResponseJson());
    }
}
