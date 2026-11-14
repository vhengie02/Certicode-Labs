<?php

namespace App\Support;

/**
 * Where students download the VS Code extension, and the name the file is saved under.
 *
 * The file on the server keeps one stable name, so old builds and bookmarks still find it.
 * The saved copy carries the version, so a student can tell which build they have.
 */
class ExtensionDownload
{
    public static function url(): string
    {
        return asset(config('services.vscode_extension.download_path'));
    }

    public static function filename(): string
    {
        return 'certicode-labs-'.config('services.vscode_extension.latest_version').'.vsix';
    }
}
