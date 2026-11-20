<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict',
]);
session_start();

require_once __DIR__ . '/db.php';
$connection = $conn;

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function databaseError($connection): void
{
    error_log('Game account database error: ' . pg_last_error($connection));
    respond(500, ['error' => 'Account storage is unavailable. Please try again later.']);
}

function getLeaderboard($connection): array
{
    $result = pg_query(
        $connection,
        'SELECT "user" AS name, score
         FROM users
         WHERE COALESCE(score, 0) > 0
         ORDER BY score DESC, "user" ASC'
    );
    if ($result === false) {
        databaseError($connection);
    }

    $leaderboard = [];
    while ($entry = pg_fetch_assoc($result)) {
        $leaderboard[] = [
            'name' => $entry['name'],
            'score' => (int) $entry['score'],
        ];
    }
    return $leaderboard;
}

function getAccounts($connection): array
{
    $result = pg_query(
        $connection,
        'SELECT "user" AS name, COALESCE(score, 0) AS score,
                CASE
                    WHEN "isAdmin"::text IN (\'1\', \'true\', \'t\') THEN TRUE
                    ELSE FALSE
                END AS is_admin
         FROM users
         ORDER BY score DESC NULLS LAST, "user" ASC'
    );
    if ($result === false) {
        databaseError($connection);
    }

    $accounts = [];
    while ($entry = pg_fetch_assoc($result)) {
        $accounts[] = [
            'name' => $entry['name'],
            'score' => (int) $entry['score'],
            'isAdmin' => $entry['is_admin'] === 't',
        ];
    }
    return $accounts;
}

function accountIsAdministrator($connection, string $accountId): bool
{
    $result = pg_query_params(
        $connection,
        'SELECT CASE
            WHEN "isAdmin"::text IN (\'1\', \'true\', \'t\') THEN TRUE
            ELSE FALSE
         END AS is_admin
         FROM users
         WHERE id = $1',
        [$accountId]
    );
    if ($result === false) {
        databaseError($connection);
    }

    $account = pg_fetch_assoc($result);
    return $account && $account['is_admin'] === 't';
}

function refreshSessionPlayers($connection): array
{
    $players = $_SESSION['players'] ?? [];
    if (!is_array($players)) {
        $players = [];
    }

    foreach ($players as $index => $player) {
        if (!is_array($player) || !isset($player['id'])) {
            unset($players[$index]);
            continue;
        }
        $players[$index]['isAdmin'] = accountIsAdministrator($connection, (string) $player['id']);
    }

    $_SESSION['players'] = array_values($players);
    return $_SESSION['players'];
}

function sessionHasAdministrator($connection): bool
{
    foreach (refreshSessionPlayers($connection) as $player) {
        if ($player['isAdmin']) {
            return true;
        }
    }

    return false;
}

$createIndex = pg_query(
    $connection,
    'CREATE UNIQUE INDEX IF NOT EXISTS users_username_lower_idx
     ON users (LOWER("user"))'
);
if ($createIndex === false) {
    databaseError($connection);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['action'] ?? '') === 'leaderboard') {
        respond(200, ['leaderboard' => getLeaderboard($connection)]);
    }
    if (($_GET['action'] ?? '') === 'accounts') {
        if (!sessionHasAdministrator($connection)) {
            respond(403, ['error' => 'Administrator access is required to view accounts.']);
        }
        respond(200, ['accounts' => getAccounts($connection)]);
    }
    respond(200, ['players' => refreshSessionPlayers($connection)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    respond(405, ['error' => 'Method not allowed.']);
}

$request = json_decode(file_get_contents('php://input'), true);
if (!is_array($request) || !isset($request['action']) || !is_string($request['action'])) {
    respond(400, ['error' => 'Invalid request.']);
}

if ($request['action'] === 'logout') {
    $_SESSION['players'] = [];
    session_regenerate_id(true);
    respond(200, ['players' => []]);
}

if ($request['action'] === 'save_scores') {
    $players = $_SESSION['players'] ?? [];
    $scores = $request['scores'] ?? null;
    if (!is_array($players) || count($players) !== 2) {
        respond(401, ['error' => 'Both players must be logged in to save race scores.']);
    }
    if (!is_array($scores) || count($scores) !== 2) {
        respond(422, ['error' => 'Two valid race scores are required.']);
    }

    $scores = array_values($scores);
    foreach ($scores as $score) {
        if (!is_int($score) || $score < 0 || $score > 2147483647) {
            respond(422, ['error' => 'Race scores must be non-negative whole numbers.']);
        }
    }

    if (pg_query($connection, 'BEGIN') === false) {
        databaseError($connection);
    }
    foreach ($players as $index => $player) {
        if (!is_array($player) || !isset($player['id'])) {
            pg_query($connection, 'ROLLBACK');
            respond(401, ['error' => 'Player session is invalid. Please log in again.']);
        }
        $result = pg_query_params(
            $connection,
            'UPDATE users
             SET score = GREATEST(COALESCE(score, 0), $1)
             WHERE id = $2',
            [$scores[$index], $player['id']]
        );
        if ($result === false) {
            pg_query($connection, 'ROLLBACK');
            databaseError($connection);
        }
        if (pg_affected_rows($result) !== 1) {
            pg_query($connection, 'ROLLBACK');
            respond(409, ['error' => 'A player account could not be found. Please log in again.']);
        }
    }
    if (pg_query($connection, 'COMMIT') === false) {
        databaseError($connection);
    }
    respond(200, ['message' => 'Race scores saved.']);
}

if ($request['action'] === 'manage_account') {
    if (!sessionHasAdministrator($connection)) {
        respond(403, ['error' => 'Administrator access is required to manage accounts.']);
    }

    $username = isset($request['username']) && is_string($request['username'])
        ? trim($request['username'])
        : '';
    $operation = $request['operation'] ?? null;
    if (!preg_match('/\A[A-Za-z0-9_]{3,24}\z/', $username)
        || !in_array($operation, ['delete', 'reset'], true)) {
        respond(422, ['error' => 'Invalid account management request.']);
    }

    $result = $operation === 'delete'
        ? pg_query_params(
            $connection,
            'DELETE FROM users WHERE LOWER("user") = LOWER($1) RETURNING id',
            [$username]
        )
        : pg_query_params(
            $connection,
            'UPDATE users SET score = 0 WHERE LOWER("user") = LOWER($1)',
            [$username]
        );
    if ($result === false) {
        databaseError($connection);
    }
    if ($operation === 'delete') {
        $deletedAccount = pg_fetch_assoc($result);
        if ($deletedAccount === false) {
            respond(404, ['error' => 'That account could not be found.']);
        }

        $players = $_SESSION['players'] ?? [];
        foreach ($players as $player) {
            if (isset($player['id']) && (string) $player['id'] === (string) $deletedAccount['id']) {
                $_SESSION['players'] = [];
                session_regenerate_id(true);
                $players = [];
                break;
            }
        }
        respond(200, ['message' => 'Account deleted.', 'players' => array_values($players)]);
    }

    if (pg_affected_rows($result) !== 1) {
        respond(404, ['error' => 'That account could not be found.']);
    }
    respond(200, ['message' => 'Account score reset.']);
}

if ($request['action'] === 'register') {
    $username = isset($request['username']) && is_string($request['username'])
        ? trim($request['username'])
        : '';
    $password = isset($request['password']) && is_string($request['password'])
        ? $request['password']
        : '';
    $confirmPassword = isset($request['confirmPassword']) && is_string($request['confirmPassword'])
        ? $request['confirmPassword']
        : '';
    $makeAdmin = $request['isAdmin'] ?? false;

    if (!preg_match('/\A[A-Za-z0-9_]{3,24}\z/', $username)) {
        respond(422, ['error' => 'Username must be 3–24 letters, numbers, or underscores.']);
    }
    if (!is_bool($makeAdmin)) {
        respond(422, ['error' => 'Invalid administrator selection.']);
    }
    
    if ($password !== $confirmPassword) {
        respond(422, ['error' => 'Passwords do not match.']);
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        respond(422, ['error' => 'Password must be between 8 and 72 bytes.']);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        respond(500, ['error' => 'Account could not be created. Please try again later.']);
    }
    $result = pg_query_params(
        $connection,
        'INSERT INTO users ("user", "password", score, "isAdmin") VALUES ($1, $2, 0, $3)
         ON CONFLICT DO NOTHING RETURNING id',
        [$username, $passwordHash, $makeAdmin ? 1 : 0]
    );
    if ($result === false) {
        databaseError($connection);
    }
    if (pg_fetch_assoc($result) === false) {
        respond(409, ['error' => 'That username is already registered.']);
    }
    session_regenerate_id(true);
    $players = refreshSessionPlayers($connection);
    respond(201, [
        'message' => $makeAdmin
            ? 'Administrator account created. Log in to join the race.'
            : 'Account created. Log in to join the race.',
        'players' => $players,
    ]);
}

if ($request['action'] === 'login') {
    $username = isset($request['username']) && is_string($request['username'])
        ? trim($request['username'])
        : '';
    $password = isset($request['password']) && is_string($request['password'])
        ? $request['password']
        : '';

    if (!preg_match('/\A[A-Za-z0-9_]{3,24}\z/', $username) || $password === '') {
        respond(422, ['error' => 'Enter a valid username and password.']);
    }
    $result = pg_query_params(
        $connection,
        'SELECT id, "user" AS username, "password" AS password_hash
         FROM users
         WHERE LOWER("user") = LOWER($1)',
        [$username]
    );
    if ($result === false) {
        databaseError($connection);
    }
    $account = pg_fetch_assoc($result);
    if (!$account || !password_verify($password, $account['password_hash'])) {
        respond(401, ['error' => 'Username or password is incorrect.']);
    }

    $players = refreshSessionPlayers($connection);
    if (count($players) >= 2) {
        respond(409, [
            'error' => 'Both player slots are already filled.',
            'players' => array_values($players),
        ]);
    }
    foreach ($players as $player) {
        if (isset($player['id']) && (string) $player['id'] === (string) $account['id']) {
            respond(409, ['error' => 'Use a different account for the second player.']);
        }
    }

    session_regenerate_id(true);
    $role = count($players) === 0 ? 'Player 1' : 'Player 2';
    $players[] = [
        'id' => (string) $account['id'],
        'username' => $account['username'],
        'role' => $role,
        'isAdmin' => accountIsAdministrator($connection, (string) $account['id']),
    ];
    $_SESSION['players'] = $players;
    respond(200, ['players' => $players]);
}

respond(400, ['error' => 'Unknown action.']);
