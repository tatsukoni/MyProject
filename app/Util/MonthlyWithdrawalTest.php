<?php

namespace Tests\Unit\Jobs\Admin;

use App\Jobs\Admin\MonthlyWithdrawal;
use App\Mail\Mails\Admin\MonthlyWithdrawalReport;
use App\Models\PointDetail;
use App\Models\PointLog;
use App\Models\User;
use App\Services\PaymentService\PaymentClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PaymentService\Bank;
use Tests\StubClass\BankStub;
use Tests\TestCase;

class MonthlyWithdrawalTest extends TestCase
{
    use DatabaseTransactions;

    private $startDate;
    private $endDate;

    public function setUp()
    {
        parent::setUp();
        $this->startDate = '2019-02-01 00:00:00'; // default
        $this->endDate = '2019-02-05 00:00:00'; // default
    }

    public function tearDown()
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createTargetData(int $userId, string $viewMode, int $accountTitleId): array
    {
        $createdPointLog = ['2019-01-31 15:00:00', '2019-02-03 00:00:00', '2019-02-04 14:59:59'];
    
        $user = factory(User::class)->states($viewMode)->create([
            'id' => $userId
        ]);
        $pointLog = factory(PointLog::class)->create([
            'id' => random_int(2000, 20000),
            'detail' => PointLog::PERMIT_POINTS_CONVERSION,
            'created' => $createdPointLog[array_rand($createdPointLog, 1)]
        ]);
        $pointDetail = factory(PointDetail::class)->states('escrow')->create([
            'account_title_id' => $accountTitleId,
            'withdrawal' => random_int(1000, 10000),
            'user_id' => $user->id,
            'point_log_id' => $pointLog->id,
            'created' => $pointLog->created,
        ]);
        $bank = new BankStub([
            'id' => random_int(1, 100000),
            'user_id' => $user->id
        ]);

        return compact('user', 'pointLog', 'pointDetail', 'bank');
    }

    private function setPaymentClientMock(int $userId, $bank = null)
    {
        $targetClass = PaymentClient::class;
        $paymentClientMock = Mockery::mock($targetClass);
        $this->app->instance($targetClass, $paymentClientMock);
        $paymentClientMock->shouldReceive('getBankAccount')->once()
            ->with($userId)->andReturn($bank);
    }

    // bank_idに2回アクセスする場合
    private function setPaymentClientMockTwice($bank1, $bank2)
    {
        $targetClass = PaymentClient::class;
        $paymentClientMock = Mockery::mock($targetClass);
        $this->app->instance($targetClass, $paymentClientMock);
        $paymentClientMock->shouldReceive('getBankAccount')->times(2)->andReturn($bank1, $bank2);
    }

    public function testHandle()
    {
        // Assert
        Mail::fake();

        $userId = random_int(100, 10000);
        $targetData = $this->createTargetData($userId, 'worker', 11);
        // set Mock
        $this->setPaymentClientMock($targetData['user']->id, $targetData['bank']);
        
        // Act
        MonthlyWithdrawal::dispatch($this->startDate, $this->endDate);

        // Assert
        Mail::assertSent(
            MonthlyWithdrawalReport::class,
            function ($mail) {
                return $mail->resultCode === MonthlyWithdrawal::SUCCESS_MONTHLY_WITHDRAWAL &&
                    $mail->addressTo === config('shufti.admin_mail') &&
                    $mail->subject === '【月跨ぎの出金】処理に成功しました';
            }
        );
    }

    // 該当データが2件以上ある場合
    public function testHandleMultipleDatas()
    {
        // Arrange
        Mail::fake();

        $userId1 = random_int(100, 10000);
        $userId2 = $userId1 + 1;
        $targetData1 = $this->createTargetData($userId1, 'worker', 11);
        $targetData2 = $this->createTargetData($userId2, 'client', 23);
        // set Mock
        $this->setPaymentClientMockTwice($targetData1['bank'], $targetData2['bank']);

        // Act
        MonthlyWithdrawal::dispatch($this->startDate, $this->endDate);

        // Assert
        Mail::assertSent(
            MonthlyWithdrawalReport::class,
            function ($mail) {
                return $mail->resultCode = MonthlyWithdrawal::SUCCESS_MONTHLY_WITHDRAWAL &&
                    $mail->addressTo = config('shufti.admin_mail') &&
                    $mail->subject = '【月跨ぎの出金】処理に成功しました';
            }
        );
    }

