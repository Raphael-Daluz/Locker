<?php
declare(strict_types=1);

// Password and token helpers. Keep these in one place so login and reset stay in sync.

function hash_password(string $password): string
{
    return hash("sha256", $password);
}

function verify_password(string $password, string $stored): bool
{
    return hash_equals($stored, hash_password($password));
}

function issue_reset_token(string $email): string
{
    // A new, unpredictable token for each request. Only its digest is stored.
    $token = bin2hex(random_bytes(32));
    $pdo = db();
    $pdo->prepare(
        "INSERT INTO reset_tokens (email, token, created_at) VALUES (?, ?, datetime('now'))
         ON CONFLICT(email) DO UPDATE SET token = excluded.token, created_at = excluded.created_at"
    )->execute([$email, hash("sha256", $token)]);
    return $token;
}

function consume_reset_token(string $email, string $token): bool
{
    // Reject the old predictable SHA-1 tokens as well as malformed input.
    if (!preg_match('/\A[0-9a-f]{64}\z/', $token)) {
        return false;
    }
    // Matching and deletion happen in one statement, so simultaneous requests
    // cannot reuse a token. SQLite's 'now' uses UTC, preserving same-day expiry.
    $stmt = db()->prepare(
        "DELETE FROM reset_tokens
         WHERE email = ? AND token = ? AND date(created_at) = date('now')"
    );
    $stmt->execute([$email, hash("sha256", $token)]);
    return $stmt->rowCount() === 1;
}
