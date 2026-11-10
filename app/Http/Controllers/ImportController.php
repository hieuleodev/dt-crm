<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportDataRequest;
use App\Models\User;
use App\Services\Import\ImportRecords;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManager($request);

        return Inertia::render('Imports/Index');
    }

    public function store(
        ImportDataRequest $request,
        ImportRecords $imports,
    ): RedirectResponse {
        $user = $request->user();
        $data = $request->validated();
        $file = $request->file('file');

        abort_unless($user instanceof User && $file instanceof UploadedFile, 403);

        $result = $imports->handle($user, (string) $data['type'], $file);
        $message = "Đã nhập {$result['created']} dòng; bỏ qua {$result['duplicates']} dòng trùng.";

        return to_route('imports.index')
            ->with('success', $message)
            ->with('import_result', $result);
    }

    public function template(
        Request $request,
        ImportRecords $imports,
        SpreadsheetReader $reader,
        string $type,
        string $format,
    ): StreamedResponse {
        $this->authorizeManager($request);

        $headers = $imports->headers($type);
        $contents = match ($format) {
            'csv' => $reader->csvTemplate($headers),
            'xlsx' => $reader->xlsxTemplate($headers),
            default => abort(404),
        };
        $contentType = $format === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            "{$type}-template.{$format}",
            ['Content-Type' => $contentType],
        );
    }

    private function authorizeManager(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_active && $user->isManager(), 403);

        return $user;
    }
}