    // 該当するデータが存在しなかった場合
    public function provideTestNoData()
    {
        return
        [
            '指定した日付に該当するデータが存在しない場合' => [
                PointLog::PERMIT_POINTS_CONVERSION,
                PointDetail::ESCROW_ACCOUNT,
                '2019-01-01 00:00:00',
            ],
            '指定した日付に該当するデータが存在しない場合（閾値)' => [
                PointLog::PERMIT_POINTS_CONVERSION,
                PointDetail::ESCROW_ACCOUNT,
                '2019-01-31 14:59:59',
            ],
            '指定した日付に該当するデータが存在しない場合（閾値)' => [
                PointLog::PERMIT_POINTS_CONVERSION,
                PointDetail::ESCROW_ACCOUNT,
                '2019-02-04 15:00:00',
            ],
            'point_logs.detailで条件に一致するデータが存在しない場合' => [
                random_int(4, 100),
                PointDetail::ESCROW_ACCOUNT,
                '2019-02-01 00:00:00',
            ],
            'point_details.account_idで条件に一致するデータが存在しない場合' => [
                PointLog::PERMIT_POINTS_CONVERSION,
                random_int(1, 3),
                '2019-02-01 00:00:00',
            ],
        ];
    }

    /**
     * @dataProvider provideTestNodata
     */
    public function testNoData($detail, $accountId, $created)
    {
        // Assert
        Mail::fake();

        $user = factory(User::class)->states('worker')->create([
            'id' => random_int(100, 10000)
        ]);
        $pointLog = factory(PointLog::class)->create([
            'id' => random_int(2000, 20000),
            'detail' => $detail,
            'created' => $created,
        ]);
        $pointDetail = factory(PointDetail::class)->create([
            'account_title_id' => 11,
            'withdrawal' => random_int(1000, 10000),
            'account_id' => $accountId,
            'user_id' => $user->id,
            'point_log_id' => $pointLog->id,
            'created' => $created,
        ]);

        // Act
        MonthlyWithdrawal::dispatch($this->startDate, $this->endDate);

        // Assert
        Mail::assertSent(
            MonthlyWithdrawalReport::class,
            function ($mail) {
                return $mail->resultCode === MonthlyWithdrawal::NOT_EXIST_MONTHLY_WITHDRAWAL &&
                    $mail->addressTo === config('shufti.admin_mail') &&
                    $mail->subject === '【月跨ぎの出金】該当するデータが存在しませんでした';
            }
        );
    }

    // 該当データ件数が上限（1000件）を超える場合
    public function testMaxData()
    {
        // Arrage
        Mail::fake();

        $maxRecord = new Collection([]);
        for ($index = 0; $index <= 1000; $index++) {
            $maxRecord->prepend([]);
        }
        $monthlyWithdrawalMock = Mockery::mock(MonthlyWithdrawal::class, [$this->startDate, $this->endDate])
            ->makePartial();
        $monthlyWithdrawalMock
            ->shouldReceive('getMonthlyWithdrawal')
            ->once()
            ->andReturn($maxRecord);

        // Act
        $monthlyWithdrawalMock->handle();

        // Assert
        Mail::assertSent(
            MonthlyWithdrawalReport::class,
            function ($mail) {
                return $mail->resultCode === MonthlyWithdrawal::MAX_COUNT_MONTHLY_WITHDRAWAL &&
                    $mail->addressTo === config('shufti.admin_mail') &&
                    $mail->subject === '【月跨ぎの出金】該当するデータ件数が上限に達しています';
            }
        );
    }

