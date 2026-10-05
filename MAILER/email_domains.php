<?php
// Only these public mail providers are accepted for account and parent
// emails. Keeps typos like "@gmial.com" and unreachable school/test domains
// out of the database, since every notification goes out through Brevo to
// whatever address is stored.
const ALLOWED_EMAIL_DOMAINS = [
    'gmail.com',
    'outlook.com',
    'hotmail.com',
    'yahoo.com',
    'icloud.com',
    'proton.me',
    'protonmail.com',
    'aol.com',
    'zohomail.com',
    'gmx.com',
    'mail.com',
    'yandex.com',
    'tuta.com',
    'deped.gov.ph',
];

const ALLOWED_EMAIL_DOMAINS_MESSAGE = 'Email must be from a supported provider: Gmail, Outlook, Hotmail, Yahoo, iCloud, Proton, AOL, Zoho, GMX, Mail.com, Yandex, Tuta, or DepEd';

function isAllowedEmailDomain(string $email): bool {
    $at = strrpos($email, '@');
    if ($at === false) return false;
    $domain = strtolower(substr($email, $at + 1));
    return in_array($domain, ALLOWED_EMAIL_DOMAINS, true);
}
