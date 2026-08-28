<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

final class SpaController extends Controller
{
    public function __invoke(): Response
    {
        $index = public_path('app/index.html');

        if (! is_file($index)) {
            return response('Interface FANABE absente. En local, lancez `npm run dev` dans /web.', 503);
        }

        return response(File::get($index), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
