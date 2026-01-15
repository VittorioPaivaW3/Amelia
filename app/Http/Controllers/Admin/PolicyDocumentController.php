<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolicyDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Smalot\PdfParser\Parser;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PolicyDocumentController extends Controller
{
    private const SECTORS = ['rh', 'mkt', 'juridico'];

    public function index(): View
    {
        $documents = PolicyDocument::query()
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('sector');

        return view('admin.policies', [
            'documents' => $documents,
            'sectors' => self::SECTORS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sector' => ['required', 'in:'.implode(',', self::SECTORS)],
            'title' => ['nullable', 'string', 'max:255'],
            'document' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $file = $request->file('document');
        if (! $file || ! $file->isValid()) {
            return back()->withErrors(['document' => 'Arquivo invalido.']);
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($file->getPathname());
            $text = $this->normalizePolicyText($pdf->getText());
        } catch (\Throwable $e) {
            return back()->withErrors(['document' => 'Nao foi possivel ler o PDF.']);
        }
        if ($text === '') {
            return back()->withErrors(['document' => 'Nao foi possivel extrair texto do PDF.']);
        }

        $sector = $data['sector'];
        $filename = (string) Str::uuid().'.pdf';
        $path = $file->storeAs('policies/'.$sector, $filename);

        PolicyDocument::query()
            ->where('sector', $sector)
            ->update(['is_active' => false]);

        PolicyDocument::create([
            'sector' => $sector,
            'title' => $data['title'] ?: $file->getClientOriginalName(),
            'file_path' => $path,
            'text_content' => $text,
            'is_active' => true,
            'uploaded_by' => $request->user()?->id,
        ]);

        return back()->with('status', 'Politica atualizada.');
    }

    public function view(PolicyDocument $policyDocument): StreamedResponse
    {
        $disk = Storage::disk(config('filesystems.default', 'local'));
        if (! $disk->exists($policyDocument->file_path)) {
            abort(404);
        }

        $filename = $this->policyFilename($policyDocument);
        return $disk->response($policyDocument->file_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function download(PolicyDocument $policyDocument): StreamedResponse
    {
        $disk = Storage::disk(config('filesystems.default', 'local'));
        if (! $disk->exists($policyDocument->file_path)) {
            abort(404);
        }

        $filename = $this->policyFilename($policyDocument);
        return $disk->download($policyDocument->file_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function normalizePolicyText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = array_map('trim', explode("\n", $text));
        $lines = array_values(array_filter($lines, fn ($line) => $line !== ''));
        if (empty($lines)) {
            return '';
        }

        $text = implode("\n", $lines);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }

    private function policyFilename(PolicyDocument $policyDocument): string
    {
        $base = trim((string) ($policyDocument->title ?? ''));
        if ($base === '') {
            $base = 'politica-'.$policyDocument->sector;
        }

        $base = Str::slug(Str::ascii($base));
        if ($base === '') {
            $base = 'politica-'.$policyDocument->sector;
        }

        return $base.'.pdf';
    }
}
