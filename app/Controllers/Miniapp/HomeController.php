<?php

/*
 * Copyright (c) 2025. Vitaliy Kamelin <v.kamelin@gmail.com>
 */

declare(strict_types=1);

namespace App\Controllers\Miniapp;

use App\Helpers\View;
use Psr\Http\Message\ResponseInterface as Res;
use Psr\Http\Message\ServerRequestInterface as Req;
use PDO;

class HomeController
{

    public function __construct(private PDO $db)
    {
    }

    public function index(Req $req, Res $res): Res
    {
        return View::render($res, 'miniapp/home.php', [], 'layouts/miniapp.php');
    }

}