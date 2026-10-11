<?php

require_once __DIR__ . '/bootstrap.php';

use abdulkadir\seoredirectmanager\tests\unit\NormalizationTest;
use abdulkadir\seoredirectmanager\tests\unit\DestinationSafetyTest;
use abdulkadir\seoredirectmanager\tests\unit\QueryPolicyTest;
use abdulkadir\seoredirectmanager\tests\unit\NotFoundFilterTest;
use abdulkadir\seoredirectmanager\tests\unit\CsvValidationTest;
use abdulkadir\seoredirectmanager\tests\unit\RegexMatchTest;

$testClasses = [
    NormalizationTest::class,
    DestinationSafetyTest::class,
    QueryPolicyTest::class,
    NotFoundFilterTest::class,
    CsvValidationTest::class,
    RegexMatchTest::class,
];

echo "========================================================\n";
echo "SEO REDIRECT MANAGER — AUTOMATED TEST SUITE\n";
echo "========================================================\n\n";

$totalTests = 0;
$totalPassed = 0;
$totalFailed = 0;
$totalAssertions = 0;

foreach ($testClasses as $testClass) {
    /** @var \abdulkadir\seoredirectmanager\tests\TestCase $test */
    $test = new $testClass();
    $result = $test->run();

    $classShort = substr(strrchr($result['class'], '\\'), 1);
    $totalAssertions += $result['assertions'];
    $totalPassed += $result['passed'];
    $totalFailed += $result['failed'];
    $totalTests += ($result['passed'] + $result['failed']);

    if ($result['failed'] === 0) {
        printf(" [PASS] %-25s (%d tests, %d assertions)\n", $classShort, $result['passed'], $result['assertions']);
    } else {
        printf(" [FAIL] %-25s (%d failed, %d passed)\n", $classShort, $result['failed'], $result['passed']);
        foreach ($result['errors'] as $err) {
            printf("        -> %s(): %s\n", $err['method'], $err['message']);
        }
    }
}

echo "\n--------------------------------------------------------\n";
printf("SUMMARY: %d tests, %d assertions | %d passed, %d failed\n", $totalTests, $totalAssertions, $totalPassed, $totalFailed);
echo "--------------------------------------------------------\n";

if ($totalFailed > 0) {
    exit(1);
}

echo "All test assertions passed successfully!\n\n";
exit(0);
