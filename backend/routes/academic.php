<?php
declare(strict_types=1);
use App\Infrastructure\Authentication\LocalSessionAuthentication;
use App\Http\Controllers\Academic\FoundationController;
use Symfony\Component\HttpFoundation\Request;
/** Explicit composition port for the isolated adapter; no Laravel kernel/listener is installed. */
return static function(LocalSessionAuthentication $authentication,FoundationController $controller):Closure {
    return static fn(Request $request)=>$authentication->handle($request,fn($actor)=>$controller->handle($request,$actor));
};
