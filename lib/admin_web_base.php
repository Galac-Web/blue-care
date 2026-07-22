<?php
declare(strict_types=1);

/** Prefix URL pentru resurse la rădăcina site-ului când admin rulează din /admin/. */
function blu_admin_web_base(): string
{
    return (string) ($GLOBALS['blu_admin_web_base'] ?? '');
}
