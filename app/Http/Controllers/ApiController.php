<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Response;

abstract class ApiController extends Controller
{
    protected function response(): Response
    {
        return app(Response::class);
    }
}
