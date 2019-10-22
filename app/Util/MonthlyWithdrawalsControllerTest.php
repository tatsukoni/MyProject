<?php

use App\Jobs\Admin\MonthlyWithdrawal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MonthlyWithdrawalsControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $url;

    /**
     * @param $adminId
     * @param $wallId
     */
    private function setUrl($adminId)
    {
        $this->url = $this->internalDomain . '/api/v1/admin/' . $adminId . '/monthly_withdrawals';
    }

    public function providerStore200()
    {
        return 
        [
            '日付形式がY-m-d' => [
                [
                    'start_date' => '2019-02-01',
                    'end_date' => '2019-02-05'
                ]
            ],
            '日付形式がY-m-d H:i:s' => [
                [
                    'start_date' => '2019-02-01 11:22:33',
                    'end_date' => '2019-02-05 11:22:33'
                ]
            ],
            'start_dateのみY-m-d' => [
                [
                    'start_date' => '2019-02-01',
                    'end_date' => '2019-02-05 11:22:33'
                ]
            ],
            'end_dateのみY-m-d' => [
                [
                    'start_date' => '2019-02-01 11:22:33',
                    'end_date' => '2019-02-05'
                ]
            ]
        ];
    }

    /**
      * @dataProvider providerStore200
      *
      * @param array $params
      */
    public function testStore200($params)
    {
        // Arrange
        $admin = factory(User::class)->states('admin')->create();
        $this->setUrl($admin->id);
        $this->setAuthHeader($admin);
        Bus::fake();

        // Act
        $response = $this->post($this->url, $params, $this->headers);

        // Assert
        $response->assertStatus(202);
        $response->assertJson([
            'response' => [
                'message' => 'success.',
            ]
        ]);
        Bus::assertDispatched(MonthlyWithdrawal::class);
    }

    public function providerStore422()
    {
        return
        [
            'start_dateがnull' => [
                ['start_date' => null],
                ['start_date' => ['入力してください。']]
            ],
            'end_dateがnull' => [
                ['end_date' => null],
                ['end_date' => ['入力してください。']]
            ],
            'start_dateが日付の型ではない' => [
                ['start_date' => 'hoge'],
                ['start_date' => ['日付を入力してください。']]
            ],
            'end_dateが日付の型ではない' => [
                ['end_date' => 'hoge'],
                ['end_date' => ['日付を入力してください。']]
            ],
            'start_dateが「Y-m-d」「Y-m-d H:i:s」の型ではない' => [
                ['start_date' => '2019/02/01'],
                ['error' => ['日付は「Y-m-d」もしくは「Y-m-d H:i:s」形式で入力してください']]
            ],
            'end_dateが「Y-m-d」「Y-m-d H:i:s」の型ではない' => [
                ['end_date' => '2019/02/05'],
                ['error' => ['日付は「Y-m-d」もしくは「Y-m-d H:i:s」形式で入力してください']]
            ],
            'start_dateがend_dateよりも後の日' => [
                ['start_date' => '2020-02-01'],
                ['start_date' => ['end dateより前の日付を入力してください。']]
            ],
            'end_dateがstart_dateより前の日' => [
                ['end_date' => '2018-02-05'],
                ['end_date' => ['start dateより後の日付を入力してください。']]
            ]
        ];
    }

    /**
      * @dataProvider providerStore422
      *
      * @param $updateParams
      * @param array $validationMessage
      */
    public function testStore422($updateParams, $validationMessage)
    {
        // Arrange
        $admin = factory(User::class)->states('admin')->create();
        $this->setUrl($admin->id);
        $this->setAuthHeader($admin);

        $baseParams = [
          'start_date' => '2019-02-01',
          'end_date' => '2019-02-05'
        ];
        $postParams = array_merge($baseParams, $updateParams);

        // Act
        $response = $this->post($this->url, $postParams, $this->headers);

        // Assert
        $response->assertStatus(422);
        $response->assertJson($validationMessage);
    }
}
