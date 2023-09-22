<?php
declare(strict_types=1);

use App\Utils\Session;
use Nyholm\Psr7\Response;

Session::getInstance()->logout();

return new Response(302, ['Location' => 'index.php']);
