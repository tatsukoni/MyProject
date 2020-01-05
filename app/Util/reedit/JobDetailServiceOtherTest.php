<?php

namespace Tests\Unit\Domain\JobTemporariness\JobDetail;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Domain\JobTemporariness\JobDetail\JobDetailService;
use App\Models\Job;
use App\Models\Temporariness;
use App\Models\TradeParameter;
use App\Models\WorkableTime;
use Validator;

/**
 * 商品登録、ライティング以外のカテゴリのテスト
 */
class JobDetailServiceOtherTest extends TestCase
{
    use DatabaseTransactions;

    const CATEGORY_ID = 905;      // シール・ラベル貼り

    /**
     * @return array 商品登録、ライティング以外の仕事で登録できる有効データ
     */
    private function getValidData()
    {
        return [
            'workable_time_id' => factory(WorkableTime::class)->create()->id
        ];
    }

    /**
     * @param array $overwriteValues
     * @return mixed
     */
    private function getParameter(array $overwriteValues)
    {
        $parameter = $this->getValidData();
        foreach ($overwriteValues as $overwriteValue) {
            $parameter[$overwriteValue['key']] = $overwriteValue['value'];
        }
        return $parameter;
    }

    public function testGetValidationRuleOther()
    {
        // Arrange
        $service = new JobDetailService;

        // Act & Assert
        // タスクの場合、設定されていなくてもOK
        $validator = Validator::make(
            $this->getParameter([['key' => 'workable_time_id', 'value' => null]]),
            $service->getValidationRule(self::CATEGORY_ID, Job::TYPE_TASK)
        );
        $this->assertSame(true, $validator->passes());

        // プロジェクトの場合、設定されていないためNG
        $validator = Validator::make(
            $this->getParameter([['key' => 'workable_time_id', 'value' => null]]),
            $service->getValidationRule(self::CATEGORY_ID, Job::TYPE_PROJECT)
        );
        $this->assertSame(false, $validator->passes());
        $this->assertTrue(array_key_exists('workable_time_id', $validator->messages()->messages()));

        // 有効値のためOK
        $validator = Validator::make(
            $this->getParameter([]),
            $service->getValidationRule(self::CATEGORY_ID, Job::TYPE_PROJECT)
        );
        $this->assertSame(true, $validator->passes());

        // 無効のためNG
        $validator = Validator::make(
            $this->getParameter([['key' => 'workable_time_id', 'value' => -1]]),
            $service->getValidationRule(self::CATEGORY_ID, Job::TYPE_PROJECT)
        );
        $this->assertSame(false, $validator->passes());
        $this->assertTrue(array_key_exists('workable_time_id', $validator->messages()->messages()));
    }

    public function testGetRecord()
    {
        // Arrange
        $service = new JobDetailService;
        $parameter = $this->getParameter([]);
        $temporariness = factory(Temporariness::class)->create(['value' => json_encode($parameter)]);

        // Act
        $record = $service->getRecord(self::CATEGORY_ID, Job::TYPE_PROJECT, $temporariness);

        // Assert
        $this->assertSame(
            [
                'tradeParameter' => [
                    'workable_time_id' => $parameter['workable_time_id']
                ],
                'job' => [
                    'teachme' => null,
                    'recommend' => null,
                    'prohibitions' => null,
                    'pr_message' => null
                ]
            ],
            $record
        );
    }

    public function testGetStep2Values()
    {
        // Arrange
        $service = new JobDetailService;
        $jobProject = factory(Job::class)->states('project')->create(['business_category_id' => self::CATEGORY_ID]);
        $jobTask = factory(Job::class)->states('task')->create(['business_category_id' => self::CATEGORY_ID]);
        $workableTimeId = factory(WorkableTime::class)->create()->id;
        factory(TradeParameter::class)->create([
            'job_id' => $jobProject->id,
            'workable_time_id' => $workableTimeId
        ]);

        // Act & Assert
        // プロジェクト
        $this->assertSame(
            ['workable_time_id' => $workableTimeId],
            $service->getStep2Values($jobProject)
        );

        // タスク
        $this->assertSame(
            [],
            $service->getStep2Values($jobTask)
        );
    }
}
