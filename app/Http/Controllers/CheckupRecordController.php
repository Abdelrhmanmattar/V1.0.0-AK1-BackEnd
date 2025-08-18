<?php

namespace App\Http\Controllers;

use App\Models\CheckupRecord;
use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use PgSql\Lob;

class CheckupRecordController extends Controller
{
    protected function authorizePart(Part $part): void
    {
        $role = Auth::user()->role;
        Log::info($role . $part->type);
        if ($role === 'devices_manager' && $part->type !== 'device') abort(403);
        if ($role === 'furniture_manager' && $part->type !== 'furniture') abort(403);
    }

    // Store uploaded photos (public disk) and return their URLs
    protected function storeUploadedPhotos(Request $request, Part $part): array
    {
        $paths = [];
        $files = $request->file('checkup_photos'); // Get files uploaded under 'checkup_photos'

        Log::info( $files);  // Log the files received

        if (!$files) {
            Log::info('No files received');
            return $paths;  // Return empty if no files were uploaded
        }

        // Ensure files are in array format
        $files = is_array($files) ? $files : [$files];

        foreach ($files as $file) {
            if (!$file) {
                Log::info('Skipping empty file');
                continue; // Skip if the file is empty
            }

            Log::info('Storing file: ' . $file->getClientOriginalName());

            // Store file in the 'checkups' directory for this part
            $path = $file->store("checkups/{$part->id}", 'public');

            // Store file URL in the array
            $url = Storage::disk('public')->url($path);
            Log::info('File stored at: ' . $url);  // Log the file URL

            $paths[] = $url;  // Add URL to array
        }

        return $paths;
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
        Log::info($request->all());
        $data = $request->validate([
            'part_id'           => ['required', 'exists:parts,id'],
            'checkup_date'      => ['required', 'date'],
            'status'            => ['required', Rule::in(['good', 'needs-attention', 'needs-repair'])],
            'notes'             => ['nullable', 'string'],
            'next_checkup_date' => ['date', 'after_or_equal:checkup_date'],
            'checkup_photos'    => ['nullable'],
            'checkup_photos.*'  => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $part = Part::find($data['part_id']);
        if (!$part) {
            return response()->json(["success" => false, "error" => "Part not found"], 404);
        }
        $this->authorizePart($part);

        $user = Auth::user();
        $uploadedPaths = $this->storeUploadedPhotos($request, $part);

        $rec = CheckupRecord::create([
            'part_id'           => $part->id,
            'part_name'         => $part->name,
            'user_id'           => $user->id,
            'user_name'         => $user->name,
            'checkup_date'      => $data['checkup_date'],
            'status'            => $data['status'],
            'notes'             => $data['notes'] ?? null,
            'next_checkup_date' => $data['next_checkup_date'],
            'checkup_photos'    => !empty($uploadedPaths) ? $uploadedPaths : null,
        ]);

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
        Log::info($rec);
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
            'status'            => ['sometimes', Rule::in(['good', 'needs-attention', 'needs-repair'])],
            'notes'             => ['nullable', 'string'],
            'next_checkup_date' => ['sometimes', 'date', 'after_or_equal:checkup_date'],
            'checkup_photos'    => ['nullable'],
            'checkup_photos.*'  => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $payload = $data;

        // If new photos uploaded → merge with existing
        if ($request->hasFile('checkup_photos')) {
            $newPaths = $this->storeUploadedPhotos($request, $rec->part);
            $existing = $rec->checkup_photos ?? [];
            $payload['checkup_photos'] = array_values(array_unique(array_merge($existing, $newPaths)));
        } else {
            // Do not touch existing photos
            unset($payload['checkup_photos']);
        }

        $rec->update($payload);

        if (array_key_exists('checkup_date', $payload) || array_key_exists('next_checkup_date', $payload)) {
            $rec->part->update([
                'last_checkup' => $payload['checkup_date'] ?? $rec->checkup_date,
                'next_checkup' => $payload['next_checkup_date'] ?? $rec->next_checkup_date,
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
