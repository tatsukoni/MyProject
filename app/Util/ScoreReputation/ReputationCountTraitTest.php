<?php

namespace Tests\Unit\Domain\ScoreReputation;

use App\Domain\ScoreReputation\WorkerReputationCount;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReputationCountTraitTest extends TestCase
{
    use DatabaseTransactions;

    private $workerReputationCount;

    public function setUp()
    {
        parent::setUp();
        // Trait 単体ではインスタンス化できないため、利用クラスからメソッドを呼び出しテストする
        $this->workerReputationCount = new WorkerReputationCount();
    }

    public function providerCheckConditionsValue()
    {
        return
        [
            'startTime が Carbonインスタンスでない場合' => [
                'startTime',
                '2020-01-01 00:00:00'
            ],
            'finishTime が Carbonインスタンスでない場合' => [
                'finishTime',
                '2020-01-01 00:00:00'
            ],
            'userIds が 配列でない場合' => [
                'userIds',
                123
            ],
            'limit が int型でない場合' => [
                'limit',
                '123'
            ],
            'offset が int型でない場合' => [
                'offset',
                '123'
            ],
            '許可されていないキー名が含まれている場合' => [
                'invalidKey',
                'invalidKey'
            ],
            'startTime > finishTime の場合' => [
                'startTime',
                Carbon::parse('2021-01-01 00:00:00', 'Asia/Tokyo')
            ]
        ];
    }

    /**
     * checkConditions()のテスト
     * $conditions の中身が適切でない場合に false が返却されること
     *
     * @dataProvider providerCheckConditionsValue
     * @param string $key
     * @param string $value
     */
    public function testCheckConditionsValue($key, $value)
    {
        // Arrange
        $targetConditions = [
            'startTime' => Carbon::now('Asia/Tokyo'),
            'finishTime' => Carbon::now('Asia/Tokyo')->addSecond(),
            'userIds' => [1, 2, 3],
            'limit' => 10,
            'offset' => 10
        ];

        // Act
        $targetConditions[$key] = $value;
        $result = $this->workerReputationCount->checkConditions($targetConditions);

        // Assert
        $this->assertFalse($result);
    }

    public function providerCheckConditionsTrue()
    {
        return
        [
            '条件の指定がない場合' => [
                []
            ],
            'startTime が指定された場合' => [
                ['startTime' => Carbon::now('Asia/Tokyo')]
            ],
            'startTime, finishTime が指定された場合' => [
                [
                    'startTime' => Carbon::now('Asia/Tokyo'),
                    'finishTime' => Carbon::now('Asia/Tokyo')->addSecond(),
                ]
            ],
            'startTime, finishTime, userIds が指定された場合' => [
                [
                    'startTime' => Carbon::now('Asia/Tokyo'),
                    'finishTime' => Carbon::now('Asia/Tokyo')->addSecond(),
                    'userIds' => [1, 2, 3]
                ]
            ],
            'startTime, finishTime, userIds, limit が指定された場合' => [
                [
                    'startTime' => Carbon::now('Asia/Tokyo'),
                    'finishTime' => Carbon::now('Asia/Tokyo')->addSecond(),
                    'userIds' => [1, 2, 3],
                    'limit' => 10
                ]
            ],
            'startTime, finishTime, userIds, limit, offset が指定された場合' => [
                [
                    'startTime' => Carbon::now('Asia/Tokyo'),
                    'finishTime' => Carbon::now('Asia/Tokyo')->addSecond(),
                    'userIds' => [1, 2, 3],
                    'limit' => 10,
                    'offset' => 10
                ]
            ],
        ];
    }

    /**
     * checkConditions()のテスト
     * $conditions が適切だった場合は true が返却されること
     * 
     * @dataProvider providerCheckConditionsTrue
     * @param array $conditions
     */
    public function testCheckConditionsTrue($conditions)
    {
        // Act
        $result = $this->workerReputationCount->checkConditions($conditions);

        // Assert
        $this->assertTrue($result);
    }
}
