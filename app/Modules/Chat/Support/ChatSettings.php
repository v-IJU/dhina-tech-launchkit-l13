<?php

declare(strict_types=1);

namespace App\Modules\Chat\Support;

/**
 * Settings > Team chat. What may be sent and how big.
 */
class ChatSettings
{
    public const IMAGE = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Voice notes from the browser are webm (Chrome, Edge, Firefox) or mp4 / m4a (Safari).
    public const AUDIO = ['webm', 'weba', 'ogg', 'oga', 'opus', 'mp3', 'm4a', 'mp4', 'wav', 'aac'];

    public const FILE = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'zip'];

    public static function maxKb(): int
    {
        return max(1, (int) setting('chat_max_upload_mb', 10)) * 1024;
    }

    public static function images(): bool
    {
        return (bool) setting('chat_images', true);
    }

    public static function voice(): bool
    {
        return (bool) setting('chat_voice_notes', true);
    }

    public static function files(): bool
    {
        return (bool) setting('chat_files', true);
    }

    /** "image", "audio" or "file" for an extension; null = not allowed right now. */
    public static function typeFor(string $extension, bool $voice): ?string
    {
        $extension = strtolower($extension);

        return match (true) {
            $voice && self::voice() && in_array($extension, self::AUDIO, true) => 'audio',
            self::images() && in_array($extension, self::IMAGE, true) => 'image',
            self::files() && in_array($extension, self::FILE, true) => 'file',
            default => null,
        };
    }

    /** For the file picker's accept="" attribute. */
    public static function accept(): string
    {
        $extensions = array_merge(self::images() ? self::IMAGE : [], self::files() ? self::FILE : []);

        return implode(',', array_map(fn ($e) => '.'.$e, $extensions));
    }
}
