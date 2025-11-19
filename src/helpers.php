<?php

function generateToken(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}

function hashToken(string $token): string {
    return hash('sha256', $token);
}

function flashMessage(string $msg) {
    echo "<p style='background:#eef;padding:10px;border-radius:8px;font-family:sans-serif;'>{$msg}</p>";
}
