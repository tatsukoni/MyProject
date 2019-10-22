<?php

namespace App\Mail\Mails\Admin;

use App\Mail\Mailable;

class MonthlyWithdrawalReport extends Mailable
{
    public $attachCsv = false;
    public $resultCode;
    public $errorMessage;

    const ERROR_MESSAGE = [
        1 => '入力された指定日に誤りがあります。指定日は「Y-m-d」もしくは「Y-m-d H:i:s」の形式で、開始日を終了日より前にしてください。',
        2 => '該当するデータが存在しませんでした。',
        3 => 'bank_idの取得に失敗したため、処理が正常に行われませんでした。',
    ];

    /**
     * NoticeUpdateAntisocialState constructor.
     * @param $fileData
     * @param string $fileName
     * @param array $options
     */
    public function __construct(
        int $resultCode,
        $fileData,
        string $fileName,
        array $options
    ) {
        parent::__construct();

        $this->textView = 'emails.admin.monthly_withdrawal_report';
        $this->addressTo = config('shufti.admin_mail');
        $this->resultCode = $resultCode;

        if ($this->resultCode === 4 && (! is_null($fileData))) {
            $this->rawAttachments = [
                [
                    'data' => $fileData,
                    'name' => $fileName,
                    'options' => $options
                ]
            ];
            $this->attachCsv = true;
            $this->subject = '処理に成功しました';
        } else {
            $this->subject = '処理に失敗しました';
            $this->errorMessage = self::ERROR_MESSAGE[$this->resultCode];
        }
    }
}
