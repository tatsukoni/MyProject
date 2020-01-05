<?php

namespace Tests\Unit\Domain\JobTemporariness\JobDetail;

use App\Domain\JobTemporariness\JobDetail\JobDetailService;
use App\Models\Job;
use App\Models\JobDetailRegisterItem;
use App\Models\Temporariness;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 商品登録カテゴリのテスト
 */
class JobDetailServiceRegisterTest extends TestCase
{
    use DatabaseTransactions, Common;

    const CATEGORY_ID = 1268;  // 商品登録・撮影

    /**
     * @return array 商品登録の仕事で登録できる有効データ
     */
    private function getValidData()
    {
        $validWritingData = [
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
        ];
        $validCommonData = $this->getValidCommonData();
        return array_merge($validWritingData, $validCommonData);
    }

    public function provideParameters()
    {
        $parameters = [
            // getValidDataのままであればOK
            [true, $this->getParameter([])],



            // [トライアルについて]
            // 未設定のためNG
            [false, $this->getParameter([['key' => 'has_trial', 'value' => null]]), 'has_trial'],

            // falseなので本文がなくてもOK
            [true, $this->getParameter([['key' => 'has_trial', 'value' => 0]])],

            // trueなのに本文がないのでNG
            [false, $this->getParameter([['key' => 'has_trial', 'value' => 1], ['key' => 'trial', 'value' => null]]), 'trial'],

            // 文字数内のためOK
            [true, $this->getParameter([['key' => 'has_trial', 'value' => 1], ['key' => 'trial', 'value' => str_random(255)]])],

            // 文字数オーバーのためNG
            [false, $this->getParameter([['key' => 'has_trial', 'value' => 1], ['key' => 'trial', 'value' => str_random(256)]]), 'trial'],



            // [画像作成・加工について]
            // 未設定のためNG
            [false, $this->getParameter([['key' => 'has_image_creation', 'value' => null]]), 'has_image_creation'],

            // falseなので本文がなくてもOK
            [true, $this->getParameter([['key' => 'has_image_creation', 'value' => 0]])],

            // trueなのに本文がないのでNG
            [false, $this->getParameter([['key' => 'has_image_creation', 'value' => 1], ['key' => 'image_creation', 'value' => null]]), 'image_creation'],

            // 文字数内のためOK
            [true, $this->getParameter([['key' => 'has_image_creation', 'value' => 1], ['key' => 'image_creation', 'value' => str_random(255)]])],

            // 文字数オーバーのためNG
            [false, $this->getParameter([['key' => 'has_image_creation', 'value' => 1], ['key' => 'image_creation', 'value' => str_random(256)]]), 'image_creation'],



            // [説明文作成について]
            // 未設定のためNG
            [false, $this->getParameter([['key' => 'has_description_creation', 'value' => null]]), 'has_description_creation'],

            // falseなので本文がなくてもOK
            [true, $this->getParameter([['key' => 'has_description_creation', 'value' => 0]])],

            // trueなのに本文がないのでNG
            [false, $this->getParameter([['key' => 'has_description_creation', 'value' => 1], ['key' => 'description_creation', 'value' => null]]), 'description_creation'],

            // 文字数内のためOK
            [true, $this->getParameter([['key' => 'has_description_creation', 'value' => 1], ['key' => 'description_creation', 'value' => str_random(255)]])],

            // 文字数オーバーのためNG
            [false, $this->getParameter([['key' => 'has_description_creation', 'value' => 1], ['key' => 'description_creation', 'value' => str_random(256)]]), 'description_creation'],



            // [マニュアルについて]
            // 未設定でもOK
            [true, $this->getParameter([['key' => 'manual', 'value' => null]])],

            // 文字数内のためOK
            [true, $this->getParameter([['key' => 'manual', 'value' => str_random(255)]])],

            // 文字数オーバーのためNG
            [false, $this->getParameter([['key' => 'manual', 'value' => str_random(256)]]), 'manual'],
        ];

        $commonParameters = $this->getProvideCommonParameters();
        return array_merge($parameters, $commonParameters);
    }

    public function provideGetRecord()
    {
        return
        [
            '仕事タイプがプロジェクト' => [
                Job::TYPE_PROJECT
            ],
            '仕事タイプがタスク' => [
                Job::TYPE_TASK
            ]
        ];
    }

    /**
     * @dataProvider provideGetRecord
     *
     * @param int $jobTyoe
     */
    public function testGetRecord(int $jobTyoe)
    {
        // Arrange
        $service = new JobDetailService;
        $parameter = $this->getParameter([]);
        $temporariness = factory(Temporariness::class)->create(['value' => json_encode($parameter)]);

        // Act
        $record = $service->getRecord(self::CATEGORY_ID, $jobTyoe, $temporariness);

        // Assert
        $expectRecord = [
            'jobDetailRegisterItem' => [
                'has_trial' => $parameter['has_trial'],
                'trial' => $parameter['trial'],
                'has_image_creation' => $parameter['has_image_creation'],
                'image_creation' => $parameter['image_creation'],
                'has_description_creation' => $parameter['has_description_creation'],
                'description_creation' => $parameter['description_creation'],
                'manual' => $parameter['manual'],

            ],
            'job' => [
                'teachme' => $parameter['teachme'],
                'recommend' => $parameter['recommend'],
                'prohibitions' => $parameter['prohibitions'],
                'pr_message' => $parameter['pr_message'],
            ]
        ];
        if ($jobTyoe === Job::TYPE_PROJECT) {
            // カテゴリが「商品登録」に変更された場合、下記を明示的にnullに更新する必要がある
            $expectRecord += [
                'tradeParameter' => [
                    'workable_time_id' => null
                ]
            ];
        }
        $this->assertEquals($expectRecord, $record);
    }

    public function testGetStep2Values()
    {
        // Arrange
        $service = new JobDetailService;
        $job = factory(Job::class)->states('project')->create(
            array_merge(
                ['business_category_id' => self::CATEGORY_ID],
                $this->getValidCommonData()
            )
        );
        $detail = factory(JobDetailRegisterItem::class)->create(['job_id' => $job->id]);

        // Act
        $values = $service->getStep2Values($job);

        // Assert
        $this->assertSame(
            [
                'has_trial' => (int) $detail->has_trial,
                'trial' => $detail->trial,
                'has_image_creation' => (int) $detail->has_image_creation,
                'image_creation' => $detail->image_creation,
                'has_description_creation' => (int) $detail->has_description_creation,
                'description_creation' => $detail->description_creation,
                'manual' => $detail->manual,
                'teachme' => $job->teachme,
                'recommend' => $job->recommend,
                'prohibitions' => $job->prohibitions,
                'pr_message' => $job->pr_message
            ],
            $values
        );
    }
}
