<?php

if (!function_exists('datebirth')) {
    function datebirth($day, $month, $year)
    {
        $day   = str_pad($day, 2, "0", STR_PAD_LEFT);
        $month = str_pad($month, 2, "0", STR_PAD_LEFT);
        return "{$year}-{$month}-{$day}";
    }
}

if (!function_exists('fileStore')) {
    function fileStore($file, $directory, $prefix = 'imagen')
    {
        if ($file) {
            $timestamp = time();
            $extension = $file->getClientOriginalExtension();
            $filename = "{$prefix}_{$timestamp}.{$extension}";

            if (!file_exists(public_path($directory))) {
                mkdir(public_path($directory), 0775, true);
            }

            $file->move(public_path($directory), $filename);
            return $filename;
        }
        return null;
    }
}

if (!function_exists('fileUpdate')) {
    function fileUpdate($newFile, $directory, $oldFile = null)
    {
        if ($oldFile) {
            fileDestroy($oldFile, $directory);
        }
        return fileStore($newFile, $directory);
    }
}

if (!function_exists('fileDestroy')) {
    function fileDestroy($photo, $directory)
    {
        try {
            $image_path = public_path("{$directory}/{$photo}");
            if (file_exists($image_path)) {
                unlink($image_path);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            \Log::error("❌ Error eliminando archivo: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('saludo')) {
    function saludo()
    {
        return "hola";
    }
}
