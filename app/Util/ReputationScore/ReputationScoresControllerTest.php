<?php

use App\Http\Controllers\V1\Internal\Admin\ReputationScoresController;
use App\Jobs\Admin\ReputationScoreJob;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ReputationScoresControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $url;

    /**
     * @param $adminId
     * @param $wallId
     */
    private function setUrl($adminId)
    {
        $this->url = $this->internalDomain . '/api/v1/admin/' . $adminId . '/reputation_scores';
    }

    public function providerStore202()
    {
        return
        [
            'ユーザーIDを1つ指定' => [
                ['user_ids' => '11109']
            ],
            'ユーザーIDを複数指定' => [
                ['user_ids' => '11109,11110,11111']
            ],
            'ユーザーIDを複数指定（カンマ間にスペースが存在している）' => [
                ['user_ids' => '11109, 11110,　11111 ']
            ],
            '数値以外を含む場合' => [
                ['user_ids' => '11109,string']
            ]
        ];
    }

    /**
      * @dataProvider providerStore202
      *
      * @param array $params
      */
    public function testStore202($params)
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
        Bus::assertDispatched(ReputationScoreJob::class);
    }

    public function providerStore422()
    {
        return
        [
            'user_ids がnull値' => [
                ['user_ids' => null],
                ['user_ids' => ['入力してください。']]
            ],
            'user_ids に数値が1つも含まれない場合' => [
                ['user_ids' => 'strong1,string2'],
                ['user_ids' => ['ユーザーidの入力情報を再度ご確認ください']]
            ]
        ];
    }

    /**
      * @dataProvider providerStore422
      *
      * @param array $params
      * @param array $validationMessage
      */
    public function testStore422($params, $validationMessage)
    {
        // Arrange
        $admin = factory(User::class)->states('admin')->create();
        $this->setUrl($admin->id);
        $this->setAuthHeader($admin);

        // Act
        $response = $this->post($this->url, $params, $this->headers);

        // Assert
        $response->assertStatus(422);
        $response->assertJson($validationMessage);
    }

    // 入力されたユーザーIDの扱いを確認する
    public function providerTestGetUserIds()
    {
        return
        [
            'ユーザーIDを1つ指定' => [
                '11109',
                [11109]
            ],
            'ユーザーIDを複数指定' => [
                '11109,11110,11111',
                [11109,11110,11111]
            ],
            'ユーザーIDを複数指定（カンマ間にスペースが存在している）' => [
                '11109, 11110,　11111　',
                [11109,11110,11111]
            ],
            '数値以外を含む場合' => [
                '11109,string',
                [11109]
            ],
            '数値が1つも含まれない場合' => [
                'string1,string2',
                []
            ]
        ];
    }

    /**
      * @dataProvider providerTestFormatDate
      *
      * @param string $inputUsersValue
      * @param array $expectedUserIds
      */
    public function testGetUserIds($inputUsersValue, $expectedUserIds)
    {
        // Act
        $reputationScoresController = new ReputationScoresController();
        $method = $this->unprotect($reputationScoresController, 'getUserIds');
        $resultUserIds = $method->invoke($reputationScoresController, $inputUsersValue);

        // Assert
        $this->assertSame($expectedUserIds, $resultUserIds);
    }
}
