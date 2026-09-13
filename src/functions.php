<?php

declare(strict_types=1);

namespace Naf\Session;

use Naf\Session\Core\Session;
use function Naf\app;

function session(): Session
{
    return app()->container()->get(Session::class);
}