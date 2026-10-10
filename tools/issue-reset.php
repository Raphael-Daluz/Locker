<?php
declare(strict_types=1);

// Local demonstration helper; never served from the public document root.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . "/src/bootstrap.php";
$email = strtolower(trim($argv[1] ?? ""));
if (!find_user_by_email($email)) {
    fwrite(STDERR, "Usage: php tools/issue-reset.php existing-account@campus.edu\n");
    exit(1);
}
$token = issue_reset_token($email);
echo "Local demo token (expires at UTC midnight; usable once):\n{$token}\n";
