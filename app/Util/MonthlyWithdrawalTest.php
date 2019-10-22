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
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\StubClass\BankStub;
use Tests\TestCase;

class MonthlyWithdrawalTest extends TestCase
{
    use DatabaseTransactions;

    private $startDate;
    private $endDate;

    const INVALID_TARGET_DATE = 1;
    const NOT_EXIST_MONTHLY_WITHDRAWAL = 2;
    const FAIL_GET_BANK_ID = 3;
    const SUCCESS_MONTHLY_WITHDRAWAL = 4;

    public function setUp()
    {
        parent::setUp();
        $this->startDate = '2019-02-01'; // default
        $this->endDate = '2019-02-05'; // default
    }

    public function tearDown()
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createTargetData(int $userId, string $viewMode, int $accountTitleId): array
    {
        $createdPointLog = ['2019-02-01 02:22:33', '2019-02-03 00:00:00', '2019-02-04 14:59:59'];
    
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
            'id' => random_int(1, 10000),
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
    private function setPaymentClientMockTwice($bank1 = null, $bank2 = null)
    {
        $targetClass = PaymentClient::class;
        $paymentClientMock = Mockery::mock($targetClass);
        $this->app->instance($targetClass, $paymentClientMock);
        $paymentClientMock->shouldReceive('getBankAccount')->times(2)->andReturn($bank1, $bank2);
    }

    public function provideTestHandle()
    {
        return 
        [
            "日付がY-m-d形式で渡された場合" => [
                '2019-02-01',
                '2019-02-05'
            ],
            "日付がY-m-d H:i:s形式で渡された場合" => [
                '2019-02-01 11:22:33',
                '2019-02-05 11:22:33'
            ]
        ];
    }

    /**
     * @dataProvider provideTestHandle
     * 
     * @param string $startDate
     * @param string $endDate
     */
    public function testHandle($startDate, $endDate)
    {
        // Assert
        Mail::fake();

        $userId = random_int(100, 10000);
        $targetData = $this->createTargetData($userId, 'worker', 11);
        // set Mock
        $this->setPaymentClientMock($targetData['user']->id, $targetData['bank']);
        
        // Act
        MonthlyWithdrawal::dispatch($startDate, $endDate);

        // Assert
        Mail::assertQueued(
            MonthlyWithdrawalReport::class,
            function ($mail) {
                return $mail->resultCode === self::SUCCESS_MONTHLY_WITHDRAWAL &&
                    $mail->addressTo === config('shufti.admin_mail') &&
                    $mail->attachCsv === true &&
                    $mail->subject === '処理に成功しました';
            }
        );
    }

    /**
     * 該当データが2件以上ある場合
     * @dataProvider provideTestHandle
     * 
     * @param string $startDate
     * @param string $endDate
     */
    public function testHandleMultipleDatas($startDate, $endDate)
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
        MonthlyWithdrawal::dispatch($startDate, $endDate);

        // Assert
        Mail::assertQueued(
            MonthlyWithdrawalReport::class,
            function ($mail) {
                return $mail->resultCode = self::SUCCESS_MONTHLY_WITHDRAWAL &&
                    $mail->addressTo = config('shufti.admin_mail') &&
                    $mail->attachCsv = true &&
                    $mail->subject = '処理に成功しました';
            }
        );
    }

    // 日付のフォーマットが適切でなかった場合
    public function provideTestInvalidDateFormat()
    {
        return
        [
            '「Y-m-d」か「Y-m-d H:i:s」以外のフォーマットで渡された場合' => [
                '2019/02/01',
                '2019/02/05'
            ],
            'start_dateがend_dateよりも後の日付を指定している場合' => [
                '2020-02-01',
                '2019-02-05'
            ]
        ];
    }

