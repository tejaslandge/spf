<?php

namespace App\Http\Controllers;

use App\Models\KycDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycDocumentController extends Controller
{
    public function download(KycDocument $document): StreamedResponse
    {
        abort_unless(request()->user() !== null, 403);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }
}
