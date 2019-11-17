<?php

namespace App\Transformers\Admin;

use League\Fractal;

/**
 * Laravel Fractal
 * Presentation and transformation layer for complex data output.
 *
 * @ref https://github.com/spatie/laravel-fractal
 */
class JobCountTransformer extends Fractal\TransformerAbstract
{
    /**
     * @param array $jobCounts
     * 
     * @return array
     */
    public function transform(Array $jobCounts): array
    {
        return [
            'id' => '',
            'waitingJobCount' => $jobCounts['waitingJobCount'],
            'checkedAntisocialCount' => $jobCounts['checkedAntisocialCount'],
            'unCheckedAntisocialCount' => $jobCounts['unCheckedAntisocialCount'],
        ];
    }
}
