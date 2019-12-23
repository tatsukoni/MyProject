<?php

namespace Tests\Feature\Controllers\V1\Internal\Client;

use Tests\TestCase;
use Bus;
use Carbon\Carbon;
use Mockery;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Controllers\V1\Internal\JobTemporariness;
use App\Domain\JobTemporariness\JobDetail\JobDetailService;
use App\Http\Controllers\Components\TradeState;
use App\Models\BusinessCategory;
use App\Models\BusinessCareer;
use App\Models\BusinessSkill;
use App\Models\BusinessSkillGenre;
use App\Models\DeferringFee;
use App\Models\Environment;
use App\Models\Job;
use App\Models\JobTag;
use App\Models\JobRole;
use App\Models\JobTemporarinessDoc;
use App\Models\NgWord;
use App\Models\OutsourcerRank;
use App\Models\Partner;
use App\Models\PointLog;
use App\Models\Prefecture;
use App\Models\S3Doc;
use App\Models\Task;
use App\Models\Temporariness;
use App\Models\TradeParameter;
use App\Models\User;
use App\Models\WorkableTime;
use App\Jobs\Job\Approved;
use App\Jobs\Job\InvitedAsPartner;

class JobRegistrationsControllerTest extends TestCase
{
    use DatabaseTransactions;
    use JobTemporariness; // 下書きテンプレート共有のため

    protected $url;

    private function setUrl(User $client, $jobId = null)
    {
        if (is_null($jobId)) {
            $this->url = $this->internalDomain . '/api/v1/client/' . $client->id . '/job_registrations';
        } else {
            $this->url = $this->internalDomain . '/api/v1/client/' . $client->id . '/job_registrations/' . $jobId;
        }
    }

    /**
     * PaymentServiceClient をMock化する
     *
     * @return \Mockery\MockInterface
     */
    private function setPaymentMock(?int $price = null)
    {
        $paymentServiceClientMock = Mockery::mock('alias:' . \PaymentService\Client::class);
        $paymentServiceClientMock->shouldReceive('config')->once();
        $paymentServiceMock = Mockery::mock('alias:' . \PaymentService\CreditDeposit::class);

        $creditDepositObj = new \PaymentService\CreditDeposit();
        $creditDepositObj->id = 1;
        $creditDepositObj->amount = is_null($price) ? 10 : $price;
        $paymentServiceMock->shouldReceive('create')
            ->once()
            ->andReturn($creditDepositObj);

        $creditCardMock = Mockery::mock('alias:' . \PaymentService\CreditCard::class);
        $creditCardObj = new \PaymentService\CreditCard();
        $creditCardObj->id = 1;
        $creditCardMock->shouldReceive('all')
            ->once()
            ->andReturn(collect([$creditCardObj]));
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

    /**
     * JobDetailServiceをMock化する
     *
     * @return \Mockery\MockInterface
     */
    private function getJobDetailServiceMock()
    {
        $targetClass = JobDetailService::class;
        $mock = Mockery::mock($targetClass);
        $this->app->instance($targetClass, $mock);
        return $mock;
    }

    /**
     * 自動承認になるようにユーザーランク「下」に変更
     * @param User $client
     * @return void
     */
    private function createAutoApproveUserRecord(User $client): void
    {
        $client->outsourcer_rank_id = OutsourcerRank::ID_AUTO_APPROVE;
        $client->save();
    }

    // パラメータを最小限でテスト
    public function testProjectPostMustOnly200()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );
        $jobTags = factory(JobTag::class, 2)->create();
        $workableTime = factory(WorkableTime::class)->create();
        $ngWord = factory(NgWord::class, 2)->create();

