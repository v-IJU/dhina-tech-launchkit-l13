<?php

declare(strict_types=1);

namespace App\Modules\Chat\Support;

use App\Core\Tenancy\Tenancy;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Where chat images, files and voice notes live: the private "chat" disk (config/filesystems.php).
 *
 *   CHAT_DISK=local → storage/app/private/chat/…  (never public, served through the app after a check)
 *   CHAT_DISK=s3    → your S3 bucket under AWS_CHAT_PREFIX (private objects)
 *   not set        → follows UPLOADS_DISK (s3 → s3, otherwise local)
 *
 * SaaS: each tenant has its own folder (tenant{id}/…), the central site uses central/…
 */
class ChatFiles
{
    public static function disk(): Filesystem
    {
        return Storage::disk('chat');
    }

    public static function store(UploadedFile $file): string
    {
        $folder = (Tenancy::tenant()?->getTenantKey() !== null ? 'tenant'.Tenancy::tenant()->getTenantKey() : 'central').'/'.now()->format('Y/m');
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');

        return (string) self::disk()->putFileAs($folder, $file, Str::random(40).'.'.$extension, ['visibility' => 'private']);
    }

    public static function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        try {
            self::disk()->delete($path);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
