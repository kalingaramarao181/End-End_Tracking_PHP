<?php

function uploadFile($file, $folder)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $uploadDir = __DIR__ . "/../uploads/" . $folder . "/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

    $fileName = uniqid() . "_" . time();

    if (!empty($extension)) {
        $fileName .= "." . $extension;
    }

    $destination = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return "uploads/" . $folder . "/" . $fileName;
    }

    // Multipart PUT files are reconstructed in a controlled temporary file.
    if (!empty($file['_parsed_put']) && is_file($file['tmp_name']) && rename($file['tmp_name'], $destination)) {
        return "uploads/" . $folder . "/" . $fileName;
    }

    return null;
}
