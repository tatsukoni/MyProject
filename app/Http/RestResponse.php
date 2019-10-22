<?php

namespace App\Http;

use League\Fractal\Manager;
use League\Fractal\Resource\Collection;

trait RestResponse
{
    public function sendSuccess($status, $response = [])
    {
        return response()->json(
            $response,
            $status,
            [],
            JSON_PRETTY_PRINT
        );
    }

    public function formatCollection($collection)
    {
        $collection = new Collection($collection, $this->transformer);
        $collection = $this->fractal->createData($collection);
        $collection = $collection->toArray();

        return $collection;
    }
}