<?php

namespace Tests\Feature;

use PDO;
use PDOException;
use Tests\TestCase;

class RuntimeLedgerPermissionTest extends TestCase
{
    public function test_runtime_role_cannot_update_or_delete_lifecycle_evidence(): void
    {
        $password = env('DB_RUNTIME_PASSWORD');
        if (! $password) {
            $this->markTestSkipped('Runtime role credentials must be configured for this permission probe.');
        }
        $pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=eval_development', env('DB_RUNTIME_USERNAME'), $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->assertGreaterThan(0, $pdo->query('SELECT COUNT(*) FROM academic_lifecycle_events')->fetchColumn());
        foreach (['UPDATE academic_lifecycle_events SET metadata=metadata WHERE id=0', 'DELETE FROM academic_lifecycle_events WHERE id=0'] as $sql) {
            try {
                $pdo->exec($sql);
                $this->fail('Runtime write permission must be denied.');
            } catch (PDOException $error) {
                $this->assertSame(1142, $error->errorInfo[1]);
            }
        }
    }
}
