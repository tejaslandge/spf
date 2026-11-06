<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    <style>
        /* -------------------------------------------------------
           Email Client Responsive CSS
           ------------------------------------------------------- */
        @media only screen and (max-width: 620px) {
            .u-row .u-col {
                width: 100% !important;
            }
            .u-image img, .u-text blockquote, .u-text pre {
                max-width: 100% !important;
            }
            .u-row-collapse .u-col {
                padding: 0;
            }
            .u-btn-uuid {
                display: inline-block !important;
                padding: 10px 20px !important;
                font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                font-size: 16px;
                line-height: 1.5;
                margin: 0;
                padding: 10px 20px;
                border-radius: 6px;
                background-color: #1a365d;
                color: #ffffff;
                text-decoration: none;
                border: none;
                cursor: pointer;
            }
            .u-btn-uuid:hover {
                background-color: #0d2445;
            }
        }

        /* -------------------------------------------------------
           Body and general reset
           ------------------------------------------------------- */
        body {
            margin: 0 !important;
            padding: 0 !important;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            line-height: 1.6;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            background-color: #f4f4f4;
        }

        /* -------------------------------------------------------
           Main container
           ------------------------------------------------------- */
        .u-row {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .u-column {
            vertical-align: top;
            display: table-cell;
            width: 100%;
        }

        /* -------------------------------------------------------
           Navigation/ Header
           ------------------------------------------------------- */
        .u-header {
            background-color: #1a365d;
            margin: -20px -20px 20px;
            padding: 20px;
            text-align: center;
        }

        .u-header-img {
            max-width: 200px;
            margin: 0 auto;
            display: block;
        }

        .u-header-text {
            color: #ffffff;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 1px;
            margin: 0;
        }

        /* -------------------------------------------------------
           Content area
           ------------------------------------------------------- */
        .u-content {
            background-color: #ffffff;
            margin: 0 -20px 20px;
            padding: 20px;
            border-radius: 4px;
        }

        .u-section {
            background-color: #f7f7f7;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }

        /* -------------------------------------------------------
           Buttons
           ------------------------------------------------------- */
        .u-btn {
            display: inline-block;
            padding: 10px 20px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            margin: 0;
            padding: 10px 20px;
            border-radius: 6px;
            background-color: #1a365d;
            color: #ffffff;
            text-decoration: none;
            border: none;
            cursor: pointer;
            text-align: center;
        }

        .u-btn:hover {
            background-color: #0d2445;
        }

        .u-btn-secondary {
            background-color: #6c757d;
        }

        .u-btn-secondary:hover {
            background-color: #5a6268;
        }

        /* -------------------------------------------------------
           Typography
           ------------------------------------------------------- */
        .u-heading-1 {
            color: #1a365d;
            font-size: 28px;
            font-weight: bold;
            margin-top: 0;
            margin-bottom: 20px;
            letter-spacing: 1px;
            text-align: center;
        }

        .u-heading-2 {
            color: #1a365d;
            font-size: 20px;
            font-weight: bold;
            margin-top: 0;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }

        .u-text {
            color: #333333;
            font-size: 16px;
            line-height: 1.6;
            margin: 0 0 16px;
        }

        .u-text-muted {
            color: #666666;
            font-size: 14px;
        }

        /* -------------------------------------------------------
           Footer
           ------------------------------------------------------- */
        .u-footer {
            background-color: #f7f7f7;
            border-top: 1px solid #e0e0e0;
            margin: -20px -20px 0;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #999999;
        }

        .u-footer a {
            color: #1a365d;
            text-decoration: underline;
        }

        /* -------------------------------------------------------
           Table utilities
           ------------------------------------------------------- */
        .u-table {
            width: 100%;
            border-collapse: collapse;
        }

        .u-table-cell {
            padding: 8px;
            vertical-align: top;
        }
    </style>
</head>
<body>
    <div class="u-row">
        <!-- Header -->
        <div class="u-header">
            <a href="{{ config('app.url') }}">
                {{ $logoHtml ?? '' }}
            </a>
            <h1 class="u-header-text">{{ $companyName ?? config('app.name') }}</h1>
        </div>

        <!-- Main Content -->
        <div class="u-content">
            @yield('content')
        </div>

        <!-- Footer -->
        <div class="u-footer">
            <p>
                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </p>
            <p>
                {{ config('mail.from.address') }}<br/>
                If you no longer wish to receive these emails, <a href="{{ route('login') }}">unsubscribe</a>.
            </p>
        </div>
    </div>
</body>
</html>