    /**
     * @dataProvider provideTestInvalidDateFormat
     * 
     * @param string $startDate
     * @param string $endDate
     */
    public function testInvalidDateFormat($startDate, $endDate)
    {
        // Arrange
        Mail::fake();

        $userId = random_int(100, 10000);
        $targetData = $this->createTargetData($userId, 'worker', 11);

        // Act
        MonthlyWithdrawal::dispatch($startDate, $endDate);

        // Assert
        Mail::assertQueued(
            MonthlyWithdrawalReport::class,
            function ($mail) {
                return $mail->resultCode === self::INVALID_TARGET_DATE &&
                    $mail->addressTo === config('shufti.admin_mail') &&
                    $mail->attachCsv === false &&
                    $mail->subject === '処理に失敗しました';
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
                '2019-02-05 00:00:00',
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
        Mail::assertQueued(
            MonthlyWithdrawalReport::class,
            function ($mail) {
                return $mail->resultCode === self::NOT_EXIST_MONTHLY_WITHDRAWAL &&
                    $mail->addressTo === config('shufti.admin_mail') &&
                    $mail->attachCsv === false &&
                    $mail->subject === '処理に失敗しました';
            }
        );
    }

    // bank_idの取得に失敗した場合
    public function testFailGetBankId()
    {
        // Assert
        Mail::fake();

        $userId1 = random_int(100, 10000);
        $userId2 = $userId1 + 1;
        $targetData1 = $this->createTargetData($userId1, 'worker', 11);
        $targetData2 = $this->createTargetData($userId2, 'client', 23);
        // set Mock
        $this->setPaymentClientMock($targetData1['user']->id);

        // Act
        MonthlyWithdrawal::dispatch($this->startDate, $this->endDate);

        // Assert
        Mail::assertQueued(
            MonthlyWithdrawalReport::class,
            function ($mail) {
                return $mail->resultCode === self::FAIL_GET_BANK_ID &&
                    $mail->addressTo === config('shufti.admin_mail') &&
                    $mail->attachCsv === false &&
                    $mail->subject === '処理に失敗しました';
            }
        );
    }

    // 指定した日付が意図する形式に整形されることを確認
    public function testGenerateTargetDate()
    {
        // Arrange
        $startDate = '2019-02-01 11:22:33'; // 「Y-m-d H:i:s」形式で渡されるstart_dateを設定
        $endDate = '2019-02-05 11:22:33'; // 「Y-m-d H:i:s」形式で渡されるend_dateを設定
        $expectedTargetDate1 = [
            'formatStartDate' => '2019-02-01 00:00:00',
            'formatEndDate' => '2019-02-05 00:00:00'
        ];
        $expectedTargetDate2 = [
            'formatStartDate' => '2019-02-01 11:22:33',
            'formatEndDate' => '2019-02-05 11:22:33'
        ];

        // Act
        $job1 = new MonthlyWithdrawal($this->startDate, $this->endDate);
        $job2 = new MonthlyWithdrawal($startDate, $endDate);
        $resultTargetDate1 = $job1->generateTargetDate();
        $resultTargetDate2 = $job2->generateTargetDate();

        // Assert
        $this->assertSame($expectedTargetDate1, $resultTargetDate1);
        $this->assertSame($expectedTargetDate2, $resultTargetDate2);
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
                'user_id' => strval($targetData1['user']->id),
                'view_mode' => User::MODE_CONTRACTOR,
                'account_title_id' => strval($targetData1['pointDetail']->account_title_id),
                'withdrawal' => strval($targetData1['pointDetail']->withdrawal),
                'created' => $expectedCreated1
            ],
            [
                'user_id' => strval($targetData2['user']->id),
                'view_mode' => User::MODE_OUTSOURCER,
                'account_title_id' => strval($targetData2['pointDetail']->account_title_id),
                'withdrawal' => strval($targetData2['pointDetail']->withdrawal),
                'created' => $expectedCreated2
            ]
        ];

        // Act
        $job = new MonthlyWithdrawal($this->startDate, $this->endDate);
        $resultData = $job->getMonthlyWithdrawal()->toArray();
        $resultArray = json_decode(json_encode($resultData), true);

        // Assert
        $this->assertSame($expectedArray, $resultArray);
    }

