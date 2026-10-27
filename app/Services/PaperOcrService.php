<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class PaperOcrService
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MAX_EDGE = 1600;

    public function transcribe(UploadedFile $file): string
    {
        $this->assertSupported($file);

        $encoded = $this->toJpegData($file);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You transcribe handwritten or printed text exactly. You never grade, summarize, correct, or add commentary.',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Transcribe every word on this paper exactly as written. Do not grade, summarize, correct grammar, or add notes. If the page is blank, return an empty string. Return only the transcribed text.',
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'data:'.$encoded['mime'].';base64,'.base64_encode($encoded['bytes']),
                                'detail' => 'high',
                            ],
                        ],
                    ],
                ],
            ],
            'temperature' => 0,
            'max_tokens' => 2000,
        ]);

        return trim((string) ($response->choices[0]->message->content ?? ''));
    }

    private function assertSupported(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new RuntimeException('The photo upload failed.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('The photo is too large. Use a smaller picture.');
        }

        $mime = (string) $file->getMimeType();

        if (! in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Use a JPEG, PNG, or WebP photo.');
        }
    }

    /**
     * @return array{bytes: string, mime: string}
     */
    private function toJpegData(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('The photo upload failed.');
        }

        $raw = file_get_contents($path);

        if ($raw === false || $raw === '') {
            throw new RuntimeException('Could not read that photo.');
        }

        if (! function_exists('imagecreatefromstring')) {
            return [
                'bytes' => $raw,
                'mime' => $file->getMimeType() ?: 'image/jpeg',
            ];
        }

        $image = @imagecreatefromstring($raw);

        if ($image === false) {
            throw new RuntimeException('Could not read that photo.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_EDGE / max($width, $height, 1));

        if ($scale < 1) {
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, 72);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        if ($bytes === '') {
            throw new RuntimeException('Could not prepare that photo.');
        }

        return [
            'bytes' => $bytes,
            'mime' => 'image/jpeg',
        ];
    }
}
