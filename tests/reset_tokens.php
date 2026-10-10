<?php
declare(strict_types=1);

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}
// Isolated database: this test never changes the running application's accounts.
putenv("DATABASE_PATH=:memory:");
require dirname(__DIR__) . "/src/bootstrap.php";

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAIL: " . $message);
    }
    echo "PASS: " . $message . "\n";
}

$email = "devon@campus.edu";
$forged = sha1($email . "|" . gmdate("Y-m-d"));
check(!consume_reset_token($email, $forged), "forged token rejected without a request");
check(!consume_reset_token($email, str_repeat("a", 64)), "unissued random-format token rejected");
$token = issue_reset_token($email);
$stored = db()->query("SELECT token FROM reset_tokens WHERE email = 'devon@campus.edu'")->fetchColumn();
check($stored === hash("sha256", $token) && $stored !== $token, "only token digest is stored");
check(!consume_reset_token($email, $forged), "forged token rejected after a request");
check(!consume_reset_token("maya@campus.edu", $token), "token rejected for another account");
check(!consume_reset_token($email, "malformed"), "malformed token rejected");
check(consume_reset_token($email, $token), "issued token accepted");
check(!consume_reset_token($email, $token), "used token rejected");
$old = issue_reset_token($email);
$new = issue_reset_token($email);
check($old !== $new, "each request gets a fresh token");
check(!consume_reset_token($email, $old), "superseded token rejected");
check(consume_reset_token($email, $new), "replacement token accepted");
$expired = issue_reset_token($email);
db()->exec("UPDATE reset_tokens SET created_at = datetime('now', '-1 day')");
check(!consume_reset_token($email, $expired), "expired token rejected");
db()->prepare("INSERT OR REPLACE INTO reset_tokens (email, token, created_at) VALUES (?, ?, datetime('now'))")
    ->execute([$email, $forged]);
check(!consume_reset_token($email, $forged), "legacy stored token rejected");
check(verify_password("campus123", find_user_by_email($email)["password_hash"]), "normal sign-in still works");
$valid = issue_reset_token($email);
check(consume_reset_token($email, $valid), "legitimate reset authorized");
set_user_password($email, "ResetDemo456!");
check(verify_password("ResetDemo456!", find_user_by_email($email)["password_hash"]), "new password works after reset");
check(!verify_password("campus123", find_user_by_email($email)["password_hash"]), "old password stops working after reset");
