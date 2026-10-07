<?php

use App\Http\Controllers\AcademicController;
use App\Http\Controllers\EducationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/api/session', fn () => response()->json(['csrf_token' => csrf_token()]));
Route::get('/local-assets/{asset}', function (Request $request, string $asset) {
    $file = public_path('client/assets/'.$asset);
    abort_unless(is_file($file), 404);
    $headers = ['Content-Type' => str_ends_with($asset, '.js') ? 'application/javascript; charset=utf-8' : 'text/css; charset=utf-8', 'X-Content-Type-Options' => 'nosniff', 'Vary' => 'Accept-Encoding'];
    if (str_contains($request->header('Accept-Encoding', ''), 'gzip') && is_file($file.'.gz')) {
        $file .= '.gz';
        $headers['Content-Encoding'] = 'gzip';
    }

    return response()->file($file, $headers);
})->where('asset', '[a-zA-Z0-9_-]+\.(?:js|css)')->withoutMiddleware('web');
Route::post('/api/login', [AcademicController::class, 'login'])->middleware('throttle:10,1');
Route::middleware('auth')->group(function () {
    Route::post('/api/logout', [AcademicController::class, 'logout']);
    Route::get('/api/academic', [AcademicController::class, 'state']);
    Route::get('/api/education', [EducationController::class, 'state']);
    Route::post('/api/education/{action}', [EducationController::class, 'command']);
    Route::get('/api/education/files/{kind}/{id}', [EducationController::class, 'download'])->where('kind', 'material|submission')->whereNumber('id');
    Route::post('/api/academic/{command}', [AcademicController::class, 'command']);
});
Route::get('/{path?}', function () {
    $file = public_path('client/index.html');
    abort_unless(is_file($file), 503, 'Build the React client using npm run build in frontend.');

    $html = str_replace('/client/assets/', '/local-assets/', file_get_contents($file));

    return response($html)->header('Content-Type', 'text/html; charset=utf-8')->header('Content-Length', (string) strlen($html));
})->where('path', '(?!api(?:/|$)).*');
