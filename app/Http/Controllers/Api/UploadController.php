<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function uploadImage(Request $request): JsonResponse
    {
        return $this->storeUpload($request, [
            'file' => 'required|file|image|max:10240',
            'folder' => 'nullable|string|max:100',
        ], 'thumbnails');
    }

    public function uploadVideo(Request $request): JsonResponse
    {
        return $this->storeUpload($request, [
            'file' => 'required|file|mimetypes:video/*|max:512000',
            'folder' => 'nullable|string|max:100',
        ], 'videos');
    }

    private function storeUpload(Request $request, array $rules, string $defaultFolder): JsonResponse
    {
        if ($errorResponse = $this->invalidUploadResponse($request, 'file')) {
            return $errorResponse;
        }

        $validator = Validator::make(
            array_merge($request->all(), $request->allFiles()),
            $rules
        );

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        try {
            $folder = $request->input('folder', $defaultFolder);
            $file = $request->file('file');
            $filename = uniqid().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs($folder, $filename, 'public');

            return response()->json([
                'success' => true,
                'url' => Storage::disk('public')->url($path),
                'public_id' => $path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function invalidUploadResponse(Request $request, string $field): ?JsonResponse
    {
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        $maxPostSize = $this->iniSizeToBytes((string) ini_get('post_max_size'));

        if ($maxPostSize > 0 && $contentLength > $maxPostSize && ! $request->hasFile($field)) {
            return response()->json([
                'message' => 'The upload exceeds the server request limit of '.ini_get('post_max_size').'.',
            ], 422);
        }

        if (! $request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);

        if ($file !== null && ! $file->isValid()) {
            return response()->json([
                'message' => $this->uploadErrorMessage($file->getError()),
            ], 422);
        }

        return null;
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            \UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the server upload limit of '.ini_get('upload_max_filesize').'.',
            \UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the allowed form size.',
            \UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
            \UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            \UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload directory.',
            \UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file to disk.',
            \UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'The file failed to upload.',
        };
    }

    private function iniSizeToBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $number = (float) $value;
        $suffix = strtolower(substr($value, -1));

        return match ($suffix) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }
}
