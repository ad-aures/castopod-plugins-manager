<?php

declare(strict_types=1);

if (! function_exists('tempdir')) {
    /**
     * Creates a random unique temporary directory, with specified parameters,
     * that does not already exist (like tempnam(), but for dirs).
     *
     * Created dir will begin with the specified prefix, followed by random
     * numbers.
     *
     * @link https://php.net/manual/en/function.tempnam.php
     * @link adapted from https://stackoverflow.com/a/30010928
     *
     * @param string|null $dir Base directory under which to create temp dir.
     *     If null, the default system temp dir (sys_get_temp_dir()) will be
     *     used.
     * @param string $prefix String with which to prefix created dirs.
     * @param int $mode Octal file permission mask for the newly-created dir.
     *     Should begin with a 0.
     * @param int $maxAttempts Maximum attempts before giving up (to prevent
     *     endless loops).
     * @return string|false Full path to newly-created dir, or false on failure.
     */
    function tempdir(
        string $prefix = 'tmp_',
        ?string $dir = null,
        int $mode = 0700,
        int $maxAttempts = 1000,
    ): string|false {
        /* Use writable/temp dir by default. */
        if ($dir === null) {
            $dir = sys_get_temp_dir();
        }

        /* Trim trailing slashes from $dir. */
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);

        /* If we don't have permission to create a directory, fail, otherwise we will
         * be stuck in an endless loop.
         */
        if (! is_dir($dir) || ! is_writable($dir)) {
            return false;
        }

        /* Make sure characters in prefix are safe. */
        if (strpbrk($prefix, '\\/:*?"<>|') !== false) {
            return false;
        }

        /* Attempt to create a random directory until it works. Abort if we reach
         * $maxAttempts. Something screwy could be happening with the filesystem
         * and our loop could otherwise become endless.
         */
        $attempts = 0;
        do {
            $path = sprintf('%s%s%s%s', $dir, DIRECTORY_SEPARATOR, $prefix, mt_rand(100000, mt_getrandmax()));
        } while (
            ! mkdir($path, $mode) &&
            $attempts++ < $maxAttempts
        );

        return $path;
    }
}

if (! function_exists('xcopy')) {
    /**
     * Recursively copies all contents of a source folder into a destination folder
     *
     * @link adapted from @https://stackoverflow.com/a/2050965
     */
    function xcopy(string $src, string $dest): bool
    {
        foreach (scandir($src) as $file) {
            if (! is_readable($src . '/' . $file)) {
                continue;
            }

            if (is_dir($src . '/' . $file) && ($file !== '.') && ($file !== '..')) {
                if (! is_dir($dest . '/' . $file) && ! mkdir($dest . '/' . $file)) {
                    return false;
                }

                xcopy($src . '/' . $file, $dest . '/' . $file);
            } elseif (($file !== '.') && ($file !== '..')) {
                if (! copy($src . '/' . $file, $dest . '/' . $file)) {
                    return false;
                }
            }
        }

        return true;
    }
}

if (! function_exists('removeDir')) {
    /**
     * Deletes a directory with its contents (rm -rf $dir)
     *
     * @link from https://stackoverflow.com/a/3349792
     */
    function removeDir(string $dir): bool
    {
        $iterator = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isDir()) {
                if (! rmdir($file->getPathname())) {
                    return false;
                }
            } elseif (! unlink($file->getPathname())) {
                return false;
            }
        }
        return rmdir($dir);
    }
}

if (! function_exists('get_directory_metadata')) {
    /**
     * Calculates directory size, file count, and checksum of a directory
     *
     * @return array{0:int,1:int,2:string}
     */
    function get_directory_metadata(string $path): array
    {
        $path = (string) realpath($path);
        if ($path === '' || ! file_exists($path)) {
            throw new Exception(sprintf('Could not get metadata of directory %s', $path));
        }

        $iterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

        $fileList = [];
        $bytesTotal = 0;
        $fileCount = 0;
        /** @var \SplFileObject $file */
        foreach ($files as $file) {
            if ($file->isFile()) {
                $filePath = $file->getPathname();
                $filename = preg_replace('/^' . preg_quote($path, '/') . '/', '', $filePath);

                $fileList[] = $filename . ':' . hash_file('sha256', $filePath);

                $bytesTotal += $file->getSize();
                $fileCount++;
            }
        }

        // Ensure file list order is always the same
        sort($fileList);

        $checksum = hash('sha256', implode('|', $fileList));

        return [$bytesTotal, $fileCount, $checksum];
    }
}