    // bank_idの取得に失敗した場合
    public function testFailGetBankId()
    {
        // Assert
        Mail::fake();

        $userId = random_int(100, 10000);
        $targetData = $this->createTargetData($userId, 'worker', 11);
        // set Mock
        $this->setPaymentClientMock($targetData['user']->id);

        // Act
        MonthlyWithdrawal::dispatch($this->startDate, $this->endDate);

        // Assert
        Mail::assertSent(
            MonthlyWithdrawalReport::class,
            function ($mail) {
                return $mail->resultCode === MonthlyWithdrawal::FAIL_GET_BANK_ID &&
                    $mail->addressTo === config('shufti.admin_mail') &&
                    $mail->subject === '【月跨ぎの出金】処理に失敗したレコードがあります';
            }
        );
    }

    // 月跨ぎの出金に該当する意図したデータが返却されることを確認
    public function testGetMonthlyWithdrawal()
    {
        // Arrange
        $userId1 = random_int(100, 10000);
        $userId2 = $userId1 + 1;
        $targetData1 = $this->createTargetData($userId1, 'worker', 11);
        $targetData2 = $this->createTargetData($userId2, 'client', 23);
        $expectedCreated1 = $this->getExpectedCreated($targetData1['pointLog']->created);
        $expectedCreated2 = $this->getExpectedCreated($targetData2['pointLog']->created);
        $expectedArray =
        [
            [
                'user_id' => $targetData1['user']->id,
                'view_mode' => User::MODE_CONTRACTOR,
                'account_title_id' => $targetData1['pointDetail']->account_title_id,
                'withdrawal' => $targetData1['pointDetail']->withdrawal,
                'created' => $expectedCreated1
            ],
            [
                'user_id' => $targetData2['user']->id,
                'view_mode' => User::MODE_OUTSOURCER,
                'account_title_id' => $targetData2['pointDetail']->account_title_id,
                'withdrawal' => $targetData2['pointDetail']->withdrawal,
                'created' => $expectedCreated2
            ]
        ];

        // Act
        $job = new MonthlyWithdrawal($this->startDate, $this->endDate);
        $resultData = $job->getMonthlyWithdrawal()->toArray();
        $resultArray = json_decode(json_encode($resultData), true);

        // Assert
        $this->assertEquals($expectedArray, $resultArray);
    }

    // CSVに渡すデータを整形する箇所において、意図した形に整形されることを確認
    public function testGenerateResultsArray()
    {
        // Arrange
        $userId1 = random_int(100, 10000);
        $userId2 = $userId1 + 1;
        $targetData1 = $this->createTargetData($userId1, 'worker', 11);
        $targetData2 = $this->createTargetData($userId2, 'client', 23);
        $targetData1['bank'] = new BankStub([
            'id' => 111,
            'user_id' => $targetData1['user']->id
        ]);
        $targetData2['bank'] = new BankStub([
            'id' => 1111,
            'user_id' => $targetData2['user']->id
        ]);
        $expectedCreated1 = $this->getExpectedCreated($targetData1['pointLog']->created);
        $expectedCreated2 = $this->getExpectedCreated($targetData2['pointLog']->created);
        $expectedData =
        [
            [
                $targetData1['user']->id,
                'ワーカー',
                '出金額',
                $targetData1['pointDetail']->withdrawal,
                $expectedCreated1,
                $targetData1['bank']->id,
                '001-00000' . $targetData1['bank']->id
            ],
            [
                $targetData2['user']->id,
                'クライアント',
                '換金手数料',
                $targetData2['pointDetail']->withdrawal,
                $expectedCreated2,
                $targetData2['bank']->id,
                '001-0000' . $targetData2['bank']->id
            ]
        ];
        // set Mock
        $this->setPaymentClientMockTwice($targetData1['bank'], $targetData2['bank']);

        // Act
        $job = new MonthlyWithdrawal($this->startDate, $this->endDate);
        $resultRecords = $job->getMonthlyWithdrawal();
        $resultData = $job->generateResultsArray($resultRecords);

        // Assert
        $this->assertEquals($expectedData, $resultData);
    }

