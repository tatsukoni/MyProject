<?php
namespace App\Transformers;

use App\Models\Site;
use League\Fractal\TransformerAbstract;

class TestTransformer extends TransformerAbstract
{
    // transformerを使うことで、任意の形のAPIに整形できる。
    public function transform(Site $site)
    {
        return [
            'siteName' => $site->site_name,
            'classification' => $site->classification,
            'detail' => [
                'link' => $site->siteDetail->link,
                'comment' => $site->siteDetail->comment,
            ],
        ];
    }
}