        // 下書きデータ
        $idFormat = Temporariness::JOB_REGISTER_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1ProjectValue();
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $client->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2ProjectValue();
        unset($step2Value['job_tag_ids']);
        $step2Value['workable_time_id'] = $workableTime->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $client->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );
        // STEP3
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $client->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );

        // STEP4
        $step4Value = $this->getStep4Value();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $client->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // Act & Assert
        // 最低限のパラメータを指定して登録
        $response = $this->post($this->url, [], $this->headers);
        $response->assertStatus(200);
        $response->assertJson(
            [
                'data' => [
                    'attributes' => [
                        'name' => $step2Value['name'],
                        'type' => $step1Value['type'],
                        'detail' => $step2Value['detail'],
                        'recruiting' => false,
                        'activated' => false,
                        're_edit' => false,
                        'rejected' => false,
                        'closed' => false,
                        'limited_type_id' => Job::LIMIT_TYPE_PUBLIC,
                        'wall_id' => null,
                        'client_id' => $client->id,
                        'client_name' => $client->username,
                        'client_thumbnail' => $client->thumbnail_url,
                        's3_docs' => [],
                        'job_tags' => [],
                        'business_categories' => [
                            [
                                'id' => $businessCategoryParent->id,
                                'name' => $businessCategoryParent->name,
                                'link' => $businessCategoryParent->parent_name,
                                'child_categories' => [
                                    [
                                        'id' => $businessCategoryChild->id,
                                        'name' => $businessCategoryChild->name,
                                        'link' => $businessCategoryChild->parent_name
                                    ]
                                ]
                            ]
                        ],
                        'business_skills' => [],
                        'prefectures' => [],
                        'business_careers' => [],
                        'unit_price' => $step2Value['unit_price_other'],
                        'recruitment_count' => $step2Value['capacity_other'] . '名',
                        'period' => 'あと1日',
                        'scheduled_reward' => $step2Value['orders_per_worker_other'],
                        'workable_time' => $workableTime->workable_time
                    ]
                ]
            ]
        );

        $this->assertDatabaseHas(
            'job_roles',
            [
                'user_id' => $client->id,
                'role_id' => JobRole::OUTSOURCER,
                'active' => true
            ]
        );
    }

    /**
     * 色々指定してテスト
     *
     * @dataProvider provideParameters
     * @param bool $isSpecialCategory
     * @param array $getRecordReturn
     */
    public function testProjectPost200(bool $isSpecialCategory, array $getRecordReturn)
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $workers = factory(User::class, 3)->states('worker')->create();
        // 一部をパートナーにする
        $partners = [];
        for ($index = 0; $index < 2; $index++) {
            $partners[] = factory(Partner::class)->create(
                [
                    'outsourcer_id' => $client->id,
                    'contractor_id' => $workers[$index]->id,
                ]
            );
        }
        $notCurrentPartner = factory(Partner::class)->create(
            [
                'outsourcer_id' => $client->id,
                'contractor_id' => $workers[2]->id,
                'state' => Partner::STATE_DISSOLVED_BY_CONTRACTOR
            ]
        );

        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );
        $jobTags = factory(JobTag::class, 2)->create();
        $environments = factory(Environment::class, 2)->create();
        $businessSkillGenre = factory(BusinessSkillGenre::class)->create();
        $businessSkills = factory(BusinessSkill::class, 2)->create([
            'business_skill_genre_id' => $businessSkillGenre->id
        ]);
        $prefectures = factory(Prefecture::class, 2)->create();
        $businessCareerParent = factory(BusinessCareer::class)->create(['id' => 10000]);
        $businessCareer1 = factory(BusinessCareer::class)->create([
            'id' => 10001,
            'parent_id' => $businessCareerParent->id
        ]);
        $businessCareer2 = factory(BusinessCareer::class)->create([
            'id' => 10101,
            'parent_id' => $businessCareerParent->id
        ]);
        $businessCareers = [$businessCareer1, $businessCareer2];
        $businessSkillGenre = factory(BusinessSkillGenre::class)->create();
        $businessSkills = factory(BusinessSkill::class, 2)->create([
            'business_skill_genre_id' => $businessSkillGenre->id
        ]);
        $ngWord = factory(NgWord::class, 2)->create();

        // 下書きデータ
        $idFormat = Temporariness::JOB_REGISTER_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1ProjectValue();
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $client->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2ProjectValue();
        $step2Value['job_tag_ids'] = $jobTags->pluck('id')->all();
        $step2Value['period_type'] = Temporariness::PROJECT_PERIOD_TYPE_FIX_DATE;
        $step2Value['period'] = Carbon::tomorrow('Asia/Tokyo')->format('Y-m-d');
        $step2 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $client->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );

        if (!$isSpecialCategory) {
            // ライティング、商品登録以外のカテゴリの場合
            $workableTime = factory(WorkableTime::class)->create();
            $getRecordReturn = [
                'tradeParameter' => [
                    'workable_time_id' => $workableTime->id
                ]
            ];
        }

        $jobDetailServiceMock = $this->getJobDetailServiceMock();
        $jobDetailServiceMock->shouldReceive('getRecord')->times(2)->andReturn($getRecordReturn);
        $jobDetailServiceMock->shouldReceive('getJobDetails')->once()->andReturn([]);

        // STEP3
        $step3 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $client->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );

        // 添付
        // S3ClientのMock化
        $s3ClientMock = $this->getS3ClientMock();
        $s3ClientMock->shouldReceive('storeS3Object')->times(2)->andReturn(true);
        $s3ClientMock->shouldReceive('getS3ObjectUrlByPath')->times(2)->andReturn('http://hoge/fuga');
        $jobTemporarinessDocs = [];
        for ($fileIndex = 0; $fileIndex < 2; $fileIndex++) {
            $path = 'hoge/fuga';
            $name = "file{$fileIndex}.txt";
            $jobTemporarinessDocs[] = factory(JobTemporarinessDoc::class)->create(
                [
                    'temporariness_id' => $step3->id,
                    's3_path' => $path,
                    'filename' => $name
                ]
            );
            $s3ClientMock->storeS3Object($path, $name, UploadedFile::fake()->create($name), false);
        }

        // STEP4
        $step4Value = $this->getStep4Value();
        $step4Value['limited_type'] = Temporariness::JOB_LIMIT_TYPE_PARTNERS;
        $step4Value['partner_ids'] = array_merge([$notCurrentPartner->id], collect($partners)->pluck('id')->all());
        $step4Value['prefecture_ids'] = $prefectures->pluck('id')->all();
        $step4Value['business_career_ids'] = collect($businessCareers)->pluck('id')->all();
        $step4Value['business_skill_ids'] = $businessSkills->pluck('id')->all();
        $step4Value['environment_ids'] = $environments->pluck('id')->all();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $client->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // Act & Assert
        $response = $this->post($this->url, [], $this->headers);
        $response->assertStatus(200);

        $response->assertJson(
            [
                'data' => [
                    'attributes' => [
                        'name' => $step2Value['name'],
                        'type' => $step1Value['type'],
                        'detail' => $step2Value['detail'],
                        'recruiting' => false,
                        'activated' => false,
                        're_edit' => false,
                        'rejected' => false,
                        'closed' => false,
                        'limited_type_id' => Job::LIMIT_TYPE_PARTNERS,
                        'wall_id' => null,
                        'client_id' => $client->id,
                        'client_name' => $client->username,
                        'client_thumbnail' => $client->thumbnail_url,
                        // 's3_docs' => [] // 別でテストする
                        'job_tags' => [
                            [
                                'id' => $jobTags[0]->id,
                                'name' => $jobTags[0]->name,
                                'link' => $jobTags[0]->search_name
                            ],
                            [
                                'id' => $jobTags[1]->id,
                                'name' => $jobTags[1]->name,
                                'link' => $jobTags[1]->search_name
                            ],
                        ],
                        'business_categories' => [
                            [
                                'id' => $businessCategoryParent->id,
                                'name' => $businessCategoryParent->name,
                                'link' => $businessCategoryParent->parent_name,
                                'child_categories' => [
                                    [
                                        'id' => $businessCategoryChild->id,
                                        'name' => $businessCategoryChild->name,
                                        'link' => $businessCategoryChild->parent_name
                                    ]
                                ]
                            ]
                        ],
                        'business_skills' => [
                            [
                                'id' => $businessSkillGenre->id,
                                'name' => $businessSkillGenre->name,
                                'businessSkills' => [
                                    [
                                        'id' => $businessSkills[0]->id,
                                        'name' => $businessSkills[0]->name,
                                    ],
                                    [
                                        'id' => $businessSkills[1]->id,
                                        'name' => $businessSkills[1]->name,
                                    ]
                                ]
                            ]
                        ],
                        'prefectures' => [
                            [
                                'id' => $prefectures[0]->id,
                                'name' => $prefectures[0]->name,
                                'area_id' => $prefectures[0]->area_id,
                            ],
                            [
                                'id' => $prefectures[1]->id,
                                'name' => $prefectures[1]->name,
                                'area_id' => $prefectures[1]->area_id,
                            ]
                        ],
                        'business_careers' => [
                            [
                                'id' => $businessCareerParent->id,
                                'name' => $businessCareerParent->name,
                                'child_careers' => [
                                    [
                                        'id' => $businessCareers[0]->id,
                                        'name' => $businessCareers[0]->name,
                                    ],
                                    [
                                        'id' => $businessCareers[1]->id,
                                        'name' => $businessCareers[1]->name,
                                    ]
                                ]
                            ]
                        ],
                        'environments' => [
                            [
                                'id' => $environments[0]->id,
                                'name' => $environments[0]->name
                            ],
                            [
                                'id' => $environments[1]->id,
                                'name' => $environments[1]->name
                            ]
                        ],
                        'unit_price' => $step2Value['unit_price_other'],
                        'recruitment_count' => $step2Value['capacity_other'] . '名',
                        'period' => 'あと1日',
                        'scheduled_reward' => $step2Value['orders_per_worker_other'],
                    ]
                ]
            ]
        );

        // 添付
        foreach ($jobTemporarinessDocs as $doc) {
            // job_temporariness_docs が消えている
            $this->assertDatabaseMissing(
                'job_temporariness_docs',
                [
                    'temporariness_id' => $step2->id,
                    'filename' => $doc->filename
                ]
            );
            // s3_docs がある
            $this->assertDatabasehas(
                's3_docs',
                [
                    'model' => Job::S3_PATH_PROJECT,
                    'filename' => $doc->filename
                ]
            );
        }
    }

    public function provideParameters()
    {
        // 商品登録、ライティングで共通
        $commonParams = [
            'job' => [
                'teachme' => [
                    '1週間に何記事書けるか',
                    'これまでのライティングの経験',
                ],
                'recommend' => [
                    '自分のペースで仕事したい方',
                    'スキルアップしたい方',
                ],
                'prohibitions' => [
                    '他のサイトからのコピーや転載',
                    '公序良俗に違反するような表現',
                ],
                'pr_message' => '自分もショップを立ち上げたばかりの未熟者ですが、お客様に喜んでいただけるようなショップを一緒に作っていきましょう'
            ]
        ];

        return [
            // 商品登録、ライティング以外
            [
                false,
                []
            ],

            // ライティング
            [
                true,
                array_merge([
                    'jobDetailWritingItem' => [
                        'theme' => 2,
                        'theme_other' => null,
                        'character_count' => 1000,
                        'end_of_sentence' => 1,
                        'assumed_readers' => '美容に興味のある30代の女性',
                        'article_count' => 5,
                        'article_count_period' => 1,
                    ]
                ], $commonParams)
            ],

            // 商品登録
            [
                true,
                array_merge([
                    'jobDetailRegisterItem' => [
                        'has_trial' => 1,
                        'trial' => 'トライアル期間または件数: 10件
報酬の有無: 無し',
                        'has_image_creation' => 1,
                        'image_creation' => '素材の提供有無: 有り
1件あたりの画像数: 5',
                        'has_description_creation' => 1,
                        'description_creation' => 'リサーチした海外サイトの商品説明を全角100文字以内でリライトしていただきます。
詳しくは動画のマニュアルをご覧ください。',
                        'manual' => '今回行なっていただく作業を一通り網羅した動画マニュアルを用意しています。',
                    ]
                ], $commonParams)
            ]
        ];
    }

    /**
     * パラメータを最小限でテスト
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testTaskPostMustOnly200()
    {
        // Arrange
        Mail::fake();
        $client = factory(User::class)->states('client', 'prepaid')->create();
        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );

        // 下書きデータ
        $idFormat = Temporariness::JOB_REGISTER_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1TaskValue();
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $client->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2TaskValue();
        unset($step2Value['job_tag_ids']);
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $client->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );
        // STEP3
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $client->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );
        // STEP4
        $step4Value = $this->getStep4Value();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $client->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // Act & Assert
        // 最低限のパラメータを指定して登録
        $response = $this->post($this->url, [], $this->headers);
        $response->assertStatus(200);
        $response->assertJson(
            [
                'data' => [
                    'attributes' => [
                        'name' => $step2Value['name'],
                        'type' => $step1Value['type'],
                        'detail' => $step2Value['detail'],
                        'recruiting' => false,
                        'activated' => false,
                        're_edit' => false,
                        'rejected' => false,
                        'closed' => false,
                        'limited_type_id' => Job::LIMIT_TYPE_PUBLIC,
                        'wall_id' => null,
                        'client_id' => $client->id,
                        'client_name' => $client->username,
                        'client_thumbnail' => $client->thumbnail_url,
                        's3_docs' => [],
                        'job_tags' => [],
                        'business_categories' => [
                            [
                                'id' => $businessCategoryParent->id,
                                'name' => $businessCategoryParent->name,
                                'link' => $businessCategoryParent->parent_name,
                                'child_categories' => [
                                    [
                                        'id' => $businessCategoryChild->id,
                                        'name' => $businessCategoryChild->name,
                                        'link' => $businessCategoryChild->parent_name
                                    ]
                                ]
                            ]
                        ],
                        'business_skills' => [],
                        'prefectures' => [],
                        'business_careers' => [],
                        'unit_price' => $step2Value['unit_price'],
                        'recruitment_count' => '0/' . $step2Value['quantity'],
                        'task' => [
                            'type' => 1, // 通常のタスク
                            'sagooo_link' => null,
                            'sagooo_published' => null,
                            'sagooo_example' => null,
                            'conditions' => [
                                [
                                    'item_name' => '質問数',
                                    'txt_list' => '2問'
                                ],
                                [
                                    'item_name' => 'うち必須回答',
                                    'txt_list' => '1問'
                                ],
                                [
                                    'item_name' => '回答条件',
                                    'txt_list' => [
                                        [
                                            'txt' => '質問2',
                                            'detail' => [
                                                '10文字以上 1,000文字以下',
                                                sprintf(
                                                    '「%s」を%d回以上利用',
                                                    $step2Value['questions'][1]['keywords'][0]['keyword'],
                                                    number_format($step2Value['questions'][1]['keywords'][0]['repeat_count'])
                                                ),
                                                sprintf(
                                                    '「%s」を%d回以上利用',
                                                    $step2Value['questions'][1]['keywords'][1]['keyword'],
                                                    $step2Value['questions'][1]['keywords'][1]['repeat_count']
                                                )
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'unimedia_task' => []
                    ]
                ]
            ]
        );
        Mail::assertNotQueued(\App\Mail\Mails\Deposit\CaughtDeposit::class);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testTaskPost200()
    {
        // Arrange
        Mail::fake();
        $client = factory(User::class)->states('client', 'prepaid')->create();
        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );
        $businessSkillGenre = factory(BusinessSkillGenre::class)->create();
        $businessSkills = factory(BusinessSkill::class, 2)->create([
            'business_skill_genre_id' => $businessSkillGenre->id
        ]);
        $jobTag = factory(JobTag::class)->create();

        // 下書きデータ
        $idFormat = Temporariness::JOB_REGISTER_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1TaskValue();
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $client->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2TaskValue();
        $step2Value['job_tag_ids'] = [$jobTag->id];
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $client->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );
        // STEP3
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $client->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );
        // STEP4
        $step4Value = $this->getStep4Value();
        $step4Value['max_delivery_type'] = Temporariness::TASK_DELIVERY_LIMIT_TYPE_FIX_COUNT;
        $step4Value['max_delivery'] = 10;
        $step4Value['business_skill_ids'] = $businessSkills->pluck('id')->all();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $client->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // Act & Assert
        $response = $this->post($this->url, [], $this->headers);
        $response->assertStatus(200);
        $response->assertJson(
            [
                'data' => [
                    'attributes' => [
                        'name' => $step2Value['name'],
                        'type' => $step1Value['type'],
                        'detail' => $step2Value['detail'],
                        'recruiting' => false,
                        'activated' => false,
                        're_edit' => false,
                        'rejected' => false,
                        'closed' => false,
                        'limited_type_id' => Job::LIMIT_TYPE_PUBLIC,
                        'wall_id' => null,
                        'client_id' => $client->id,
                        'client_name' => $client->username,
                        'client_thumbnail' => $client->thumbnail_url,
                        's3_docs' => [],
                        'job_tags' => [],
                        'business_categories' => [
                            [
                                'id' => $businessCategoryParent->id,
                                'name' => $businessCategoryParent->name,
                                'link' => $businessCategoryParent->parent_name,
                                'child_categories' => [
                                    [
                                        'id' => $businessCategoryChild->id,
                                        'name' => $businessCategoryChild->name,
                                        'link' => $businessCategoryChild->parent_name
                                    ]
                                ]
                            ]
                        ],
                        'business_skills' => [
                            [
                                'id' => $businessSkillGenre->id,
                                'name' => $businessSkillGenre->name,
                                'businessSkills' => [
                                    [
                                        'id' => $businessSkills[0]->id,
                                        'name' => $businessSkills[0]->name,
                                    ],
                                    [
                                        'id' => $businessSkills[1]->id,
                                        'name' => $businessSkills[1]->name,
                                    ]
                                ]
                            ]
                        ],
                        'prefectures' => [],
                        'business_careers' => [],
                        'unit_price' => $step2Value['unit_price'],
                        'recruitment_count' => '0/' . $step2Value['quantity'],
                        'task' => [
                            'type' => 1, // 通常のタスク
                            'sagooo_link' => null,
                            'sagooo_published' => null,
                            'sagooo_example' => null,
                            'conditions' => [
                                [
                                    'item_name' => '質問数',
                                    'txt_list' => '2問'
                                ],
                                [
                                    'item_name' => 'うち必須回答',
                                    'txt_list' => '1問'
                                ],
                                [
                                    'item_name' => '回答条件',
                                    'txt_list' => [
                                        [
                                            'txt' => '質問2',
                                            'detail' => [
                                                '10文字以上 1,000文字以下',
                                                sprintf(
                                                    '「%s」を%d回以上利用',
                                                    $step2Value['questions'][1]['keywords'][0]['keyword'],
                                                    number_format($step2Value['questions'][1]['keywords'][0]['repeat_count'])
                                                ),
                                                sprintf(
                                                    '「%s」を%d回以上利用',
                                                    $step2Value['questions'][1]['keywords'][1]['keyword'],
                                                    $step2Value['questions'][1]['keywords'][1]['repeat_count']
                                                )
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'unimedia_task' => []
                    ]
                ]
            ]
        );
        Mail::assertNotQueued(\App\Mail\Mails\Deposit\CaughtDeposit::class);
    }

    /**
     * タスクの仕事を更新する際に共通化できるデータを作成
     * 
     * @param string $isDefaultTaskType = true
     */
    public function createTaskPut200Data(bool $isDefaultTaskType = true)
    {
        $client = factory(User::class)->states('client')->create();

        $s3ClientMock = $this->getS3ClientMock();
        $s3ClientMock->shouldReceive('storeS3Object')->times(2)->andReturn(true);
        $s3ClientMock->shouldReceive('getS3ObjectUrlByPath')->times(1)->andReturn('http://hoge/fuga');
        $s3ClientMock->shouldReceive('deleteS3Object')->times(1)->andReturn(true);

        // 仕事
        if ($isDefaultTaskType) {
            $job = factory(Job::class)->states('task', 'not_active', 're_edit')->create();
            $defaultJobTypeTable = factory(Task::class)->create(
                ['job_id' => $job->id]
            );
            $s3DocModel = Job::S3_PATH_TASK;
            $s3DocForeignKey = $defaultJobTypeTable->id;
        } else {
            $job = factory(Job::class)->states('project', 'not_active', 're_edit')->create();
            $defaultJobTypeTable = factory(TradeParameter::class)->create(
                ['job_id' => $job->id]
            );
            $s3DocModel = Job::S3_PATH_PROJECT;
            $s3DocForeignKey = $defaultJobTypeTable->id;
        }

        factory(JobRole::class)->create(
            [
                'user_id' => $client->id,
                'job_id' => $job->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        $path = 'hoge/fuga';
        $name = "file_old.txt";
        factory(S3Doc::class)->create(
            [
                's3_path' => $path,
                'filename' => $name,
                'model' => $s3DocModel,
                'foreign_key' => $s3DocForeignKey
            ]
        );
        $s3ClientMock->storeS3Object($path, $name, UploadedFile::fake()->create($name), false);

        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );
        $businessSkillGenre = factory(BusinessSkillGenre::class)->create();
        $businessSkills = factory(BusinessSkill::class, 2)->create([
            'business_skill_genre_id' => $businessSkillGenre->id
        ]);

        // 下書きデータ
        $idFormat = Temporariness::JOB_REEDIT_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1TaskValue();
        $step1Value['job_id'] = $job->id;
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $job->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2TaskValue();
        $step2Value['job_id'] = $job->id;
        $step2Value['job_tag_ids'] = [];

        $step2 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $job->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );

        // STEP3
        $step3 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $job->id
                ),
                'value' => json_encode(['step_id' => 3, 'job_id' => $job->id]),
                'user_id' => $client->id
            ]
        );
        $path = 'foo';
        $name = "file_new.png";
        factory(JobTemporarinessDoc::class)->create(
            [
                's3_path' => $path,
                'filename' => $name,
                'temporariness_id' => $step3->id
            ]
        );
        $s3ClientMock->storeS3Object($path, $name, UploadedFile::fake()->create($name), false);

        // STEP4
        $step4Value = $this->getStep4Value();
        $step4Value['job_id'] = $job->id;
        $step4Value['business_skill_ids'] = $businessSkills->pluck('id')->all();

        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $job->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        return compact(
            'client',
            'job',
            'defaultJobTypeTable',
            'businessCategoryParent',
            'businessCategoryChild',
            'businessSkillGenre',
            'businessSkills',
            'step2',
            'step1Value',
            'step2Value',
            'step4Value'
        );
    }

    public function testTaskPut200()
    {
        // Arrange
        $testingData = $this->createTaskPut200Data();

        $this->setUrl($testingData['client'], $testingData['job']->id);
        $this->setAuthHeader($testingData['client']);

        // Act & Assert
        $response = $this->put($this->url, [], $this->headers);
        $response->assertStatus(200);
        $response->assertJson(
            [
                'data' => [
                    'attributes' => [
                        'name' => $testingData['step2Value']['name'],
                        'type' => $testingData['step1Value']['type'],
                        'detail' => $testingData['step2Value']['detail'],
                        'recruiting' => false,
                        'activated' => false,
                        're_edit' => false,
                        'rejected' => false,
                        'closed' => false,
                        'limited_type_id' => Job::LIMIT_TYPE_PUBLIC,
                        'wall_id' => null,
                        'client_id' => $testingData['client']->id,
                        'client_name' => $testingData['client']->username,
                        'client_thumbnail' => $testingData['client']->thumbnail_url,
                        's3_docs' => [],
                        'job_tags' => [],
                        'business_categories' => [
                            [
                                'id' => $testingData['businessCategoryParent']->id,
                                'name' => $testingData['businessCategoryParent']->name,
                                'link' => $testingData['businessCategoryParent']->parent_name,
                                'child_categories' => [
                                    [
                                        'id' => $testingData['businessCategoryChild']->id,
                                        'name' => $testingData['businessCategoryChild']->name,
                                        'link' => $testingData['businessCategoryChild']->parent_name
                                    ]
                                ]
                            ]
                        ],
                        'business_skills' => [
                            [
                                'id' => $testingData['businessSkillGenre']->id,
                                'name' => $testingData['businessSkillGenre']->name,
                                'businessSkills' => [
                                    [
                                        'id' => $testingData['businessSkills'][0]->id,
                                        'name' => $testingData['businessSkills'][0]->name,
                                    ],
                                    [
                                        'id' => $testingData['businessSkills'][1]->id,
                                        'name' => $testingData['businessSkills'][1]->name,
                                    ]
                                ]
                            ]
                        ],
                        'prefectures' => [],
                        'business_careers' => [],
                        'unit_price' => $testingData['step2Value']['unit_price'],
                        'recruitment_count' => '0/' . $testingData['step2Value']['quantity'],
                        'task' => [
                            'type' => 1, // 通常のタスク
                            'sagooo_link' => null,
                            'sagooo_published' => null,
                            'sagooo_example' => null,
                            'conditions' => [
                                [
                                    'item_name' => '質問数',
                                    'txt_list' => '2問'
                                ],
                                [
                                    'item_name' => 'うち必須回答',
                                    'txt_list' => '1問'
                                ],
                                [
                                    'item_name' => '回答条件',
                                    'txt_list' => [
                                        [
                                            'txt' => '質問2',
                                            'detail' => [
                                                '10文字以上 1,000文字以下',
                                                sprintf(
                                                    '「%s」を%d回以上利用',
                                                    $testingData['step2Value']['questions'][1]['keywords'][0]['keyword'],
                                                    number_format($testingData['step2Value']['questions'][1]['keywords'][0]['repeat_count'])
                                                ),
                                                sprintf(
                                                    '「%s」を%d回以上利用',
                                                    $testingData['step2Value']['questions'][1]['keywords'][1]['keyword'],
                                                    $testingData['step2Value']['questions'][1]['keywords'][1]['repeat_count']
                                                )
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'unimedia_task' => []
                    ]
                ]
            ]
        );

        // 編集前の添付ファイルが削除されている
        $name = "file_old.txt";
        $this->assertDatabaseMissing(
            's3_docs',
            [
                'model' => Job::S3_PATH_TASK,
                'filename' => 'file_old.txt'
            ]
        );

        // job_temporariness_docs が消えている
        $this->assertDatabaseMissing(
            'job_temporariness_docs',
            [
                'temporariness_id' => $testingData['step2']->id,
                'filename' => 'file_old.txt'
            ]
        );
        // s3_docs がある
        $this->assertDatabasehas(
            's3_docs',
            [
                'model' => Job::S3_PATH_TASK,
                'filename' => 'file_new.png'
            ]
        );
    }

    // 差し戻し編集時、仕事タイプをプロジェクトからタスクに変更した場合
    public function testPut200ProjectToTaks()
    {
        // Arrange
        $testingData = $this->createTaskPut200Data(false);

        $this->setUrl($client, $job->id);
        $this->setAuthHeader($client);

        // Act & Assert
        $response = $this->put($this->url, [], $this->headers);
        $response->assertStatus(200);
        $response->assertJson(
            [
                'data' => [
                    'attributes' => [
                        'name' => $testingData['step2Value']['name'],
                        'type' => $testingData['step1Value']['type'],
                        'detail' => $testingData['step2Value']['detail'],
                        'recruiting' => false,
                        'activated' => false,
                        're_edit' => false,
                        'rejected' => false,
                        'closed' => false,
                        'limited_type_id' => Job::LIMIT_TYPE_PUBLIC,
                        'wall_id' => null,
                        'client_id' => $testingData['client']->id,
                        'client_name' => $testingData['client']->username,
                        'client_thumbnail' => $testingData['client']->thumbnail_url,
                        's3_docs' => [],
                        'job_tags' => [],
                        'business_categories' => [
                            [
                                'id' => $testingData['businessCategoryParent']->id,
                                'name' => $testingData['businessCategoryParent']->name,
                                'link' => $testingData['businessCategoryParent']->parent_name,
                                'child_categories' => [
                                    [
                                        'id' => $testingData['businessCategoryChild']->id,
                                        'name' => $testingData['businessCategoryChild']->name,
                                        'link' => $testingData['businessCategoryChild']->parent_name
                                    ]
                                ]
                            ]
                        ],
                        'business_skills' => [
                            [
                                'id' => $testingData['businessSkillGenre']->id,
                                'name' => $testingData['businessSkillGenre']->name,
                                'businessSkills' => [
                                    [
                                        'id' => $testingData['businessSkills'][0]->id,
                                        'name' => $testingData['businessSkills'][0]->name,
                                    ],
                                    [
                                        'id' => $testingData['businessSkills'][1]->id,
                                        'name' => $testingData['businessSkills'][1]->name,
                                    ]
                                ]
                            ]
                        ],
                        'prefectures' => [],
                        'business_careers' => [],
                        'unit_price' => $testingData['step2Value']['unit_price'],
                        'recruitment_count' => '0/' . $testingData['step2Value']['quantity'],
                        'task' => [
                            'type' => 1, // 通常のタスク
                            'sagooo_link' => null,
                            'sagooo_published' => null,
                            'sagooo_example' => null,
                            'conditions' => [
                                [
                                    'item_name' => '質問数',
                                    'txt_list' => '2問'
                                ],
                                [
                                    'item_name' => 'うち必須回答',
                                    'txt_list' => '1問'
                                ],
                                [
                                    'item_name' => '回答条件',
                                    'txt_list' => [
                                        [
                                            'txt' => '質問2',
                                            'detail' => [
                                                '10文字以上 1,000文字以下',
                                                sprintf(
                                                    '「%s」を%d回以上利用',
                                                    $testingData['step2Value']['questions'][1]['keywords'][0]['keyword'],
                                                    number_format($testingData['step2Value']['questions'][1]['keywords'][0]['repeat_count'])
                                                ),
                                                sprintf(
                                                    '「%s」を%d回以上利用',
                                                    $testingData['step2Value']['questions'][1]['keywords'][1]['keyword'],
                                                    $testingData['step2Value']['questions'][1]['keywords'][1]['repeat_count']
                                                )
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'unimedia_task' => []
                    ]
                ]
            ]
        );

        // trade_parametersが消えている
        $this->assertDatabaseMissing(
            'trade_parameters',
            [
                'id' => $testingData['defaultJobTypeTable']->id,
                'job_id' => $job->id
            ]
        );

        // tasksが作成されている
        $this->assertDatabasehas(
            'tasks',
            [
                'job_id' => $testingData['job']->id,
                'unit_price' => $testingData['step2Value']['unit_price'],
                'quantity' => $testingData['step2Value']['quantity']
            ]
        );

        // jobsの仕事タイプが更新されている
        $this->assertDatabasehas(
            'jobs',
            [
                'id' => $testingData['job']->id,
                'type' => Job::TYPE_TASK
            ]
        );

        // 編集前の添付ファイルが削除されている
        $name = "file_old.txt";
        $this->assertDatabaseMissing(
            's3_docs',
            [
                'model' => Job::S3_PATH_PROJECT,
                'filename' => 'file_old.txt'
            ]
        );

        // job_temporariness_docs が消えている
        $this->assertDatabaseMissing(
            'job_temporariness_docs',
            [
                'temporariness_id' => $testingData['step2']->id,
                'filename' => 'file_old.txt'
            ]
        );
        // s3_docs がある
        $this->assertDatabasehas(
            's3_docs',
            [
                'model' => Job::S3_PATH_TASK,
                'filename' => 'file_new.png'
            ]
        );
    }

    // 仕事カテゴリーを変更した場合のテスト

    public function testStore404()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );
        $workableTime = factory(WorkableTime::class)->create();
        $idFormat = Temporariness::JOB_REGISTER_ID_FORMAT;

        // STEP1
        $step1Value = $this->getStep1ProjectValue();
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        $step1Value['job_id'] = 1;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $client->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2ProjectValue();
        unset($step2Value['job_tag_ids']);
        $step2Value['workable_time_id'] = $workableTime->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $client->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );
        // STEP3
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $client->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );
        // STEP4
        $step4Value = $this->getStep4Value();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $client->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // Act & Assert
        $this->post($this->url, [], $this->headers)->assertStatus(404);
    }

    public function testProjectPut200()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $workers = factory(User::class, 3)->states('worker')->create();

        // S3ClientのMock化
        $s3ClientMock = $this->getS3ClientMock();
        $s3ClientMock->shouldReceive('storeS3Object')->times(2)->andReturn(true);
        $s3ClientMock->shouldReceive('getS3ObjectUrlByPath')->times(2)->andReturn('http://hoge');
        $s3ClientMock->shouldReceive('deleteS3Object')->times(2)->andReturn(true);

        // 仕事
        $job = factory(Job::class)->states('project', 'not_active', 're_edit')->create();
        factory(JobRole::class)->create(
            [
                'user_id' => $client->id,
                'job_id' => $job->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        $tradeParameter = factory(TradeParameter::class)->create(['job_id' => $job->id]);
        // 修正前の仕事への添付
        for ($fileIndex = 0; $fileIndex < 2; $fileIndex++) {
            $path = 'hoge';
            $name = "file_old{$fileIndex}.txt";
            factory(S3Doc::class)->create(
                [
                    'model' => Job::S3_PATH_PROJECT,
                    'foreign_key' => $tradeParameter->id,
                    's3_path' => $path,
                    'filename' => $name
                ]
            );
        }

        // 一部をパートナーにする
        $partners = [];
        for ($index = 0; $index < 2; $index++) {
            $partners[] = factory(Partner::class)->create(
                [
                    'outsourcer_id' => $client->id,
                    'contractor_id' => $workers[$index]->id,
                ]
            );
        }
        $notCurrentPartner = factory(Partner::class)->create(
            [
                'outsourcer_id' => $client->id,
                'contractor_id' => $workers[2]->id,
                'state' => Partner::STATE_DISSOLVED_BY_CONTRACTOR
            ]
        );

        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );
        $jobTags = factory(JobTag::class, 2)->create();
        $workableTime = factory(WorkableTime::class)->create();
        $environments = factory(Environment::class, 2)->create();
        $businessSkillGenre = factory(BusinessSkillGenre::class)->create();
        $businessSkills = factory(BusinessSkill::class, 2)->create([
            'business_skill_genre_id' => $businessSkillGenre->id
        ]);
        $prefectures = factory(Prefecture::class, 2)->create();
        $businessCareerParent = factory(BusinessCareer::class)->create(['id' => 10000]);
        $businessCareer1 = factory(BusinessCareer::class)->create([
            'id' => 10001,
            'parent_id' => $businessCareerParent->id
        ]);
        $businessCareer2 = factory(BusinessCareer::class)->create([
            'id' => 10101,
            'parent_id' => $businessCareerParent->id
        ]);
        $businessCareers = [$businessCareer1, $businessCareer2];
        $ngWord = factory(NgWord::class, 2)->create();


        // 下書きデータ
        $idFormat = Temporariness::JOB_REEDIT_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1ProjectValue();
        $step1Value['job_id'] = $job->id;
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $job->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2ProjectValue();
        $step2Value['job_id'] = $job->id;
        $step2Value['workable_time_id'] = $workableTime->id;
        $step2Value['job_tag_ids'] = $jobTags->pluck('id')->all();
        $step2Value['period_type'] = Temporariness::PROJECT_PERIOD_TYPE_FIX_DATE;
        $step2Value['period'] = Carbon::tomorrow('Asia/Tokyo')->format('Y-m-d');
        $step2 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $job->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );

        // STEP3
        $step3 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $job->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );
        // 添付
        $jobTemporarinessDocs = [];
        for ($fileIndex = 0; $fileIndex < 2; $fileIndex++) {
            $path = 'hoge/fuga';
            $name = "file{$fileIndex}.txt";
            $jobTemporarinessDocs[] = factory(JobTemporarinessDoc::class)->create(
                [
                    'temporariness_id' => $step3->id,
                    's3_path' => $path,
                    'filename' => $name
                ]
            );
            $s3ClientMock->storeS3Object($path, $name, UploadedFile::fake()->create($name), false);
        }

        // STEP4
        $step4Value = $this->getStep4Value();
        $step4Value['job_id'] = $job->id;
        $step4Value['limited_type'] = Temporariness::JOB_LIMIT_TYPE_PARTNERS;
        $step4Value['partner_ids'] = array_merge([$notCurrentPartner->id], collect($partners)->pluck('id')->all());
        $step4Value['prefecture_ids'] = $prefectures->pluck('id')->all();
        $step4Value['business_career_ids'] = collect($businessCareers)->pluck('id')->all();
        $step4Value['business_skill_ids'] = $businessSkills->pluck('id')->all();
        $step4Value['environment_ids'] = $environments->pluck('id')->all();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $job->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        $this->setUrl($client, $job->id);
        $this->setAuthHeader($client);

        // Act & Assert
        $response = $this->put($this->url, [], $this->headers);
        $response->assertStatus(200);

        $response->assertJson(
            [
                'data' => [
                    'attributes' => [
                        'name' => $step2Value['name'],
                        'type' => $step1Value['type'],
                        'detail' => $step2Value['detail'],
                        'recruiting' => false,
                        'activated' => false,
                        're_edit' => false,
                        'rejected' => false,
                        'closed' => false,
                        'limited_type_id' => Job::LIMIT_TYPE_PARTNERS,
                        'wall_id' => null,
                        'client_id' => $client->id,
                        'client_name' => $client->username,
                        'client_thumbnail' => $client->thumbnail_url,
                        // 's3_docs' => [] // 別でテストする
                        'job_tags' => [
                            [
                                'id' => $jobTags[0]->id,
                                'name' => $jobTags[0]->name,
                                'link' => $jobTags[0]->search_name
                            ],
                            [
                                'id' => $jobTags[1]->id,
                                'name' => $jobTags[1]->name,
                                'link' => $jobTags[1]->search_name
                            ],
                        ],
                        'business_categories' => [
                            [
                                'id' => $businessCategoryParent->id,
                                'name' => $businessCategoryParent->name,
                                'link' => $businessCategoryParent->parent_name,
                                'child_categories' => [
                                    [
                                        'id' => $businessCategoryChild->id,
                                        'name' => $businessCategoryChild->name,
                                        'link' => $businessCategoryChild->parent_name
                                    ]
                                ]
                            ]
                        ],
                        'business_skills' => [
                            [
                                'id' => $businessSkillGenre->id,
                                'name' => $businessSkillGenre->name,
                                'businessSkills' => [
                                    [
                                        'id' => $businessSkills[0]->id,
                                        'name' => $businessSkills[0]->name,
                                    ],
                                    [
                                        'id' => $businessSkills[1]->id,
                                        'name' => $businessSkills[1]->name,
                                    ]
                                ]
                            ]
                        ],
                        'prefectures' => [
                            [
                                'id' => $prefectures[0]->id,
                                'name' => $prefectures[0]->name,
                                'area_id' => $prefectures[0]->area_id,
                            ],
                            [
                                'id' => $prefectures[1]->id,
                                'name' => $prefectures[1]->name,
                                'area_id' => $prefectures[1]->area_id,
                            ]
                        ],
                        'business_careers' => [
                            [
                                'id' => $businessCareerParent->id,
                                'name' => $businessCareerParent->name,
                                'child_careers' => [
                                    [
                                        'id' => $businessCareers[0]->id,
                                        'name' => $businessCareers[0]->name,
                                    ],
                                    [
                                        'id' => $businessCareers[1]->id,
                                        'name' => $businessCareers[1]->name,
                                    ]
                                ]
                            ]
                        ],
                        'unit_price' => $step2Value['unit_price_other'],
                        'recruitment_count' => $step2Value['capacity_other'] . '名',
                        'period' => 'あと1日',
                        'scheduled_reward' => $step2Value['orders_per_worker_other'],
                        'workable_time' => $workableTime->workable_time
                    ]
                ]
            ]
        );

        // 編集前の添付ファイルが削除されている
        for ($fileIndex = 0; $fileIndex < 2; $fileIndex++) {
            $name = "file_old{$fileIndex}.txt";
            $this->assertDatabaseMissing(
                's3_docs',
                [
                    'model' => Job::S3_PATH_PROJECT,
                    'filename' => $name
                ]
            );
        }

        // 添付
        foreach ($jobTemporarinessDocs as $doc) {
            // job_temporariness_docs が消えている
            $this->assertDatabaseMissing(
                'job_temporariness_docs',
                [
                    'temporariness_id' => $step2->id,
                    'filename' => $doc->filename
                ]
            );
            // s3_docs がある
            $this->assertDatabasehas(
                's3_docs',
                [
                    'model' => Job::S3_PATH_PROJECT,
                    'filename' => $doc->filename
                ]
            );
        }
    }

    // 差し戻し編集時、仕事タイプをタスクからプロジェクトに変更した場合
    public function testPut200TaskToProject()
    {
        // Arrange
        $client = factory(User::class)->states('client')->create();
        $workers = factory(User::class, 3)->states('worker')->create();

        // S3ClientのMock化
        $s3ClientMock = $this->getS3ClientMock();
        $s3ClientMock->shouldReceive('storeS3Object')->times(2)->andReturn(true);
        $s3ClientMock->shouldReceive('getS3ObjectUrlByPath')->times(2)->andReturn('http://hoge');
        $s3ClientMock->shouldReceive('deleteS3Object')->times(2)->andReturn(true);

        $job = factory(Job::class)->states('task', 'not_active', 're_edit')->create();
        factory(JobRole::class)->create(
            [
                'user_id' => $client->id,
                'job_id' => $job->id,
                'role_id' => JobRole::OUTSOURCER
            ]
        );
        $task = factory(Task::class)->create(
            ['job_id' => $job->id]
        );
        
        // 修正前の仕事への添付
        for ($fileIndex = 0; $fileIndex < 2; $fileIndex++) {
            $path = 'hoge';
            $name = "file_old{$fileIndex}.txt";
            factory(S3Doc::class)->create(
                [
                    'model' => Job::S3_PATH_TASK,
                    'foreign_key' => $task->id,
                    's3_path' => $path,
                    'filename' => $name
                ]
            );
        }

        // 一部をパートナーにする
        $partners = [];
        for ($index = 0; $index < 2; $index++) {
            $partners[] = factory(Partner::class)->create(
                [
                    'outsourcer_id' => $client->id,
                    'contractor_id' => $workers[$index]->id,
                ]
            );
        }
        $notCurrentPartner = factory(Partner::class)->create(
            [
                'outsourcer_id' => $client->id,
                'contractor_id' => $workers[2]->id,
                'state' => Partner::STATE_DISSOLVED_BY_CONTRACTOR
            ]
        );

        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );
        $businessSkillGenre = factory(BusinessSkillGenre::class)->create();
        $businessSkills = factory(BusinessSkill::class, 2)->create([
            'business_skill_genre_id' => $businessSkillGenre->id
        ]);

        // 下書きデータ
        $idFormat = Temporariness::JOB_REEDIT_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1ProjectValue();
        $step1Value['job_id'] = $job->id;
        $step1Value['type'] = Job::TYPE_PROJECT;
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $job->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );

        // STEP2
        $step2Value = $this->getStep2ProjectValue();
        $step2Value['job_id'] = $job->id;
        $step2Value['workable_time_id'] = $workableTime->id;
        $step2Value['job_tag_ids'] = $jobTags->pluck('id')->all();
        $step2Value['period_type'] = Temporariness::PROJECT_PERIOD_TYPE_FIX_DATE;
        $step2Value['period'] = Carbon::tomorrow('Asia/Tokyo')->format('Y-m-d');
        $step2 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $job->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );

        // STEP3
        $step3 = factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $job->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );
        // 添付
        $jobTemporarinessDocs = [];
        for ($fileIndex = 0; $fileIndex < 2; $fileIndex++) {
            $path = 'hoge/fuga';
            $name = "file{$fileIndex}.txt";
            $jobTemporarinessDocs[] = factory(JobTemporarinessDoc::class)->create(
                [
                    'temporariness_id' => $step3->id,
                    's3_path' => $path,
                    'filename' => $name
                ]
            );
            $s3ClientMock->storeS3Object($path, $name, UploadedFile::fake()->create($name), false);
        }

        // STEP4
        $step4Value = $this->getStep4Value();
        $step4Value['job_id'] = $job->id;
        $step4Value['limited_type'] = Temporariness::JOB_LIMIT_TYPE_PARTNERS;
        $step4Value['partner_ids'] = array_merge([$notCurrentPartner->id], collect($partners)->pluck('id')->all());
        $step4Value['prefecture_ids'] = $prefectures->pluck('id')->all();
        $step4Value['business_career_ids'] = collect($businessCareers)->pluck('id')->all();
        $step4Value['business_skill_ids'] = $businessSkills->pluck('id')->all();
        $step4Value['environment_ids'] = $environments->pluck('id')->all();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $job->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        $this->setUrl($client, $job->id);
        $this->setAuthHeader($client);

        // Act

        // Assert
    }

    public function testApprovedMailProject()
    {
        $client = factory(User::class)->states('client', 'prepaid')->create(
            ['job_state_inform' => true]
        );
        $workers = factory(User::class, 2)->states('worker')->create(
            ['partner_inform' => true]
        );
        $notInformedWorker = factory(User::class)->states('worker')->create(
            ['partner_inform' => false]
        );
        $partners = [];
        foreach ($workers as $worker) {
            $partners[] = factory(Partner::class)->create(
                [
                    'outsourcer_id' => $client->id,
                    'contractor_id' => $worker->id
                ]
            );
        }
        $partners[] = factory(Partner::class)->create(
            [
                'outsourcer_id' => $client->id,
                'contractor_id' => $notInformedWorker->id
            ]
        );

        $workableTime = factory(WorkableTime::class)->create();
        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );

        // 自動承認のクライアントにする
        $this->createAutoApproveUserRecord($client);

        // 下書きデータ
        $idFormat = Temporariness::JOB_REGISTER_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1ProjectValue();
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $client->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2ProjectValue();
        unset($step2Value['job_tag_ids']);
        $step2Value['workable_time_id'] = $workableTime->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $client->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );
        // STEP3
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $client->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );
        // STEP4
        $step4Value = $this->getStep4Value();
        $step4Value['limited_type'] = Temporariness::JOB_LIMIT_TYPE_PARTNERS;
        $step4Value['partner_ids'] = collect($partners)->pluck('id')->all();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $client->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        $this->setUrl($client);
        $this->setAuthHeader($client);

        Bus::fake();

        // Act & Assert
        $response = $this->post($this->url, [], $this->headers);
        $response->assertStatus(200);

        // クライアントへ仕事公開メール
        Bus::assertDispatched(
            Approved::class,
            function ($approveJob) use ($client) {
                return $approveJob->client->id === $client->id;
            }
        );
        // ワーカーへパートナー案件メール
        Bus::assertDispatched(
            InvitedAsPartner::class,
            function ($invited) use ($client, $partners) {
                return $invited->client->id === $client->id;
            }
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testApprovedMailTask()
    {
        Mail::fake();
        $client = factory(User::class)->states('client', 'prepaid')->create(
            ['job_state_inform' => true]
        );
        $workers = factory(User::class, 2)->states('worker')->create(
            ['partner_inform' => true]
        );
        $notInformedWorker = factory(User::class)->states('worker')->create(
            ['partner_inform' => false]
        );
        $partners = [];
        foreach ($workers as $worker) {
            $partners[] = factory(Partner::class)->create(
                [
                    'outsourcer_id' => $client->id,
                    'contractor_id' => $worker->id
                ]
            );
        }
        $partners[] = factory(Partner::class)->create(
            [
                'outsourcer_id' => $client->id,
                'contractor_id' => $notInformedWorker->id
            ]
        );

        $workableTime = factory(WorkableTime::class)->create();
        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );

        // 自動承認のクライアントにする
        $this->createAutoApproveUserRecord($client);

        // 下書きデータ
        $idFormat = Temporariness::JOB_REGISTER_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1TaskValue();
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $client->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2TaskValue();
        unset($step2Value['job_tag_ids']);
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $client->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );
        // STEP3
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $client->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );
        // STEP4
        $step4Value = $this->getStep4Value();
        $step4Value['limited_type'] = Temporariness::JOB_LIMIT_TYPE_PARTNERS;
        $step4Value['partner_ids'] = collect($partners)->pluck('id')->all();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $client->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        $this->setUrl($client);
        $this->setAuthHeader($client);

        $this->setPaymentMock();

        Bus::fake();

        // Act & Assert
        $response = $this->post($this->url, [], $this->headers);
        $response->assertStatus(200);

        // クライアントへ仕事公開メール
        Bus::assertDispatched(
            Approved::class,
            function ($approveJob) use ($client) {
                return $approveJob->client->id === $client->id;
            }
        );
        // ワーカーへパートナー案件メール
        Bus::assertDispatched(
            InvitedAsPartner::class,
            function ($invited) use ($client, $partners) {
                return $invited->client->id === $client->id;
            }
        );
        // クライアントへクレジット支払完了メール
        Mail::assertQueued(\App\Mail\Mails\Deposit\CaughtDeposit::class, function ($mail) use ($client) {
            return $mail->addressTo === $client->email &&
                $mail->username === $client->username;
        });
    }

    // タスク自動承認、後払いの場合のポイント、task_trade データのテスト
    public function testDeferredTaskApproved()
    {
        $this->doTestTaskJournal(true);
    }

    /**
     * タスク自動承認、前払いの場合のポイント、task_trade データのテスト
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPrepaidTaskApproved()
    {
        $this->doTestTaskJournal();
    }

    private function doTestTaskJournal(bool $deferrable = false)
    {
        Mail::fake();
        if ($deferrable) {
            $deferringFee = factory(DeferringFee::class)->create();
            $client = factory(User::class)->states('client', 'deferrable')->create(
                ['deferring_fee_id' => $deferringFee->id]
            );
        } else {
            $client = factory(User::class)->states('client', 'prepaid')->create();
        }
        $workableTime = factory(WorkableTime::class)->create();
        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );

        // 自動承認のクライアントにする
        $this->createAutoApproveUserRecord($client);

        // 下書きデータ
        $idFormat = Temporariness::JOB_REGISTER_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1TaskValue();
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $client->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2TaskValue();
        unset($step2Value['job_tag_ids']);
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $client->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );
        // STEP3
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $client->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );
        // STEP4
        $step4Value = $this->getStep4Value();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $client->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        // メール処理があるため
        Bus::fake();

        $this->setUrl($client);
        $this->setAuthHeader($client);

        if (!$deferrable) {
            $this->setPaymentMock();
        }

        $response = $this->post($this->url, [], $this->headers);
        $response->assertStatus(200);

        // 登録レコードを確認
        $job = Job::with('task')->first();
        $this->assertDatabasehas(
            'task_trades',
            [
                'state' => TradeState::STATE_TASK_REGISTERED,
                'job_id' => $job->id,
                'task_id' => $job->task->id,
                'contractor_id' => $client->id
            ]
        );
        $this->assertDatabasehas(
            'point_logs',
            [
                'detail' => $deferrable ? PointLog::DEFERRED_TASK_REGISTRATION : PointLog::TASK_REGISTRATION
            ]
        );
        // クレジット支払完了メール
        if ($deferrable) {
            Mail::assertNotQueued(\App\Mail\Mails\Deposit\CaughtDeposit::class);
        } else {
            Mail::assertQueued(\App\Mail\Mails\Deposit\CaughtDeposit::class, function ($mail) use ($client) {
                return $mail->addressTo === $client->email &&
                    $mail->username === $client->username;
            });
        }
    }

    /**
     * 前払いタスクの入金レコード & タスク登録仕分け確認
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testTaskPrepaidDeposit()
    {
        Mail::fake();
        $client = factory(User::class)->states('client', 'prepaid')->create();
        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );

        // 自動承認のクライアントにする
        $this->createAutoApproveUserRecord($client);

        // 下書きデータ
        $idFormat = Temporariness::JOB_REGISTER_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1TaskValue();
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $client->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2TaskValue();
        unset($step2Value['job_tag_ids']);
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $client->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );
        // STEP3
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $client->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );
        // STEP4
        $step4Value = $this->getStep4Value();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $client->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        $this->setUrl($client);
        $this->setAuthHeader($client);

        $paymentServiceMock = $this->setPaymentMock();

        Bus::fake();

        $response = $this->post($this->url, [], $this->headers);

        // 仕分けレコード
        // ポイント購入
        $this->assertDatabaseHas(
            'point_logs',
            [
                'detail' => PointLog::PURCHASE_CREDIT
            ]
        );
        // タスク登録
        $this->assertDatabaseHas(
            'point_logs',
            [
                'detail' => PointLog::TASK_REGISTRATION
            ]
        );
        // credit_purchases
        $this->assertDatabaseHas(
            'credit_purchases',
            [
                'user_id' => $client->id
            ]
        );
        // job_charges
        $this->assertDatabaseHas(
            'job_charges',
            [
                'price_job' => $step2Value['unit_price'] * $step2Value['quantity']
            ]
        );
        Mail::assertQueued(\App\Mail\Mails\Deposit\CaughtDeposit::class, function ($mail) use ($client) {
            return $mail->addressTo === $client->email &&
                $mail->username === $client->username;
        });
    }


    /**
     * 前払いクライアントの決済サービスエラー
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testTaskPrepaidDepositPaymentServiceException()
    {
        Mail::fake();
        $client = factory(User::class)->states('client', 'prepaid')->create();
        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );

        // 自動承認のクライアントにする
        $this->createAutoApproveUserRecord($client);

        // 下書きデータ
        $idFormat = Temporariness::JOB_REGISTER_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1TaskValue();
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $client->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2TaskValue();
        unset($step2Value['job_tag_ids']);
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $client->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );
        // STEP3
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $client->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );
        // STEP4
        $step4Value = $this->getStep4Value();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $client->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // 決済サービスで例外を投げる設定
        $paymentServiceClientMock = Mockery::mock('alias:' . \PaymentService\Client::class);
        $paymentServiceClientMock->shouldReceive('config')->once();
        $paymentServiceMock = Mockery::mock('alias:' . \PaymentService\CreditCard::class);
        $exception = new \PaymentService\Error\InvalidInput(0, 'test exception');
        $paymentServiceMock->shouldReceive('all')
            ->once()
            ->andThrow($exception);

        Bus::fake();

        // Act & Assert
        $this->post($this->url, [], $this->headers)->assertStatus(400);
        Mail::assertNotQueued(\App\Mail\Mails\Deposit\CaughtDeposit::class);
    }

    /**
     * 前払いクライアントの入金不足エラー（実際には起こり得ない想定だが）
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testTaskPrepaidDepositShortage()
    {
        Mail::fake();
        $client = factory(User::class)->states('client', 'prepaid')->create();
        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );

        // 自動承認のクライアントにする
        $this->createAutoApproveUserRecord($client);

        // 下書きデータ
        $idFormat = Temporariness::JOB_REGISTER_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1TaskValue();
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $client->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2TaskValue();
        unset($step2Value['job_tag_ids']);
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $client->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );
        // STEP3
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $client->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );
        // STEP4
        $step4Value = $this->getStep4Value();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $client->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        $this->setUrl($client);
        $this->setAuthHeader($client);

        // 入金金額不足の状態を作る
        $targetClass = 'App\Services\PaymentService\PaymentClient';
        $paymentClientMock = Mockery::mock($targetClass);
        $this->app->instance($targetClass, $paymentClientMock);

        $creditDepositObj = new \PaymentService\CreditDeposit();
        $creditDepositObj->id = 1;
        $creditDepositObj->amount = 1;
        $paymentClientMock->shouldReceive('createCreditDeposit')->times(1)
            ->andReturn($creditDepositObj);
        $paymentClientMock->shouldReceive('getCreatedCreditDeposit')->times(1)
            ->andReturn(1);
        $paymentClientMock->shouldReceive('deleteCreditDepositRecord')->times(1)
            ->andReturn(true);

        Bus::fake();

        // Act & Assert
        $this->post($this->url, [], $this->headers)->assertStatus(500);
        Mail::assertNotQueued(\App\Mail\Mails\Deposit\CaughtDeposit::class);
    }

    /**
     * 後払いクライアントのタスク承認時の後払い上限エラー(事前チェックをするのであまり起こり得ないが）
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testTaskDeferredUpperLimit()
    {
        // 後払い上限1円
        $deferringFee = factory(DeferringFee::class)->create([
            'upper_limit' => 1
        ]);
        $client = factory(User::class)->states('client', 'deferrable')->create([
            'deferring_fee_id' => $deferringFee->id
        ]);
        $businessCategoryParent = factory(BusinessCategory::class)->create();
        $businessCategoryChild = factory(BusinessCategory::class)->create(
            ['parent_id' => $businessCategoryParent->id]
        );

        // 自動承認のクライアントにする
        $this->createAutoApproveUserRecord($client);

        // 下書きデータ
        $idFormat = Temporariness::JOB_REGISTER_ID_FORMAT;
        // STEP1
        $step1Value = $this->getStep1TaskValue();
        $step1Value['business_category_id'] = $businessCategoryChild->id;
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    1,
                    $client->id
                ),
                'value' => json_encode($step1Value),
                'user_id' => $client->id
            ]
        );
        // STEP2
        $step2Value = $this->getStep2TaskValue();
        unset($step2Value['job_tag_ids']);
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    2,
                    $client->id
                ),
                'value' => json_encode($step2Value),
                'user_id' => $client->id
            ]
        );
        // STEP3
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    3,
                    $client->id
                ),
                'value' => json_encode(['step_id' => 3]),
                'user_id' => $client->id
            ]
        );
        // STEP4
        $step4Value = $this->getStep4Value();
        factory(Temporariness::class)->create(
            [
                'id' => sprintf(
                    $idFormat,
                    4,
                    $client->id
                ),
                'value' => json_encode($step4Value),
                'user_id' => $client->id
            ]
        );

        $this->setUrl($client);
        $this->setAuthHeader($client);

        Bus::fake();

        $this->post($this->url, [], $this->headers)->assertStatus(500);
    }
}
