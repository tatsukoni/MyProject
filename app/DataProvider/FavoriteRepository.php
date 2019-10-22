<?php

namespace App\DataProvider;

use App\Models\SiteFavorite;

class FavoriteRepository implements FavoriteRepositoryInterface
{
    private $siteFavorite;

    public function __construct(SiteFavorite $siteFavorite)
    {
        $this->siteFavorite = $siteFavorite;
    }

    public function switch(int $siteId) : int
    {
        return \DB::transaction(
            function () use ($siteId) {
                if ($this->siteFavorite->where('site_id', $siteId)->exists()) {
                    $this->siteFavorite->where('site_id', $siteId)->delete();
                    return 0;
                } else {
                    $this->siteFavorite->create([
                        'site_id' => $siteId
                    ]);
                    return 1;
                }
            }
        );
    }
}