<?php
/**
 * Shared password policy, used by every endpoint that sets or changes a
 * password (Admin add/reset/change, Teacher/Student change, forced
 * first-login change): the password must not be/contain the account
 * holder's first name, last name, or full name, and must include at least
 * one special character.
 *
 * Returns an error message string if the password violates the policy, or
 * null if it's fine. Callers still enforce their own minimum length.
 */
function passwordPolicyViolation($password, $firstName = '', $lastName = '') {
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return 'Password must include at least one special character.';
    }

    $lowerPw = strtolower(preg_replace('/\s+/', '', $password));
    $first = strtolower(trim($firstName));
    $last  = strtolower(trim($lastName));
    $full  = strtolower(preg_replace('/\s+/', '', trim($firstName . ' ' . $lastName)));

    if ($full !== '' && strpos($lowerPw, $full) !== false) {
        return 'Password must not contain your full name.';
    }
    if ($first !== '' && strpos($lowerPw, $first) !== false) {
        return 'Password must not contain your first name.';
    }
    if ($last !== '' && strpos($lowerPw, $last) !== false) {
        return 'Password must not contain your last name.';
    }

    return null;
}

/**
 * Random 8-character temporary password for a new account, emailed to its
 * owner instead of a shared role default like "Teacher@123" that anyone
 * could guess. Always has an uppercase, lowercase, number and special
 * character. Look-alike characters (0/O, 1/l/I) are left out since parents
 * and teachers type it by hand from the email.
 */
function generateTemporaryPassword($firstName = '', $lastName = '') {
    $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower   = 'abcdefghijkmnopqrstuvwxyz';
    $digits  = '23456789';
    $special = '!@#$%&*?';
    $all = $upper . $lower . $digits . $special;

    do {
        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $special[random_int(0, strlen($special) - 1)],
        ];
        while (count($chars) < 8) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        $password = implode('', $chars);
    } while (passwordPolicyViolation($password, $firstName, $lastName) !== null);

    return $password;
}
