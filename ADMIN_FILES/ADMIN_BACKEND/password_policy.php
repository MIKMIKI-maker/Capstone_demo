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
