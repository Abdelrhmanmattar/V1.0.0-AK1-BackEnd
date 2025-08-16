<?php

namespace App\Http\Controllers;

use App\Models\CheckupRecord;
use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CheckupRecordController extends Controller
{
    protected function authorizePart(Part $part): void
    {
        $role = Auth::user()->role;
        if ($role === 'devices_manager' && $part->type !== 'device') abort(403);
        if ($role === 'furniture_manager' && $part->type !== 'furniture') abort(403);
    }

    // GET /api/checkup-records
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $q = CheckupRecord::with(['part:id,type,name', 'user:id,name'])->orderByDesc('id');

        $role = Auth::user()->role;
        if ($role === 'devices_manager')   $q->whereHas('part', fn($p) => $p->where('type', 'device'));
        if ($role === 'furniture_manager') $q->whereHas('part', fn($p) => $p->where('type', 'furniture'));

        return response()->json($q->paginate($perPage));
    }

    // POST /api/checkup-records
    public function store(Request $request)
    {
        $data = $request->validate([
            'part_id'           => ['required', 'exists:parts,id'],
            'checkup_date'      => ['required', 'date'],
            'status'            => ['required', Rule::in(['good','needs-attention','needs-repair'])],
            'notes'             => ['nullable', 'string'],
            'next_checkup_date' => ['required', 'date', 'after_or_equal:checkup_date'],
            'checkup_photos'    => ['nullable', 'array'],
            'checkup_photos.*'  => ['string'],
        ]);

        $part = Part::findOrFail($data['part_id']);
        $this->authorizePart($part);

        $user = Auth::user();
        $rec = CheckupRecord::create([
            'part_id'           => $part->id,
            'part_name'         => $part->name,
            'user_id'           => $user->id,
            'user_name'         => $user->name,
            'checkup_date'      => $data['checkup_date'],
            'status'            => $data['status'],
            'notes'             => $data['notes'] ?? null,
            'next_checkup_date' => $data['next_checkup_date'],
            'checkup_photos'    => $data['checkup_photos'] ?? null,
        ]);

        // optional: update part's last/next checkup mirrors
        $part->update([
            'last_checkup' => $data['checkup_date'],
            'next_checkup' => $data['next_checkup_date'],
        ]);

        return response()->json($rec, 201);
    }

    // GET /api/checkup-records/{id}
    public function show($id)
    {
        $rec = CheckupRecord::with(['part:id,type,name', 'user:id,name'])->findOrFail($id);
        $this->authorizePart($rec->part);
        return response()->json($rec);
    }

    // PUT/PATCH /api/checkup-records/{id}
    public function update(Request $request, $id)
    {
        $rec = CheckupRecord::with('part')->findOrFail($id);
        $this->authorizePart($rec->part);

        $data = $request->validate([
            'checkup_date'      => ['sometimes', 'date'],
            'status'            => ['sometimes', Rule::in(['good','needs-attention','needs-repair'])],
            'notes'             => ['nullable', 'string'],
            'next_checkup_date' => ['sometimes', 'date', 'after_or_equal:checkup_date'],
            'checkup_photos'    => ['nullable', 'array'],
            'checkup_photos.*'  => ['string'],
        ]);

        $rec->update($data);

        // keep part mirrors in sync when dates change
        if (array_key_exists('checkup_date', $data) || array_key_exists('next_checkup_date', $data)) {
            $rec->part->update([
                'last_checkup' => $data['checkup_date'] ?? $rec->checkup_date,
                'next_checkup' => $data['next_checkup_date'] ?? $rec->next_checkup_date,
            ]);
        }

        return response()->json($rec);
    }

    // DELETE /api/checkup-records/{id}
    public function destroy($id)
    {
        $rec = CheckupRecord::with('part')->findOrFail($id);
        $this->authorizePart($rec->part);
        $rec->delete(); // soft delete
        return response()->json(['message' => 'Checkup record deleted']);
    }
}
