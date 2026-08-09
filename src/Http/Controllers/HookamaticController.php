<?php

namespace VanDmade\Hookamatic\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Exception;

class HookamaticController extends BaseController
{

    public function success($data, int $status = 200)
    {
        return response()->json($data, $status);
    }

    public function error(string $message, int $status = 500)
    {
        return response()->json([
            'message' => $message,
        ], $status);
    }

}