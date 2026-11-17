<?php
namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Decode, resize and rewrite photos as metadata-free JPEGs on private local disk. */
final class StudioPhotoProcessor
{
    public function store(UploadedFile $upload, int $company): string
    {
        $sourcePath = $upload->getRealPath();
        $bytes = $sourcePath ? file_get_contents($sourcePath) : false;
        if ($bytes === false || !extension_loaded('gd')) {
            throw ValidationException::withMessages(['photo'=>'Image processing is unavailable.']);
        }
        $image = @imagecreatefromstring($bytes);
        unset($bytes);
        if ($image === false) {
            throw ValidationException::withMessages(['photo'=>'Unable to decode this image.']);
        }
        try {
            $width = imagesx($image);
            $height = imagesy($image);
            if ($width < 1 || $height < 1 || $width > 2048 || $height > 2048) {
                throw ValidationException::withMessages(['photo'=>'Image dimensions are unsupported.']);
            }
            $factor = min(1, 1200 / max($width, $height));
            $targetWidth = max(1, (int) round($width * $factor));
            $targetHeight = max(1, (int) round($height * $factor));
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            ob_start();
            try {
                $success = imagejpeg($canvas, null, 85);
                $jpg = ob_get_contents();
            } finally {
                ob_end_clean();
                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($image);
        }
        if (!$success || !$jpg) {
            throw ValidationException::withMessages(['photo'=>'Unable to process this photo.']);
        }

        $path = "studio/{$company}/employee-photos/".Str::uuid().".jpg";
        if (!Storage::disk('local')->put($path, $jpg)) {
            throw ValidationException::withMessages(['photo'=>'Unable to store this photo.']);
        }
        return $path;
    }
}