    // 口座idの桁数に応じて、GMO上の口座idが正しい形に整形されることを確認
    public function provideTestGenerateGmoBankId()
    {
        return
        [
            '口座idが1桁' => [
                1,
                '001-0000000'
            ],
            '口座idが2桁' => [
                11,
                '001-000000'
            ],
            '口座idが3桁' => [
                111,
                '001-00000'
            ],
            '口座idが4桁' => [
                1111,
                '001-0000'
            ],
            '口座idが5桁' => [
                11111,
                '001-000'
            ],
            '口座idが6桁' => [
                111111,
                '001-00'
            ],
            '口座idが7桁' => [
                1111111,
                '001-0'
            ],
            '口座idが8桁' => [
                11111111,
                '001-'
            ],
            '口座idが8桁より大きい' => [
                111111111,
                '001-'
            ]
        ];
    }

    /**
     * @dataProvider provideTestGenerateGmoBankId
     *
     * @param int $bankId
     * @param string $expectedGmoBankId
     */
    public function testGenerateGmoBankId($bankId, $expectedGmoBankId)
    {
        // Act
        $monthlyWithdrawal = new MonthlyWithdrawal($this->startDate, $this->endDate);
        $method = $this->unprotect($monthlyWithdrawal, 'generateGmoBankId');
        $resultGmoBankId = $method->invoke($monthlyWithdrawal, $bankId);

        // Assert
        $this->assertSame($expectedGmoBankId, $resultGmoBankId);
    }

    // 意図した形のCSVとして出力されることを確認
    public function testGetCsv()
    {
        // Arrange
        $userId1 = random_int(100, 10000);
        $userId2 = $userId1 + 1;
        $targetData1 = $this->createTargetData($userId1, 'worker', 11);
        $targetData2 = $this->createTargetData($userId2, 'client', 23);
        $targetData1['bank'] = new BankStub([
            'id' => 11111,
            'user_id' => $targetData1['user']->id
        ]);
        $targetData2['bank'] = new BankStub([
            'id' => 111111,
            'user_id' => $targetData2['user']->id
        ]);
        $expectedWithdrawal1 = $targetData1['pointDetail']->withdrawal;
        $expectedWithdrawal2 = $targetData2['pointDetail']->withdrawal;
        $expectedCreated1 = $this->getExpectedCreated($targetData1['pointLog']->created);
        $expectedCreated2 = $this->getExpectedCreated($targetData2['pointLog']->created);
        $expectedBankId1 = $targetData1['bank']->id;
        $expectedBankId2 = $targetData2['bank']->id;
        $expectedGmoBankId1 = '001-000' . $expectedBankId1;
        $expectedGmoBankId2 = '001-00' . $expectedBankId2;

        $expectedCsv = 'user_id,種別,出金種別,金額,created,口座id,GMO上の口座id' . "\r\n"
            . "$userId1,ワーカー,出金額,$expectedWithdrawal1," . "\"" . "$expectedCreated1" . "\"" . ",$expectedBankId1,$expectedGmoBankId1" . "\r\n"
            . "$userId2,クライアント,換金手数料,$expectedWithdrawal2," . "\"" . "$expectedCreated2" . "\"" . ",$expectedBankId2,$expectedGmoBankId2" . "\r\n";
        $expectedEncordingCsv = mb_convert_encoding($expectedCsv, 'SJIS-win', 'UTF-8');

        // set Mock
        $this->setPaymentClientMockTwice($targetData1['bank'], $targetData2['bank']);

        // Act
        $job = new MonthlyWithdrawal($this->startDate, $this->endDate);
        $resultRecords = $job->getMonthlyWithdrawal();
        $resultData = $job->generateResultsArray($resultRecords);
        $resultCsv = $job->getCsv($resultData);

        // Assert
        $this->assertEquals($expectedEncordingCsv, $resultCsv);
    }

    // 9時間を加算した日時を返却する
    private function getExpectedCreated(string $targetDate): string
    {
        return Carbon::parse($targetDate)->addHours(9)->format('Y-m-d H:i:s');
    }
}
