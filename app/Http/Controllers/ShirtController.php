<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShirtController extends Controller
{
    public function page()
    {
        return view('app', ['settings' => array_merge(config('shirts'), [
            'authenticated' => Auth::check(),
            'timezone' => config('app.timezone'),
        ])]);
    }

    public function store(Request $request)
    {
        $data = $this->selectionData($request);
        $submission = Submission::firstOrCreate(['request_key' => $data['request_key']], $data);
        if ($submission->name !== $data['name'] || $submission->display_name !== $data['display_name'] || $submission->display_number !== $data['display_number'] || $submission->design_1_display_name !== ($data['design_1_display_name'] ?? null) || $submission->design_1_display_number !== ($data['design_1_display_number'] ?? null) || $submission->size !== $data['size'] || $submission->designs !== $data['designs']) {
            return response()->json(['message' => 'This request was already submitted. Refresh to start a new selection.'], 409);
        }

        return response()->json(['id' => $submission->id], $submission->wasRecentlyCreated ? 201 : 200);
    }

    private function selectionData(Request $request, bool $requireKey = true): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'display_name' => ['required', 'string', 'max:150'],
            'display_number' => ['required', 'string', 'regex:/^[0-9]{2}$/D'],
            'design_1_display_name' => ['nullable', 'string', 'max:150', Rule::requiredIf(fn () => in_array('1', (array) $request->input('designs', []), true))],
            'design_1_display_number' => ['nullable', 'string', 'regex:/^[0-9]{2}$/D', Rule::requiredIf(fn () => in_array('1', (array) $request->input('designs', []), true))],
            'size' => ['required', Rule::in(config('shirts.sizes'))],
            'designs' => ['required', 'array', 'min:1', 'max:2', function ($attribute, $value, $fail) {
                if (! is_array($value) || ! in_array('2', $value, true)) {
                    $fail('Design 2 must be selected.');
                }
            }],
            'designs.*' => ['required', 'string', 'distinct', Rule::in(['1', '2'])],
            ...($requireKey ? ['request_key' => ['required', 'uuid']] : []),
        ]);
        sort($data['designs']);
        if (! in_array('1', $data['designs'], true)) {
            $data['design_1_display_name'] = null;
            $data['design_1_display_number'] = null;
        }

        return $data;
    }

    public function save(Request $request)
    {
        $data = $this->selectionData($request);
        if ($request->boolean('paid', true)) {
            $data['paid_at'] = now();
        }
        // The unguessable browser key grants access to this entry only; never expose it in listings.
        $submission = Submission::updateOrCreate(['request_key' => $data['request_key']], $data);

        return response()->json(['id' => $submission->id, 'paid_at' => $submission->paid_at?->toISOString()], $submission->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, Submission $submission)
    {
        $submission->update($this->selectionData($request, false));

        return response()->json(['id' => $submission->id]);
    }

    public function updatePaid(Request $request, Submission $submission)
    {
        $data = $request->validate(['paid' => ['required', 'boolean']]);
        $submission->update(['paid_at' => $data['paid'] ? now() : null]);

        return response()->json([
            'id' => $submission->id,
            'paid_at' => $submission->paid_at?->toISOString(),
        ]);
    }

    public function destroy(Submission $submission)
    {
        $submission->delete();

        return response()->json(['ok' => true]);
    }

    public function login(Request $request)
    {
        $request->merge(['login' => $request->input('login', $request->input('email'))]);
        $data = $request->validate(['login' => ['required', 'string', 'max:255'], 'password' => ['required', 'string']]);
        $field = str_contains($data['login'], '@') ? 'email' : 'username';
        $credentials = [$field => $data['login'], 'password' => $data['password']];
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['login' => 'The username, email, or password is incorrect.']);
        }
        $request->session()->regenerate();

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    private function filtered(Request $request)
    {
        $filters = $request->validate(['size' => ['nullable', Rule::in(config('shirts.sizes'))], 'design' => ['nullable', Rule::in(['1', '2'])]]);

        return Submission::query()
            ->when($filters['size'] ?? null, fn ($q, $size) => $q->where('size', $size))
            ->when($filters['design'] ?? null, fn ($q, $design) => $q->whereJsonContains('designs', (string) $design));
    }

    public function index(Request $request)
    {
        $query = $this->filtered($request);
        $designs = collect(['1', '2'])->mapWithKeys(fn ($id) => [$id => (clone $query)->whereJsonContains('designs', $id)->count()]);
        $sizes = collect();
        foreach (['1', '2'] as $id) {
            $counts = (clone $query)
                ->whereJsonContains('designs', $id)
                ->selectRaw('size, COUNT(*) as total')
                ->groupBy('size')
                ->pluck('total', 'size');

            foreach ($counts as $size => $count) {
                $sizes[$size] = ($sizes[$size] ?? 0) + $count;
            }
        }
        $paidShirts = collect(['1', '2'])->sum(fn ($id) => (clone $query)->whereNotNull('paid_at')->whereJsonContains('designs', $id)->count());
        $paidAmount = $paidShirts * 350;
        $totalAmount = $designs->sum() * 350;

        return response()->json([
            'submissions' => (clone $query)->latest('id')->paginate(20),
            'total' => (clone $query)->count(),
            'shirts' => $designs->sum(),
            'sizes' => $sizes,
            'designs' => $designs,
            'authenticated' => Auth::check(),
            'paid' => (clone $query)->whereNotNull('paid_at')->count(),
            'paid_amount' => $paidAmount,
            'unpaid_amount' => $totalAmount - $paidAmount,
        ]);
    }

    public function export(Request $request)
    {
        $query = $this->filtered($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Full name', 'Design 1 display name', 'Design 1 display number', 'Design 2 display name', 'Design 2 display number', 'Size', 'Designs', 'Amount', 'Paid amount', 'Unpaid amount', 'Payment status', 'Marked paid at (GMT+8)', 'Submitted at (GMT+8)'], ',', '"', '');
            foreach ($query->orderBy('id')->cursor() as $row) {
                $name = preg_match('/^[\s]*[=+@\-]/u', $row->name) ? "'".$row->name : $row->name;
                $displayName = preg_match('/^[\s]*[=+@\-]/u', $row->display_name ?? '') ? "'".$row->display_name : $row->display_name;
                $design1DisplayName = preg_match('/^[\s]*[=+@\-]/u', $row->design_1_display_name ?? '') ? "'".$row->design_1_display_name : $row->design_1_display_name;
                $amount = count($row->designs) * 350;
                fputcsv($out, [$row->id, $name, $design1DisplayName, $row->design_1_display_number, $displayName, $row->display_number, $row->size, implode('; ', array_map(fn ($id) => 'Design '.$id, $row->designs)), $amount, $row->paid_at ? $amount : 0, $row->paid_at ? 0 : $amount, $row->paid_at ? 'Paid' : 'Unpaid', $row->paid_at?->setTimezone(config('app.timezone'))->toDateTimeString(), $row->created_at->setTimezone(config('app.timezone'))->toDateTimeString()], ',', '"', '');
            }
            fclose($out);
        }, 'shirt-submissions-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
