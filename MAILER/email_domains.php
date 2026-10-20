<?php
// Any email provider is accepted for account and parent emails (school,
// company, or public mail). What's kept out are addresses that can't be
// reached: a malformed domain, or a common misspelling of a big provider
// like "@gmial.com" — every notification goes out through Brevo to
// whatever address is stored.

// Misspelled provider domain → the domain that was meant.
const EMAIL_DOMAIN_TYPOS = [
    'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gmal.com' => 'gmail.com', 'gamil.com' => 'gmail.com',
    'gnail.com' => 'gmail.com', 'gmail.co' => 'gmail.com', 'gmail.con' => 'gmail.com', 'gmail.cm' => 'gmail.com',
    'gmail.om' => 'gmail.com', 'gmaill.com' => 'gmail.com', 'gmail.comm' => 'gmail.com',
    'yaho.com' => 'yahoo.com', 'yahooo.com' => 'yahoo.com', 'yahoo.co' => 'yahoo.com', 'yahoo.con' => 'yahoo.com',
    'hotmal.com' => 'hotmail.com', 'hotmial.com' => 'hotmail.com', 'hotmail.co' => 'hotmail.com', 'hotmail.con' => 'hotmail.com',
    'outlok.com' => 'outlook.com', 'outloo.com' => 'outlook.com', 'outlook.co' => 'outlook.com', 'outlook.con' => 'outlook.com',
    'icloud.co' => 'icloud.com', 'iclod.com' => 'icloud.com',
    'deped.gov' => 'deped.gov.ph', 'depedgov.ph' => 'deped.gov.ph',
];

const ALLOWED_EMAIL_DOMAINS_MESSAGE = 'Please enter a valid email address (e.g. name@gmail.com or name@school.edu.ph)';

/** Why this email can't be used ('' when it's fine). */
function emailDomainProblem(string $email): string {
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ALLOWED_EMAIL_DOMAINS_MESSAGE;
    $domain = strtolower(substr($email, strrpos($email, '@') + 1));
    // Needs a real-looking domain: at least one dot and a 2+ letter ending.
    if (!preg_match('/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $domain)) return ALLOWED_EMAIL_DOMAINS_MESSAGE;
    if (isset(EMAIL_DOMAIN_TYPOS[$domain])) {
        return 'Check the email: "@' . $domain . '" looks misspelled — did you mean "@' . EMAIL_DOMAIN_TYPOS[$domain] . '"?';
    }
    return '';
}

function isAllowedEmailDomain(string $email): bool {
    return emailDomainProblem($email) === '';
}
