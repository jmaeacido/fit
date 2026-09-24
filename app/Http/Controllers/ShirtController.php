<?php
namespace App\Http\Controllers;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class ShirtController extends Controller
{
    public function page() {
        return view('app', ['settings' => array_merge(config('shirts'), [
            'authenticated' => Auth::check(),
        ])]);
    }
    public function store(Request $request) {
        $data = $this->selectionData($request);
        $submission = Submission::firstOrCreate(['request_key' => $data['request_key']], $data);
        if ($submission->name !== $data['name'] || $submission->display_name !== $data['display_name'] || $submission->display_number !== $data['display_number'] || $submission->size !== $data['size'] || $submission->designs !== $data['designs']) {
            return response()->json(['message' => 'This request was already submitted. Refresh to start a new selection.'], 409);
        }
        return response()->json(['id' => $submission->id], $submission->wasRecentlyCreated ? 201 : 200);
    }
    private function selectionData(Request $request, bool $requireKey = true): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'display_name' => ['required', 'string', 'max:150'],
            'display_number' => ['required', 'string', 'regex:/^[0-9]{2}$/D'],
            'size' => ['required', Rule::in(config('shirts.sizes'))],
            'designs' => ['required', 'array', 'min:1', 'max:2', function ($attribute, $value, $fail) {
                if (!is_array($value) || !in_array('2', $value, true)) {
                    $fail('Design 2 must be selected.');
                }
            }],
            'designs.*' => ['required', 'string', 'distinct', Rule::in(['1', '2'])],
            ...($requireKey ? ['request_key' => ['required', 'uuid']] : []),
        ]);
        sort($data['designs']);
        return $data;
    }
    public function save(Request $request) {
        $data = $this->selectionData($request);
        // The unguessable browser key grants access to this entry only; never expose it in listings.
        $submission = Submission::updateOrCreate(['request_key' => $data['request_key']], $data);
        return response()->json(['id' => $submission->id], $submission->wasRecentlyCreated ? 201 : 200);
    }
    public function update(Request $request, Submission $submission) {
        $submission->update($this->selectionData($request, false));
        return response()->json(['id' => $submission->id]);
    }
    public function login(Request $request) {
        $request->merge(['login' => $request->input('login', $request->input('email'))]);
        $data = $request->validate(['login' => ['required', 'string', 'max:255'], 'password' => ['required', 'string']]);
        $field = str_contains($data['login'], '@') ? 'email' : 'username';
        $credentials = [$field => $data['login'], 'password' => $data['password']];
        if (!Auth::attempt($credentials)) throw ValidationException::withMessages(['login' => 'The username, email, or password is incorrect.']);
        $request->session()->regenerate();
        return response()->json(['ok' => true]);
    }
    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['ok' => true]);
    }
    private function filtered(Request $request) {
        $filters = $request->validate(['size' => ['nullable', Rule::in(config('shirts.sizes'))], 'design' => ['nullable', Rule::in(['1', '2'])]]);
        return Submission::query()
            ->when($filters['size'] ?? null, fn ($q, $size) => $q->where('size', $size))
            ->when($filters['design'] ?? null, fn ($q, $design) => $q->whereJsonContains('designs', (string) $design));
    }
    public function index(Request $request) {
        $query = $this->filtered($request);
        return response()->json([
            'submissions' => (clone $query)->latest('id')->paginate(20),
            'total' => (clone $query)->count(),
            'sizes' => (clone $query)->selectRaw('size, COUNT(*) as total')->groupBy('size')->pluck('total', 'size'),
            'designs' => collect(['1', '2'])->mapWithKeys(fn ($id) => [$id => (clone $query)->whereJsonContains('designs', $id)->count()]),
            'authenticated' => Auth::check(),
        ]);
    }
    public function export(Request $request) {
        $query = $this->filtered($request);
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Full name', 'Display name', 'Display number', 'Size', 'Designs', 'Submitted at (UTC)'], ',', '"', '');
            foreach ($query->orderBy('id')->cursor() as $row) {
                $name = preg_match('/^[\s]*[=+@\-]/u', $row->name) ? "'".$row->name : $row->name;
                $displayName = preg_match('/^[\s]*[=+@\-]/u', $row->display_name ?? '') ? "'".$row->display_name : $row->display_name;
                fputcsv($out, [$row->id, $name, $displayName, $row->display_number, $row->size, implode('; ', array_map(fn ($id) => 'Design '.$id, $row->designs)), $row->created_at->toDateTimeString()], ',', '"', '');
            }
            fclose($out);
        }, 'shirt-submissions-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
