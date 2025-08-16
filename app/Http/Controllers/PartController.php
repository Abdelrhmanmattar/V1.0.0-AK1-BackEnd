<?php

namespace App\Http\Controllers;

use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PartController extends Controller
{
    // Helper: enforce role matches part type
    protected function authorizeType(string $type): void
    {
        $role = Auth::user()->role;
        if ($role === 'devices_manager' && $type !== 'device') {
            abort(403, 'You can only manage device parts.');
        }
        if ($role === 'furniture_manager' && $type !== 'furniture') {
            abort(403, 'You can only manage furniture parts.');
        }
    }

    // GET /api/parts
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $q = Part::query()->orderByDesc('id');

        // Limit results by role automatically
        $role = Auth::user()->role;
        if ($role === 'devices_manager')   $q->where('type', 'device');
        if ($role === 'furniture_manager') $q->where('type', 'furniture');

        // Optional filters
        if ($search = $request->query('q')) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%$search%")
                   ->orWhere('qr_code', 'like', "%$search%")
                   ->orWhere('part_number', 'like', "%$search%")
                   ->orWhere('serial_number', 'like', "%$search%");
            });
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        return response()->json($q->paginate($perPage));
    }

    // POST /api/parts
    public function store(Request $request)
    {
        $data = $request->validate([
            'part_number'             => ['required', 'string', 'max:255'],
            'serial_number'           => ['nullable', 'string', 'max:255', 'unique:parts,serial_number'],
            'name'                    => ['required', 'string', 'max:255'],
            'facility_classification' => ['required', Rule::in(['general','directDedicated','indirectDedicated','private'])],
            'facility_type'           => ['required', Rule::in(['officeFurniture','safetyEquipment','serviceDevices'])],
            'facility_details'        => ['required', 'string'],
            'location'                => ['required', Rule::in(['egypt','saudi'])],
            'department_id'           => ['required', 'exists:departments,id'],
            'type'                    => ['required', Rule::in(['furniture','device'])],
            'photo'                   => ['required', 'string', 'max:255'],
            'invoice_photo'           => ['nullable', 'string', 'max:255'],
            'status'                  => ['nullable', Rule::in(['available','checked-out'])],
            'condition'               => ['required', Rule::in(['new','like-new','needs-fix','damaged'])],
            'qr_code'                 => ['required', 'string', 'max:255', 'unique:parts,qr_code'],
            'price'                   => ['required', 'numeric', 'min:0'],
            'vat'                     => ['required', 'numeric', 'min:0', 'max:100'],
            'all_vat'                 => ['nullable', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string'],
            'checkup_schedule'        => ['nullable', Rule::in(['weekly','almost-daily','bi-weekly','monthly'])],
            'last_checkup'            => ['nullable', 'date'],
            'next_checkup'            => ['nullable', 'date'],
        ]);

        $this->authorizeType($data['type']);
        $part = Part::create($data);

        return response()->json($part, 201);
    }

    // GET /api/parts/{id}
    public function show($id)
    {
        $part = Part::findOrFail($id);
        $this->authorizeType($part->type);
        return response()->json($part);
    }

    // PUT/PATCH /api/parts/{id}
    public function update(Request $request, $id)
    {
        $part = Part::findOrFail($id);
        $this->authorizeType($part->type); // authorize on existing type

        $data = $request->validate([
            'part_number'             => ['sometimes', 'string', 'max:255'],
            'serial_number'           => ['nullable', 'string', 'max:255', Rule::unique('parts','serial_number')->ignore($part->id)],
            'name'                    => ['sometimes', 'string', 'max:255'],
            'facility_classification' => ['sometimes', Rule::in(['general','directDedicated','indirectDedicated','private'])],
            'facility_type'           => ['sometimes', Rule::in(['officeFurniture','safetyEquipment','serviceDevices'])],
            'facility_details'        => ['sometimes', 'string'],
            'location'                => ['sometimes', Rule::in(['egypt','saudi'])],
            'department_id'           => ['sometimes', 'exists:departments,id'],
            'type'                    => ['sometimes', Rule::in(['furniture','device'])],
            'photo'                   => ['sometimes', 'string', 'max:255'],
            'invoice_photo'           => ['nullable', 'string', 'max:255'],
            'status'                  => ['sometimes', Rule::in(['available','checked-out'])],
            'condition'               => ['sometimes', Rule::in(['new','like-new','needs-fix','damaged'])],
            'qr_code'                 => ['sometimes', 'string', 'max:255', Rule::unique('parts','qr_code')->ignore($part->id)],
            'price'                   => ['sometimes', 'numeric', 'min:0'],
            'vat'                     => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'all_vat'                 => ['nullable', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string'],
            'checkup_schedule'        => ['nullable', Rule::in(['weekly','almost-daily','bi-weekly','monthly'])],
            'last_checkup'            => ['nullable', 'date'],
            'next_checkup'            => ['nullable', 'date'],
        ]);

        // If client tries to change type, re-authorize target type as well
        if (array_key_exists('type', $data)) {
            $this->authorizeType($data['type']);
        }

        $part->update($data);
        return response()->json($part);
    }

    // DELETE /api/parts/{id}
    public function destroy($id)
    {
        $part = Part::findOrFail($id);
        $this->authorizeType($part->type);
        $part->delete(); // soft delete
        return response()->json(['message' => 'Part deleted']);
    }
}
