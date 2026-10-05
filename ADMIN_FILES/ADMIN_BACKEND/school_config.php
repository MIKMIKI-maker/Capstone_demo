<?php
// The school this deployment serves. Set SCHOOL_NAME / SCHOOL_LOCATION in the
// environment (docker-compose.yml locally, Render's Environment tab live) to
// use the system for another school; the defaults are the current one.
if (!defined('SCHOOL_NAME')) {
    define('SCHOOL_NAME', getenv('SCHOOL_NAME') ?: 'Mamatid Elementary School');
    define('SCHOOL_LOCATION', getenv('SCHOOL_LOCATION') ?: 'Cabuyao, Laguna');
}

/**
 * Value for teacher_accounts.teacher_password on a new row. Logins only check
 * admin_accounts, so this column is never used to sign in; it gets a random
 * hash nobody knows instead of the old shared "Teacher@123".
 */
function unusableTeacherPasswordHash(): string {
    return password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
}
