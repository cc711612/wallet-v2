<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Response;

abstract class ApiController extends Controller
{
    /**
     * 取得共用回應物件。
     *
     * @return Response
     */
    protected function response(): Response
    {
        return app(Response::class);
    }
}
