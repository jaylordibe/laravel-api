<?php

use App\Utils\FileUtil;
use App\Utils\ResponseUtil;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    return ResponseUtil::notFound();
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
