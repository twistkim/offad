<?php
// inc/flash.php
declare(strict_types=1);

function set_flash(string $key, string $message): void {
  $_SESSION['_flash'][$key] = $message;
}

function get_flash(string $key): ?string {
  if (!empty($_SESSION['_flash'][$key])) {
    $msg = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $msg;
  }
  return null;
}