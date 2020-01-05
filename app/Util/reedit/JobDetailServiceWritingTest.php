<?php

namespace Tests\Unit\Domain\JobTemporariness\JobDetail;

use App\Domain\JobTemporariness\JobDetail\JobDetailService;
use App\Models\Job;
use App\Models\JobDetailWritingItem;
use App\Models\Temporariness;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * ライティングカテゴリのテスト
 */
class JobDetailServiceWritingTest extends TestCase
{
    use DatabaseTransactions, Common;

    const CATEGORY_ID = 13;     // キャッチコピー・ネーミング

    /**
     * @return array ライティングの仕事で登録できる有効データ
     */
    private function getValidData()
    {
        $validWritingData = [
            'theme' => 2,
            'theme_other' => null,
            'character_count' => 1000,
            'article_count' => 5,
            'article_count_period' => 1,
            'end_of_sentence' => 1,
            'assumed_readers' => '美容に興味のある30代の女性',
        ];
        $validCommonData = $this->getValidCommonData();
        return array_merge($validWritingData, $validCommonData);
    }

    public function provideParameters()
    {
        $parameters = [
            // getValidDataのままであればOK
            [true, $this->getParameter([])],



            // [記事のテーマ]
            // テーマIDが設定されていないためNG
            [false, $this->getParameter([['key' => 'theme', 'value' => null]]), 'theme'],

            // テーマIDが無効のためNG
            [false, $this->getParameter([['key' => 'theme', 'value' => -1]]), 'theme'],

            // テーマIDが「その他」のとき、その他の内容も正しいのでOK
            [true, $this->getParameter([
                ['key' => 'theme', 'value' => JobDetailWritingItem::SELECT_THEME_OTHER],
                ['key' => 'theme_other', 'value' => str_random(30)]
            ])],

            // テーマIDが「その他」のとき、その他の内容を入力していないためNG
            [false, $this->getParameter([
                ['key' => 'theme', 'value' => JobDetailWritingItem::SELECT_THEME_OTHER],
                ['key' => 'theme_other', 'value' => null]
            ]), 'theme_other'],

            // テーマIDが「その他」のとき、その他の内容が文字数オーバーのためNG
            [false, $this->getParameter([
                ['key' => 'theme', 'value' => JobDetailWritingItem::SELECT_THEME_OTHER],
                ['key' => 'theme_other', 'value' => str_random(31)]
            ]), 'theme_other'],



            // [1記事あたりの文字数]
            // 設定されていないためNG
            [false, $this->getParameter([['key' => 'character_count', 'value' => null]]), 'character_count'],

            // 負の値のためNG
            [false, $this->getParameter([['key' => 'character_count', 'value' => -1]]), 'character_count'],



            // [1人あたりに依頼する記事数]
            // タスクの場合、設定されていなくてもOK
            [true, $this->getParameter([['key' => 'article_count', 'value' => null]]), null, Job::TYPE_TASK],

            // プロジェクトの場合、設定されていないためNG
            [false, $this->getParameter([['key' => 'article_count', 'value' => null]]), 'article_count'],

            // 負の値のためNG
            [false, $this->getParameter([['key' => 'article_count', 'value' => -1]]), 'article_count'],



            // [期間ID]
            // 有効のためOK
            [
                true,
                $this->getParameter(
                    [['key' => 'article_count_period', 'value' => array_rand(JobDetailWritingItem::ARTICLE_COUNT_PERIODS)]]
                )
            ],

            // タスクの場合、設定されていなくてもOK
            [true, $this->getParameter([['key' => 'article_count_period', 'value' => null]]), null, Job::TYPE_TASK],

            // プロジェクトの場合、設定されていないためNG
            [false, $this->getParameter([['key' => 'article_count_period', 'value' => null]]), 'article_count_period'],

            // 無効のためNG
            [false, $this->getParameter([['key' => 'article_count_period', 'value' => 4]]), 'article_count_period'],



            // [文末表現ID]
            // 有効のためOK
            [
                true,
                $this->getParameter(
                    [['key' => 'end_of_sentence', 'value' => array_rand(JobDetailWritingItem::END_OF_SENTENCES)]]
                )
            ],

            // 設定されていないためNG
            [false, $this->getParameter([['key' => 'end_of_sentence', 'value' => null]]), 'end_of_sentence'],

            // 無効のためNG
            [false, $this->getParameter([['key' => 'end_of_sentence', 'value' => 4]]), 'end_of_sentence'],



            // [想定読者]
            // 未設定でもOK
            [true, $this->getParameter([['key' => 'assumed_readers', 'value' => null]])],

            // 文字数内のためOK
            [true, $this->getParameter([['key' => 'assumed_readers', 'value' => str_random(50)]])],

            // 文字数オーバーのためNG
            [false, $this->getParameter([['key' => 'assumed_readers', 'value' => str_random(51)]]), 'assumed_readers'],
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
            'jobDetailWritingItem' => [
                'theme' => $parameter['theme'],
                'theme_other' => $parameter['theme_other'],
                'character_count' => $parameter['character_count'],
                'end_of_sentence' => $parameter['end_of_sentence'],
                'assumed_readers' => $parameter['assumed_readers'],
                'article_count' => null,
                'article_count_period' => null,
            ],
            'job' => [
                'teachme' => $parameter['teachme'],
                'recommend' => $parameter['recommend'],
                'prohibitions' => $parameter['prohibitions'],
                'pr_message' => $parameter['pr_message'],
            ]
        ];
        if ($jobTyoe === Job::TYPE_PROJECT) {
            // プロジェクトの場合のみ有効なカテゴリ
            $expectRecord['jobDetailWritingItem']['article_count'] = $parameter['article_count'];
            $expectRecord['jobDetailWritingItem']['article_count_period'] = $parameter['article_count_period'];
            // カテゴリが「ライティング」に変更された場合、下記を明示的にnullに更新する必要がある
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
        $detail = factory(JobDetailWritingItem::class)->create(['job_id' => $job->id]);

        // Act
        $values = $service->getStep2Values($job);

        // Assert
        $this->assertSame(
            [
                'theme' => $detail->theme,
                'theme_other' => $detail->theme_other,
                'character_count' => $detail->character_count,
                'end_of_sentence' => $detail->end_of_sentence,
                'assumed_readers' => $detail->assumed_readers,
                'article_count' => $detail->article_count,
                'article_count_period' => $detail->article_count_period,
                'teachme' => $job->teachme,
                'recommend' => $job->recommend,
                'prohibitions' => $job->prohibitions,
                'pr_message' => $job->pr_message
            ],
            $values
        );
    }
}
