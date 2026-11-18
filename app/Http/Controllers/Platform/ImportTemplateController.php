<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Shared\Exports\ImportTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /import-templates/{type}: the Excel template of an import screen (applicants | high-schools).
 * Needs the same "import" permission as the import itself.
 */
class ImportTemplateController extends Controller
{
    public function __invoke(Request $request, string $type): BinaryFileResponse
    {
        abort_unless(in_array($type, ImportTemplate::TYPES, true), 404);

        $model = $type === 'applicants' ? Applicant::class : HighSchool::class;
        abort_unless($request->user()->can('import', $model), 403);

        $template = new ImportTemplate($type);

        return Excel::download($template, $template->fileName());
    }
}
