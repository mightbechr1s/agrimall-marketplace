<?php
// src/Core/router.php

function view(string $file, array $data = []): void
{
    extract($data, EXTR_SKIP);

    require $file;
}

function abort(
    int $code = 404,
    string $message = 'Page not found.'
): void {
    http_response_code($code);

    exit($message);
}
