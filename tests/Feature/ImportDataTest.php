<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\Customer\SaveCustomer;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ImportDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_managers_can_open_the_import_page(): void
    {
        $this->withoutVite();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'is_active' => true,
        ]);
        $sales = User::factory()->create([
            'role' => UserRole::Sales,
            'is_active' => true,
        ]);
        $inactiveManager = User::factory()->create([
            'role' => UserRole::Manager,
            'is_active' => false,
        ]);

        $this->actingAs($manager)->get('/imports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Imports/Index'));

        $this->actingAs($sales)->get('/imports')->assertForbidden();
        $this->actingAs($sales)->post('/imports')->assertForbidden();
        $this->actingAs($inactiveManager)->get('/imports')->assertForbidden();
    }

    public function test_managers_can_download_templates_for_customers_and_leads(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'is_active' => true,
        ]);

        foreach ([
            'customer' => 'code',
            'lead' => 'title',
        ] as $type => $firstHeader) {
            $csv = $this->actingAs($manager)
                ->get(route('imports.template', [$type, 'csv']))
                ->assertOk()
                ->streamedContent();
            $this->assertStringContainsString($firstHeader, $csv);

            $xlsx = $this->actingAs($manager)
                ->get(route('imports.template', [$type, 'xlsx']))
                ->assertOk()
                ->streamedContent();
            $rows = app(SpreadsheetReader::class)
                ->read(UploadedFile::fake()->createWithContent('template.xlsx', $xlsx));

            $this->assertSame($firstHeader, $rows[0]['values'][0]);
        }
    }

    public function test_manager_imports_customers_from_csv_and_skips_duplicate_rows(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'is_active' => true,
        ]);
        (new SaveCustomer)->handle([
            'name' => 'Existing customer',
            'tax_code' => '0312345678',
        ], $manager);

        $file = $this->csvUpload('customers.csv', [
            ['code', 'name', 'short_name', 'tax_code', 'phone', 'email', 'website', 'address', 'province', 'industrial_zone', 'industry', 'company_type', 'employee_count', 'source_channel', 'tier', 'lifecycle_stage', 'status', 'notes'],
            ['', 'New customer', '', '', '', 'contact@example.test', '', '', '', '', '', '', '', '', '', '', 'active', ''],
            ['', 'Existing customer', '', '0312345678', '', '', '', '', '', '', '', '', '', '', '', '', 'active', ''],
            ['', 'New customer', '', '', '', 'other@example.test', '', '', '', '', '', '', '', '', '', '', 'active', ''],
        ]);

        $response = $this->actingAs($manager)
            ->post(route('imports.store'), [
                'type' => 'customer',
                'file' => $file,
            ]);

        $response->assertRedirect(route('imports.index'))
            ->assertSessionHas('import_result', fn (array $result): bool => $result['created'] === 1
                && $result['duplicates'] === 2);

        $this->assertDatabaseHas('customers', [
            'name' => 'New customer',
            'email' => 'contact@example.test',
            'owner_id' => $manager->id,
        ]);
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_manager_imports_leads_from_xlsx(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'is_active' => true,
        ]);
        $file = $this->xlsxUpload('leads.xlsx', [
            ['title', 'contact_name', 'company', 'email', 'phone', 'stage', 'estimated_value', 'notes'],
            ['Need safety equipment', 'Nguyen An', 'Example Co', 'an@example.test', '0901234567', 'new', '1250000', 'Imported'],
        ], true);
        $xlsxRows = app(SpreadsheetReader::class)->read($file);
        $this->assertCount(2, $xlsxRows);
        $this->assertSame('title', $xlsxRows[0]['values'][0]);

        $this->actingAs($manager)
            ->post(route('imports.store'), [
                'type' => 'lead',
                'file' => $file,
            ])
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('import_result', fn (array $result): bool => $result['created'] === 1
                && $result['duplicates'] === 0);

        $this->assertDatabaseHas('leads', [
            'title' => 'Need safety equipment',
            'contact_name' => 'Nguyen An',
            'owner_id' => $manager->id,
            'kind' => 'lead',
            'stage' => 'new',
            'estimated_value' => '1250000.00',
        ]);
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_invalid_rows_prevent_the_entire_file_from_being_imported(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'is_active' => true,
        ]);
        $file = $this->csvUpload('customers.csv', [
            ['code', 'name', 'short_name', 'tax_code', 'phone', 'email', 'website', 'address', 'province', 'industrial_zone', 'industry', 'company_type', 'employee_count', 'source_channel', 'tier', 'lifecycle_stage', 'status', 'notes'],
            ['', 'Valid customer', '', '', '', 'valid@example.test', '', '', '', '', '', '', '', '', '', '', 'active', ''],
            ['', '', '', '', '', 'invalid-email', '', '', '', '', '', '', '', '', '', '', 'active', ''],
        ]);

        $this->actingAs($manager)
            ->from('/imports')
            ->post(route('imports.store'), [
                'type' => 'customer',
                'file' => $file,
            ])
            ->assertRedirect('/imports')
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('customers', 0);
    }

    private function xlsxUpload(string $name, array $rows, bool $useSharedStrings = false): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'crm-import-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary workbook.');
        }

        $archive = new ZipArchive;

        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create a test workbook.');
        }

        $sharedStrings = [];

        if ($useSharedStrings) {
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    if (! in_array((string) $value, $sharedStrings, true)) {
                        $sharedStrings[] = (string) $value;
                    }
                }
            }
        }

        $archive->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .($useSharedStrings
                ? '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
                : '')
            .'</Types>');
        $archive->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $archive->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Import" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $relationships = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .($useSharedStrings
                ? '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
                : '')
            .'</Relationships>';
        $archive->addFromString('xl/_rels/workbook.xml.rels', $relationships);

        $xmlRows = [];

        foreach ($rows as $rowIndex => $row) {
            $cells = '';

            foreach ($row as $columnIndex => $value) {
                $column = '';
                $number = $columnIndex + 1;

                while ($number > 0) {
                    $number--;
                    $column = chr(65 + ($number % 26)).$column;
                    $number = intdiv($number, 26);
                }

                $reference = $column.($rowIndex + 1);
                $escapedValue = htmlspecialchars((string) $value, ENT_XML1);
                $cells .= $useSharedStrings
                    ? '<c r="'.$reference.'" t="s"><v>'.array_search((string) $value, $sharedStrings, true).'</v></c>'
                    : '<c r="'.$reference.'" t="inlineStr"><is><t>'.$escapedValue.'</t></is></c>';
            }

            $xmlRows[] = '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        }

        $archive->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .implode('', $xmlRows)
            .'</sheetData></worksheet>');

        if ($useSharedStrings) {
            $stringNodes = array_map(
                static fn (string $value): string => '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>',
                $sharedStrings,
            );
            $archive->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                .'count="'.count($rows[0] ?? []).'" uniqueCount="'.count($sharedStrings).'">'
                .implode('', $stringNodes)
                .'</sst>');
        }

        $archive->close();

        $contents = file_get_contents($path);
        unlink($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read a test workbook.');
        }

        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function csvUpload(string $name, array $rows): UploadedFile
    {
        $stream = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw new RuntimeException('Unable to read a test CSV file.');
        }

        return UploadedFile::fake()->createWithContent($name, $contents);
    }
}
