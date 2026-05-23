<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DdDataRoomItem;
use App\Services\Dd\DataRoom;
use App\Services\Storage\Exceptions\InfectedFileException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class DdGuestUploadController extends Controller
{
    public function __invoke(Request $request, string $token, DataRoom $dataRoom): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'guest_name' => ['nullable', 'string', 'max:160'],
            'guest_email' => ['nullable', 'email', 'max:255'],
        ]);

        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        try {
            $item = $dataRoom->uploadViaGuestToken(
                token: $token,
                file: $file,
                guestName: $validated['guest_name'] ?? null,
                guestEmail: $validated['guest_email'] ?? null,
                metadata: [
                    'upload_channel' => 'dd_guest_link',
                ],
            );
        } catch (InfectedFileException) {
            throw ValidationException::withMessages([
                'file' => 'Upload rejected because malware was detected.',
            ]);
        }

        return response()->json([
            'data_room_item' => $this->payload($item),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DdDataRoomItem $item): array
    {
        $document = $item->document;

        return [
            'id' => $item->id,
            'workstream' => $item->workstream,
            'folder' => $item->folder,
            'artifact_type' => $item->artifact_type,
            'source' => $item->source,
            'document' => [
                'id' => $document?->id,
                'original_filename' => $document?->original_filename,
                'category' => $document?->category,
                'scanner_result' => $document?->scanner_result,
                'uploaded_at' => $document?->created_at?->toIso8601String(),
            ],
        ];
    }
}
