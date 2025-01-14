<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title>Test PDF Export Template</title>
        <link href="{{ public_path('css/bootstrap.min.css') }}" rel="stylesheet" media="all">
        <style media="all">
            .page-break {
                page-break-after: always;
            }
        </style>
    </head>
    <body>
        <div class="container-fluid">
            <div class="row">
                <div class="col-xs-12 text-center">
                    <h2>{{ $title }}</h2>
                    <h4>{{ $content }}</h4>
                </div>
            </div>
        </div>
    </body>
</html>
