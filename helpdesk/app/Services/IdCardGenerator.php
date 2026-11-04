<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class IdCardGenerator
{
    public function generate(
        UploadedFile $photo,
        string $nickname,
        string $fullName,
        string $position,
        string $employeeNumber,
        int $nameFontSize,
        int $positionFontSize,
    ): string {
        if (! extension_loaded('gd') || ! function_exists('imagettftext')) {
            throw new RuntimeException('The PHP GD extension with FreeType support is required.');
        }

        $assetDirectory = public_path('tools/sp-id-generator/img');
        $templatePath = $assetDirectory.DIRECTORY_SEPARATOR.'id.png';
        $fontPath = $assetDirectory.DIRECTORY_SEPARATOR.'PointPantherDEMO.otf';
        $positionFontPath = $assetDirectory.DIRECTORY_SEPARATOR.'KeepCalm-Medium.ttf';
        $employeeNumberFontPath = $assetDirectory.DIRECTORY_SEPARATOR.'ariblk.ttf';

        foreach ([$templatePath, $fontPath, $positionFontPath, $employeeNumberFontPath] as $path) {
            if (! is_file($path)) {
                throw new RuntimeException('An ID card template or font file is missing.');
            }
        }

        $photoPath = $photo->getRealPath();
        if ($photoPath === false) {
            throw new RuntimeException('The uploaded photo could not be read.');
        }

        $disk = Storage::disk('local');
        $relativeDirectory = 'id-generator/cards';
        if (! $disk->makeDirectory($relativeDirectory)) {
            throw new RuntimeException('The ID card output directory could not be created.');
        }

        $fileName = 'card-'.bin2hex(random_bytes(16)).'.png';
        $outputPath = $disk->path($relativeDirectory.'/'.$fileName);
        $images = [];

        try {
            $card = imagecreatefrompng($templatePath);
            if (! $card instanceof \GdImage) {
                throw new RuntimeException('The ID card template could not be opened.');
            }
            $images[] = $card;

            $sourcePhoto = imagecreatefromjpeg($photoPath);
            if (! $sourcePhoto instanceof \GdImage) {
                throw new RuntimeException('The uploaded JPEG photo could not be opened.');
            }
            $images[] = $sourcePhoto;

            $profile = imagecreatetruecolor(450, 450);
            if (! $profile instanceof \GdImage) {
                throw new RuntimeException('The profile image could not be prepared.');
            }
            $images[] = $profile;

            $photoDimensions = getimagesize($photoPath);
            if ($photoDimensions === false || ! imagecopyresampled(
                $profile,
                $sourcePhoto,
                0,
                0,
                0,
                0,
                450,
                450,
                $photoDimensions[0],
                $photoDimensions[1],
            )) {
                throw new RuntimeException('The uploaded JPEG photo could not be resized.');
            }

            $imageWidth = imagesx($card);
            $imageHeight = imagesy($card);
            $photoX = $imageWidth - 450 - 300;
            $photoY = $imageHeight - 450 - 795;
            if (! imagecopymerge($card, $profile, $photoX, $photoY, 0, 0, 450, 450, 100)) {
                throw new RuntimeException('The profile photo could not be placed on the card.');
            }

            $white = imagecolorallocate($card, 255, 255, 255);
            if ($white === false) {
                throw new RuntimeException('The ID card text color could not be prepared.');
            }

            $scale = 900 / $imageWidth;
            $nickname = ucwords($nickname);
            $fullName = ucwords($fullName);
            $position = ucwords($position);

            $this->drawText($card, 33, 650, 1125, $white, $employeeNumberFontPath, $employeeNumber);
            $this->drawCenteredText($card, 150, 915, $white, $fontPath, $nickname);
            $this->drawCenteredText($card, $nameFontSize * $scale, 1000, $white, $fontPath, $fullName);
            $this->drawCenteredText($card, $positionFontSize * $scale, 1065, $white, $positionFontPath, $position);

            if (! imagepng($card, $outputPath, 9)) {
                throw new RuntimeException('The generated ID card could not be saved.');
            }

            return $fileName;
        } catch (Throwable $exception) {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }

            throw $exception;
        } finally {
            foreach ($images as $image) {
                imagedestroy($image);
            }
        }
    }

    private function drawCenteredText(\GdImage $image, float $fontSize, int $y, int $color, string $fontPath, string $text): void
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text);
        if ($box === false) {
            throw new RuntimeException('The ID card text could not be measured.');
        }

        $textWidth = $box[2] - $box[0];
        $x = (int) round((imagesx($image) / 2) - ($textWidth / 2));
        $this->drawText($image, $fontSize, $x, $y, $color, $fontPath, $text);
    }

    private function drawText(\GdImage $image, float $fontSize, int $x, int $y, int|false $color, string $fontPath, string $text): void
    {
        if ($color === false || imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $text) === false) {
            throw new RuntimeException('The ID card text could not be rendered.');
        }
    }
}
