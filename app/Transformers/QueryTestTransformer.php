<?php

namespace App\Transformers;

use League\Fractal\TransformerAbstract;

class QueryTestTransformer extends TransformerAbstract
{
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform($sites)
    {
        return [
            'site_name' => $sites->site_name,
            'classification' => $sites->classification,
            'comment' => $sites->comment,
            'link' => $sites->link,
        ];
    }
}
