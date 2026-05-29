<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DdDataRoomItem;
use App\Models\DdEngagement;
use App\Services\Dd\DataRoom;
use App\Services\Dd\DdAdviceReportGenerator;
use App\Services\Storage\Exceptions\InfectedFileException;
use App\Services\Storage\Exceptions\SecureFileStorageException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class DdGuestUploadController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        DataRoom $dataRoom,
        DdAdviceReportGenerator $reports,
    ): JsonResponse {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx'],
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
        } catch (SecureFileStorageException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'file' => 'Upload could not be stored securely. Please try again or contact Future Shift Advisory.',
            ]);
        }

        $item->loadMissing('engagement.client');
        if ($item->engagement instanceof DdEngagement) {
            $reports->generateIfReady($item->engagement);
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
