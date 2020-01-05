<?php

namespace App\Domain\JobTemporariness\JobDetail\Processors;

use App\Models\Job;
use App\Models\JobDetailWritingItem;
use Illuminate\Validation\Rule;

class WritingProcessor extends ProcessorAbstract implements ProcessorInterface
{
    // Jobモデルに定義されているリレーションメソッド名
    const RELATION = 'jobDetailWritingItem';

    public function getValidationRule(int $jobType): array
    {
        $rules = [
            // 記事のテーマID
            'theme' => [
                'required',
                'integer',
                Rule::in(array_keys(JobDetailWritingItem::SELECT_THEMES))
            ],

            // 記事のテーマ(その他)
            'theme_other' => [
                'required_if:theme,' . JobDetailWritingItem::SELECT_THEME_OTHER,
                'max:30'
            ],

            // 1記事あたりの文字数
            'character_count' => [
                'required',
                'integer',
                'min:1'
            ],

            // 文末表現ID
            'end_of_sentence' => [
                'required',
                'integer',
                Rule::in(array_keys(JobDetailWritingItem::END_OF_SENTENCES))
            ],

            // 想定読者
            'assumed_readers' => [
                'nullable',
                'max:50'
            ]
        ];

        if ($jobType === Job::TYPE_PROJECT) {
            $rules += [
                // 1人あたりに依頼する記事数(記事数)
                'article_count' => [
                    'required',
                    'integer',
                    'min:1'
                ],

                // 1人あたりに依頼する記事数(期間ID)
                'article_count_period' => [
                    'required',
                    'integer',
                    Rule::in(array_keys(JobDetailWritingItem::ARTICLE_COUNT_PERIODS))
                ]
            ];
        }

        $commonRules = $this->getCommonValidation();
        return array_merge($rules, $commonRules);
    }

    public function getRecord(int $jobType, array $value): array
    {
        $record = [];
        $record += [
            self::RELATION => [
                'theme' => $value['theme'] ?? null,
                'theme_other' => $value['theme_other'] ?? null,
                'character_count' => $value['character_count'] ?? null,
                'end_of_sentence' => $value['end_of_sentence'] ?? null,
                'assumed_readers' => $value['assumed_readers'] ?? null,
                'article_count' => null,
                'article_count_period' => null
            ]
        ];
        if ($jobType === Job::TYPE_PROJECT) {
            // プロジェクトの場合のみ有効なカテゴリ
            $record[self::RELATION]['article_count'] = $value['article_count'] ?? null;
            $record[self::RELATION]['article_count_period'] = $value['article_count_period'] ?? null;
            // カテゴリが「ライティング」に変更された場合、下記を明示的にnullに更新する必要がある
            $record += [
                'tradeParameter' => [
                    'workable_time_id' => null
                ]
            ];
        }
        $commonRecord = $this->getCommonRecord($value);
        return array_merge($record, $commonRecord);
    }

    public function getDetails(Job $job): array
    {
        $detail = $job->{self::RELATION};
        $values = [
            'theme' => $detail->theme,
            'theme_other' => $detail->theme_other,
            'character_count' => $detail->character_count,
            'end_of_sentence' => $detail->end_of_sentence,
            'assumed_readers' => $detail->assumed_readers,
            'article_count' => $detail->article_count,
            'article_count_period' => $detail->article_count_period
        ];
        $commonValues = $this->getCommonDetails($job);
        return array_merge($values, $commonValues);
    }
}