    // CSVに渡すデータを整形する箇所において、意図した形に整形されることを確認
    public function testGenerateResultsArray()
    {
        // Arrange
        $userId1 = random_int(100, 10000);
        $userId2 = $userId1 + 1;
        $targetData1 = $this->createTargetData($userId1, 'worker', 11);
        $targetData2 = $this->createTargetData($userId2, 'client', 23);
        $expectedCreated1 = $this->getExpectedCreated($targetData1['pointLog']->created);
        $expectedCreated2 = $this->getExpectedCreated($targetData2['pointLog']->created);
        $expectedBankId1 = $targetData1['bank']->id;
        $expectedBankId2 = $targetData2['bank']->id;
        $expectedData =
        [
            [
                strval($targetData1['user']->id),
                'ワーカー',
                '出金額',
                strval($targetData1['pointDetail']->withdrawal),
                $expectedCreated1,
                strval($expectedBankId1),
                '001-000' . $expectedBankId1
            ],
            [
                strval($targetData2['user']->id),
                'クライアント',
                '換金手数料',
                strval($targetData2['pointDetail']->withdrawal),
                $expectedCreated2,
                strval($expectedBankId2),
                '001-000' . $expectedBankId2
            ]
        ];
        // set Mock
        $this->setPaymentClientMockTwice($targetData1['bank'], $targetData2['bank']);

        // Act
        $job = new MonthlyWithdrawal($this->startDate, $this->endDate);
        $resultRecords = $job->getMonthlyWithdrawal();
        $resultData = $job->generateResultsArray($resultRecords);

        // Assert
        $this->assertSame($expectedData, $resultData);
    }

    // 意図した形のCSVとして出力されることを確認
    public function testGetCsv()
    {
        // Arrange
        $userId1 = random_int(100, 10000);
        $userId2 = $userId1 + 1;
        $targetData1 = $this->createTargetData($userId1, 'worker', 11);
        $targetData2 = $this->createTargetData($userId2, 'client', 23);
        $expectedWithdrawal1 = $targetData1['pointDetail']->withdrawal;
        $expectedWithdrawal2 = $targetData2['pointDetail']->withdrawal;
        $expectedCreated1 = $this->getExpectedCreated($targetData1['pointLog']->created);
        $expectedCreated2 = $this->getExpectedCreated($targetData2['pointLog']->created);
        $expectedBankId1 = $targetData1['bank']->id;
        $expectedBankId2 = $targetData2['bank']->id;
        $expectedGmoBankId1 = '001-000' . $expectedBankId1;
        $expectedGmoBankId2 = '001-000' . $expectedBankId2;

        $expectedCsv = 'user_id,種別,出金種別,金額,created,口座id,GMO上の口座id' . "\n"
            . "$userId1,ワーカー,出金額,$expectedWithdrawal1," . "\"" . "$expectedCreated1" . "\"" . ",$expectedBankId1,$expectedGmoBankId1" . "\n"
            . "$userId2,クライアント,換金手数料,$expectedWithdrawal2," . "\"" . "$expectedCreated2" . "\"" . ",$expectedBankId2,$expectedGmoBankId2" . "\n";

        // set Mock
        $this->setPaymentClientMockTwice($targetData1['bank'], $targetData2['bank']);

        // Act
        $job = new MonthlyWithdrawal($this->startDate, $this->endDate);
        $resultRecords = $job->getMonthlyWithdrawal();
        $resultData = $job->generateResultsArray($resultRecords);
        $resultCsv = $job->getCsv($resultData);

        // Assert
        $this->assertSame($expectedCsv, $resultCsv);
    }

    // 9時間を加算した日時を返却する
    private function getExpectedCreated(string $targetDate): string
    {
        return Carbon::parse($targetDate)->addHours(9)->format('Y-m-d H:i:s');
    }
}
