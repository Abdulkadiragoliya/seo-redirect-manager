<?php

namespace abdulkadir\seoredirectmanager\tests\unit;

use abdulkadir\seoredirectmanager\tests\TestCase;
use abdulkadir\seoredirectmanager\services\CsvImportService;
use abdulkadir\seoredirectmanager\services\CsvExportService;

class CsvValidationTest extends TestCase
{
    private CsvImportService $importer;
    private CsvExportService $exporter;

    protected function setUp(): void
    {
        $this->importer = new CsvImportService();
        $this->exporter = new CsvExportService();
    }

    public function testFlexibleHeaderMapping(): void
    {
        $headers = ['From URL', 'To URL', 'HTTP Status', 'Match Type', 'Active'];
        $map = $this->importer->mapHeaders($headers);

        $this->assertEquals(0, $map['source']);
        $this->assertEquals(1, $map['destination']);
        $this->assertEquals(2, $map['statusCode']);
        $this->assertEquals(3, $map['matchType']);
        $this->assertEquals(4, $map['enabled']);
    }

    public function testFormulaInjectionSanitization(): void
    {
        // Attack vectors with dangerous formula prefixes
        $this->assertEquals("'=cmd|' /C calc'!A0", $this->exporter->sanitizeFormulaInjection("=cmd|' /C calc'!A0"));
        $this->assertEquals("'+1+2", $this->exporter->sanitizeFormulaInjection("+1+2"));
        $this->assertEquals("'-1-2", $this->exporter->sanitizeFormulaInjection("-1-2"));
        $this->assertEquals("'@SUM(A1:A10)", $this->exporter->sanitizeFormulaInjection("@SUM(A1:A10)"));
        $this->assertEquals("'\ttabbed", $this->exporter->sanitizeFormulaInjection("\ttabbed"));

        // Safe values should remain unchanged
        $this->assertEquals("/safe/url/path", $this->exporter->sanitizeFormulaInjection("/safe/url/path"));
        $this->assertEquals("https://example.com/target", $this->exporter->sanitizeFormulaInjection("https://example.com/target"));
    }
}
