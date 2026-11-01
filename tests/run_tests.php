<?php

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/**
 * Test suite runner for Kahootify
 */

$testDir = __DIR__ . '/Unit';
$files = glob($testDir . '/*Test.php');

$totalTests = 0;
$totalPassed = 0;
$totalFailed = 0;
$failures = [];

echo "========================================\n";
echo "   Kahootify Test Runner\n";
echo "========================================\n\n";

foreach ($files as $file) {
    echo "Running " . basename($file) . "...\n";
    require_once $file;
    $className = pathinfo($file, PATHINFO_FILENAME);

    if (!class_exists($className)) {
        echo "  [WARN] Class $className not found in $file\n";
        continue;
    }

    $ref = new ReflectionClass($className);
    $instance = $ref->newInstance();
    $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);

    foreach ($methods as $method) {
        if (str_starts_with($method->getName(), 'test')) {
            $totalTests++;
            try {
                $method->invoke($instance);
                echo "  ✓ " . $method->getName() . "\n";
                $totalPassed++;
            } catch (Throwable $e) {
                echo "  ✗ " . $method->getName() . " FAILED\n";
                $totalFailed++;
                $failures[] = [
                    'class' => $className,
                    'method' => $method->getName(),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ];
            }
        }
    }
    echo "\n";
}

echo "========================================\n";
echo "Summary: Total: $totalTests | Passed: $totalPassed | Failed: $totalFailed\n";
echo "========================================\n";

if ($totalFailed > 0) {
    echo "\nFailures:\n";
    foreach ($failures as $f) {
        echo "- {$f['class']}::{$f['method']}: {$f['message']}\n";
    }
    exit(1);
}

exit(0);
