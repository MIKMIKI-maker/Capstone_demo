<?php
require_once __DIR__ . '/send_email.php';

/**
 * Emails a student's parent about something a teacher sent/did — shared by
 * teacher_notify_student.php (single-student notification + broadcast) and
 * teacher_finalize_submission.php (final grade).
 */
function notifyParentByEmail($parent_email, $parent_name, $student_name, $title, $message) {
    if (!$parent_email) return;
    $safeParent  = htmlspecialchars($parent_name ?: 'Parent/Guardian', ENT_QUOTES, 'UTF-8');
    $safeStudent = htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');
    $safeTitle   = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $logoUrl  = LOGO_CID_SRC;
    $loginUrl = PUBLIC_SITE_URL . '/ADMIN_FILES/login_screen.html';
    $year = date('Y');

    $html = "<div style=\"max-width:480px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:32px 28px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;\">"
        . "<div style=\"text-align:center;margin-bottom:10px;\"><img src=\"{$logoUrl}\" alt=\"SPED ALM\" width=\"48\" height=\"48\" style=\"width:48px;height:48px;border-radius:50%;\"></div>"
        . "<p style=\"text-align:center;margin:0 0 2px;font-weight:700;color:#1e3a8a;font-size:15px;\">SPED ALM SYSTEM</p>"
        . "<p style=\"text-align:center;margin:0 0 22px;color:#64748b;font-size:13px;\">Student Notification</p>"
        . "<hr style=\"border:none;border-top:1px solid #e2e8f0;margin:0 0 20px;\">"
        . "<p style=\"margin:0 0 12px;\">Dear {$safeParent},</p>"
        . "<p style=\"margin:0 0 16px;\">This is to inform you that your child's teacher has sent a new notification regarding <b>{$safeStudent}</b>.</p>"
        . "<div style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin:0 0 20px;\">"
        . "<p style=\"margin:0 0 8px;font-weight:700;color:#1e3a8a;font-size:14px;\">🔔 {$safeTitle}</p>"
        . "<p style=\"margin:0;font-size:14px;\">{$safeMessage}</p>"
        . "</div>"
        . "<p style=\"margin:0 0 20px;font-size:13px;color:#64748b;\">Please log in to your SPED ALM account to view the complete notification.</p>"
        . "<div style=\"text-align:center;margin:0 0 24px;\"><a href=\"{$loginUrl}\" style=\"display:inline-block;background:#1e3a8a;color:#ffffff;text-decoration:none;padding:12px 32px;border-radius:10px;font-weight:700;font-size:14px;\">View Notification</a></div>"
        . "<hr style=\"border:none;border-top:1px solid #e2e8f0;margin:0 0 16px;\">"
        . "<p style=\"margin:0 0 16px;\">Sincerely yours,<br>SPED ALM System</p>"
        . "<p style=\"font-size:12px;color:#94a3b8;text-align:center;margin:0 0 4px;\">This is an automated message from SPED ALM. Please do not reply directly to this email.</p>"
        . "<p style=\"font-size:12px;color:#cbd5e1;text-align:center;margin:0;\">© {$year} SPED ALM</p>"
        . "</div>";
    send_email($parent_email, $parent_name ?: 'Parent/Guardian', "New notification for {$student_name}: {$title}", $html);
}
