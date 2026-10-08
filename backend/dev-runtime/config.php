<?php
declare(strict_types=1);

function evalDevBinding(array $environment): bool
{
    $role = $environment['EVAL_DB_ROLE'] ?? null;
    return ($environment['EVAL_UNIT'] ?? null) === 'Dev'
        && ($environment['EVAL_DB_HOST'] ?? null) === '127.0.0.1'
        && ($environment['EVAL_DB_PORT'] ?? null) === '3307'
        && ($environment['EVAL_DB_NAME'] ?? null) === 'eval_dev'
        && in_array($role, ['runtime', 'migration'], true)
        && ($environment['EVAL_DB_USER'] ?? null) === 'eval_dev_'.$role;
}

/** Pure capability assessment; does not connect, provision or expose grant text. */
function evalDevCapabilities(array $grants): array
{
    foreach ($grants as $grant) {
        if (!is_string($grant) || str_starts_with($grant, 'REVOKE ')) {
            return ['create_database' => false, 'create_user' => false, 'grant_dev_permissions' => false];
        }
    }
    $required = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP', 'ALTER', 'INDEX', 'REFERENCES', 'TRIGGER'];
    $grantable = [];
    $createDatabase = false;
    $createUser = false;
    foreach ($grants as $grant) {
        if (!is_string($grant) || !preg_match('/^GRANT ([A-Z ,]+) ON (\*\.\*|`eval_dev`\.\*) TO /D', $grant, $match)) {
            continue; // Role/wildcard/table grants are not proof of this exact setup authority.
        }
        $privileges = array_map('trim', explode(',', $match[1]));
        $all = in_array('ALL PRIVILEGES', $privileges, true);
        $global = $match[2] === '*.*';
        $createDatabase = $createDatabase || $all || in_array('CREATE', $privileges, true);
        $createUser = $createUser || ($global && ($all || in_array('CREATE USER', $privileges, true)));
        if (str_ends_with($grant, ' WITH GRANT OPTION')) {
            $grantable = array_unique(array_merge($grantable, $all ? $required : $privileges));
        }
    }
    return ['create_database' => $createDatabase, 'create_user' => $createUser,
        'grant_dev_permissions' => array_diff($required, $grantable) === []];
}
