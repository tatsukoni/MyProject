<?php

namespace App\DataProvider;

interface FavoriteRepositoryInterface
{
    public function switch(int $siteId) : int;
}
