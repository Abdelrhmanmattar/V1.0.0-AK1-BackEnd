<?php

namespace App\Http\Controllers;

use App\Models\CheckoutRecord;
use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CheckoutRecordController extends Controller
{
    protected function authorizePart(Part $part): void
    {
        $role = Auth::user()->role;
        if ($role === 'devices_manager' && $part->type !== 'device') abort(403);
        if ($role === 'furniture_manager' && $part->type !== 'furniture') abort(403);
    }

    // GET /api/checkout-records
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $q = CheckoutRecord::with(['part:id,type,name', 'user:id,name'])->orderByDesc('id');

        // Limit by role through the part relationship
        $role = Auth::user()->role;
        if ($role === 'devices_manager')   $q->whereHas('part', fn($p) => $p->where('type', 'device'));
        if ($role === 'furniture_manager') $q->whereHas('part', fn($p) => $p->where('type', 'furniture'));

        return response()->json($q->paginate($perPage));
    }

    // POST /api/checkout-records  (create checkout)
    public function store(Request $request)
    {
        $data = $request->validate([
            'part_id'             => ['required', 'exists:parts,id'],
            'custody_assigned_to' => ['required', 'string', 'max:255'],
            'usage_type'          => ['required', Rule::in(['internal','external'])],
            'checked_out_at'      => ['nullable', 'date'],
            'checkout_photos'     => ['nullable', 'array'],
            'checkout_photos.*'   => ['string'],
            'notes'               => ['nullable', 'string'],
        ]);

        $part = Part::findOrFail($data['part_id']);
        $this->authorizePart($part);

        if ($part->status === 'checked-out') {
            return response()->json(['message' => 'Part is already checked out'], 422);
        }

        return DB::transaction(function () use ($data, $part) {
            $user = Auth::user();

            $record = CheckoutRecord::create([
                'part_id'            => $part->id,
                'part_name'          => $part->name, // snapshot
                'user_id'            => $user->id,
                'user_name'          => $user->name, // snapshot
                'custody_assigned_to'=> $data['custody_assigned_to'],
                'usage_type'         => $data['usage_type'],
                'checked_out_at'     => $data['checked_out_at'] ?? now(),
                'returned_at'        => null,
                'checkout_photos'    => $data['checkout_photos'] ?? null,
                'notes'              => $data['notes'] ?? null,
            ]);

            $part->update(['status' => 'checked-out']);

            return response()->json($record, 201);
        });
    }

    // GET /api/checkout-records/{id}
    public function show($id)
    {
        $rec = CheckoutRecord::with(['part:id,type,name', 'user:id,name'])->findOrFail($id);
        $this->authorizePart($rec->part);
        return response()->json($rec);
    }

    // PUT/PATCH /api/checkout-records/{id}
    public function update(Request $request, $id)
    {
        $rec = CheckoutRecord::with('part')->findOrFail($id);
        $this->authorizePart($rec->part);

        $data = $request->validate([
            'custody_assigned_to' => ['sometimes', 'string', 'max:255'],
            'usage_type'          => ['sometimes', Rule::in(['internal','external'])],
            'checked_out_at'      => ['sometimes', 'date'],
            'returned_at'         => ['nullable', 'date'],
            'checkout_photos'     => ['nullable', 'array'],
            'checkout_photos.*'   => ['string'],
            'notes'               => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($rec, $data) {
            $rec->update($data);

            // If returned_at just set (and was null), mark part available
            if (array_key_exists('returned_at', $data) && $data['returned_at'] && is_null($rec->getOriginal('returned_at'))) {
                $rec->part->update(['status' => 'available']);
            }

            return response()->json($rec);
        });
    }

    // DELETE /api/checkout-records/{id}
    public function destroy($id)
    {
        $rec = CheckoutRecord::with('part')->findOrFail($id);
        $this->authorizePart($rec->part);
        $rec->delete(); // soft delete
        return response()->json(['message' => 'Checkout record deleted']);
    }
}
