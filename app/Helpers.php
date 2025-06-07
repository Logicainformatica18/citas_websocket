<?php

function datebirth($day, $month, $year)
{
    //       datebirth
    if ($day < 10) {
        $day = "0" . $day;
    }
    if ($month < 10) {
        $month = "0" . $month;
    }
    $datebirth =    $year . "-" . $month . "-" . $day;
    return $datebirth;
}
function fileStore($file, $directory, $prefix = 'imagen')
{
    if ($file) {
        $timestamp = time(); // Ej: 1716923800
        $extension = $file->getClientOriginalExtension(); // jpg, png, pdf, etc.

        // Nombre nuevo: soporte_1716923800.jpg
        $filename = "{$prefix}_{$timestamp}.{$extension}";

        // Guardar archivo
        $file->move(public_path($directory), $filename);

        return $filename;
    }

    return null;
}

function fileUpdate($newFile, $directory, $oldFile = null)
{
    if ($oldFile) {
        fileDestroy($oldFile, $directory);  
    }
    return fileStore($newFile, $directory); 
}

function fileDestroy($photo, $directory)
{
    try {
        $image_path = public_path() . '/' . $directory . '/' . $photo;
        unlink($image_path);
    } catch (\Exception $e) {
               //   return  $e->getMessage();
               return "<div style='background-color:red'> ERROR </div>";
    }
}
function saludo(){
    return "hola";
}
