<?php

namespace App\Http\Controllers;

use App\Models\CheckoutRecord;
use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutRecordController extends Controller
{
    protected function authorizePart(Part $part): void
    {
        $role = Auth::user()->role;
        if ($role === 'devices_manager' && $part->type !== 'device') abort(403);
        if ($role === 'furniture_manager' && $part->type !== 'furniture') abort(403);
    }

    // Store uploaded checkout photos (public disk)
    protected function storeCheckoutPhotos(Request $request, Part $part): array
    {
        $paths = [];
        $files = $request->file('checkout_photos');
        if (!$files) return $paths;
        Log::info('photo check correct');
        $files = is_array($files) ? $files : [$files];

        foreach ($files as $file) {
            if (!$file) continue;
            $path = $file->store("checkouts/{$part->id}", 'public');
            Log::info($path);
            $paths[] = Storage::disk('public')->url($path);
        }

        return $paths;
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
        Log::info("before valid");
        $data = $request->validate([
            'part_id'             => ['required', 'exists:parts,id'],
            'custody_assigned_to' => ['required', 'string', 'max:255'],
            'usage_type'          => ['required', Rule::in(['internal','external'])],
            'checked_out_at'      => ['nullable', 'date'],
            'checkout_photos'     => ['nullable', 'array'],
            'checkout_photos.*'   => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'notes'               => ['nullable', 'string'],
        ]);
        Log::info("start controller");
        $part = Part::find($data['part_id']);
        if(!$part)
        {
            return response()->json(["message"=>"part not found"],404);
        }
        $this->authorizePart($part);

        if ($part->status === 'checked-out') {
            return response()->json(['success'=>false,"error"=> 'Part is already checked out'], 422);
        }
        Log::info("we start save .....");
        // Handle photos upload
        $uploadedPaths = $this->storeCheckoutPhotos($request, $part);
        Log::info('We saved photos');
        return DB::transaction(function () use ($data, $part, $uploadedPaths) {
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
                'checkout_photos'    => $uploadedPaths, // store photo URLs
                'notes'              => $data['notes'] ?? null,
            ]);

            $part->update(['status' => 'checked-out']); // Mark part as checked-out

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
            'checkout_photos.*'   => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'notes'               => ['nullable', 'string'],
        ]);

        // Handle new photo uploads and merge with existing photos
        $uploadedPaths = [];
        if ($request->hasFile('checkout_photos')) {
            $uploadedPaths = $this->storeCheckoutPhotos($request, $rec->part);
        }

        return DB::transaction(function () use ($rec, $data, $uploadedPaths) {
            $rec->update(array_merge($data, ['checkout_photos' => $uploadedPaths]));

            // Mark part as available if returned_at is set
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

    // Get history of checkout records by part
    public function history(int $part_id)
    {
        $rec = CheckoutRecord::with(['part','user'])
               ->where('part_id', $part_id)->get();

        if($rec->count() == 0) return response()->json(['success'=>false,'message'=> 'Part not found'],404);

        $this->authorizePart($rec->first()->part); // authorize based on part's first checkout
        return response()->json(['success'=>true,'data'=>$rec ],200);
    }
}
