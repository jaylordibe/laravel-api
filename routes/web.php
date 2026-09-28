<?php

use App\Utils\FileUtil;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    $frontendUrl = config('custom.app_frontend_url');

    // A pure API has no frontend to send visitors to; answer 404 rather than a 500.
    abort_if(empty($frontendUrl), 404);

    return new RedirectResponse($frontendUrl);
});

if (app()->environment('local')) {
    Route::prefix('sample')->group(function () {
        // Sample PDF export
        Route::get('/pdf-export', function () {
            $data = [
                'title' => 'Sample PDF Export',
                'content' => 'This is a sample content for PDF export.',
            ];
            $fileName = 'sample_pdf_export_' . Str::random() . '.pdf';
            SnappyPdf::loadView('pdf/test', $data)->save(FileUtil::getStoragePath($fileName));
//            SnappyPdf::loadView('pdf/test', $data)->download($fileName);
        });
    });
}
