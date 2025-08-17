<?php

namespace App\Http\Controllers;

use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

        $role = Auth::user()->role;
        if ($role === 'devices_manager')   $q->where('type', 'device');
        if ($role === 'furniture_manager') $q->where('type', 'furniture');

        if ($search = $request->query('q')) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%$search%")
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
            'facility_classification' => ['required', Rule::in(['general', 'directDedicated', 'indirectDedicated', 'private'])],
            'facility_type'           => ['required', Rule::in(['officeFurniture', 'safetyEquipment', 'serviceDevices'])],
            'facility_details'        => ['required', 'string'],
            'location'                => ['required', Rule::in(['egypt', 'saudi'])],
            'department_id'           => ['required', 'exists:departments,id'],
            'type'                    => ['required', Rule::in(['furniture', 'device'])],
            'photo'                   => ['required', 'file', 'image', 'max:2048'],
            'invoice_photo'           => ['nullable', 'file', 'image', 'max:2048'],
            'status'                  => ['nullable', Rule::in(['available', 'checked-out'])],
            'condition'               => ['required', Rule::in(['new', 'like-new', 'needs-fix', 'damaged'])],
            'qr_code'                 => ['required', 'string', 'max:255', 'unique:parts,qr_code'],
            'price'                   => ['required', 'numeric', 'min:0'],
            'vat'                     => ['required', 'numeric', 'min:0', 'max:100'],
            'all_vat'                 => ['nullable', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string'],
            'checkup_schedule'        => ['nullable', Rule::in(['weekly', 'almost-daily', 'bi-weekly', 'monthly'])],
            'last_checkup'            => ['nullable', 'date'],
            'next_checkup'            => ['nullable', 'date'],
        ]);

        $this->authorizeType($data['type']);

        // Handle file uploads
        $data['photo'] = $request->file('photo')->store("parts/photos", 'public');
        $data['photo'] = Storage::disk('public')->url($data['photo']);

        if ($request->hasFile('invoice_photo')) {
            $data['invoice_photo'] = $request->file('invoice_photo')->store("parts/invoices", 'public');
            $data['invoice_photo'] = Storage::disk('public')->url($data['invoice_photo']);
        }

        $part = Part::create($data);

        return response()->json($part, 201);
    }

    // GET /api/parts/{id}
    public function show($id)
    {
        $part = Part::with('department')->find($id);

        if (!$part) {
            return response()->json(["success" => false, "error" => "Part not found"], 404);
        }

        $this->authorizeType($part->type);

        return response()->json($part);
    }

    // PUT/PATCH /api/parts/{id}
    public function update(Request $request, $id)
    {
        $part = Part::find($id);
        if (!$part) {
            return response()->json(["success" => false, "error" => "Part not found"], 404);
        }

        $this->authorizeType($part->type);

        $data = $request->validate([
            'part_number'             => ['sometimes', 'string', 'max:255'],
            'serial_number'           => ['nullable', 'string', 'max:255', Rule::unique('parts', 'serial_number')->ignore($part->id)],
            'name'                    => ['sometimes', 'string', 'max:255'],
            'facility_classification' => ['sometimes', Rule::in(['general', 'directDedicated', 'indirectDedicated', 'private'])],
            'facility_type'           => ['sometimes', Rule::in(['officeFurniture', 'safetyEquipment', 'serviceDevices'])],
            'facility_details'        => ['sometimes', 'string'],
            'location'                => ['sometimes', Rule::in(['egypt', 'saudi'])],
            'department_id'           => ['sometimes', 'exists:departments,id'],
            'type'                    => ['sometimes', Rule::in(['furniture', 'device'])],
            'photo'                   => ['sometimes', 'file', 'image', 'max:2048'],
            'invoice_photo'           => ['nullable', 'file', 'image', 'max:2048'],
            'status'                  => ['sometimes', Rule::in(['available', 'checked-out'])],
            'condition'               => ['sometimes', Rule::in(['new', 'like-new', 'needs-fix', 'damaged'])],
            'qr_code'                 => ['sometimes', 'string', 'max:255', Rule::unique('parts', 'qr_code')->ignore($part->id)],
            'price'                   => ['sometimes', 'numeric', 'min:0'],
            'vat'                     => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'all_vat'                 => ['nullable', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string'],
            'checkup_schedule'        => ['nullable', Rule::in(['weekly', 'almost-daily', 'bi-weekly', 'monthly'])],
            'last_checkup'            => ['nullable', 'date'],
            'next_checkup'            => ['nullable', 'date'],
        ]);
        Log::info('valided '. $part->id );
        if (array_key_exists('type', $data)) {
            $this->authorizeType($data['type']);
        }

        // Handle new uploads (keep old if not uploaded)
        if ($request->hasFile('photo')) {
            Log::info("photo added to part");
            $data['photo'] = Storage::disk('public')->url(
                $request->file('photo')->store("parts/photos", 'public')
            );
        }
        if ($request->hasFile('invoice_photo')) {
            $data['invoice_photo'] = Storage::disk('public')->url(
                $request->file('invoice_photo')->store("parts/invoices", 'public')
            );
        }

        $part->update($data);

        return response()->json($part);
    }

    // DELETE /api/parts/{id}
    public function destroy($id)
    {
        $part = Part::find($id);
        if (!$part) {
            return response()->json(["success" => false, "error" => "Part not found"], 404);
        }
        $this->authorizeType($part->type);
        $part->delete(); // soft delete
        return response()->json(['success' => true, 'message' => 'Part deleted successfully']);
    }

    public function showByQr(string $qrCode)
    {
        $part = Part::with('department')->where('qr', $qrCode)->first();

        if (! $part) {
            return response()->json(['success' => false, 'error' => 'Part not found'], 404);
        }

        $this->authorizeType($part->type);

        return response()->json($part);
    }

    public function getPartQR(int $part_id)
    {
        $part = Part::find($part_id);
        if (!$part) {
            return response()->json(["success"=>false,"error"=> "Failed to generate QRcode"],404);
        }
        $this->authorizeType($part->type);
        return response()->json(["success"=>true , "qrCode"=>$part->qr]);
    }
}
