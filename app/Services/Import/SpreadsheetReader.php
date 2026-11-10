<?php

namespace App\Services\Import;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class SpreadsheetReader
{
    private const MAX_ROWS = 1000;

    private const MAX_ARCHIVE_BYTES = 20_000_000;

    private const MAX_ENTRY_BYTES = 10_000_000;

    private const MAIN_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const RELATIONSHIPS_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const PACKAGE_RELATIONSHIPS_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * @return list<array{line: int, values: list<string>}>
     */
    public function read(UploadedFile $file): array
    {
        return match (strtolower($file->getClientOriginalExtension())) {
            'csv' => $this->readCsv($file),
            'xlsx' => $this->readXlsx($file),
            default => $this->invalidFile('Chỉ chấp nhận tệp CSV hoặc XLSX.'),
        };
    }

    /**
     * @param  list<string>  $headers
     */
    public function csvTemplate(array $headers): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create the CSV template stream.');
        }

        try {
            if (fputcsv($stream, $headers, ',', '"', '') === false) {
                throw new \RuntimeException('Unable to write the CSV template.');
            }

            rewind($stream);
            $contents = stream_get_contents($stream);

            if ($contents === false) {
                throw new \RuntimeException('Unable to read the CSV template.');
            }

            return "\xEF\xBB\xBF".$contents;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  list<string>  $headers
     */
    public function xlsxTemplate(array $headers): string
    {
        $path = tempnam(sys_get_temp_dir(), 'crm-import-template-');

        if ($path === false) {
            throw new \RuntimeException('Unable to create a temporary XLSX template.');
        }

        $archive = new ZipArchive;
        $archiveOpen = false;

        try {
            if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create the XLSX template archive.');
            }

            $archiveOpen = true;
            $entries = [
                '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                    .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                    .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                    .'<Default Extension="xml" ContentType="application/xml"/>'
                    .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                    .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                    .'</Types>',
                '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?>'
                    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                    .'</Relationships>',
                'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                    .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                    .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                    .'<sheets><sheet name="Import" sheetId="1" r:id="rId1"/></sheets></workbook>',
                'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>'
                    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                    .'</Relationships>',
                'xl/worksheets/sheet1.xml' => $this->worksheetTemplate($headers),
            ];

            foreach ($entries as $name => $contents) {
                if (! $archive->addFromString($name, $contents)) {
                    throw new \RuntimeException('Unable to write the XLSX template archive.');
                }
            }

            $closed = $archive->close();
            $archiveOpen = false;

            if (! $closed) {
                throw new \RuntimeException('Unable to finish the XLSX template archive.');
            }

            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new \RuntimeException('Unable to read the XLSX template archive.');
            }

            return $contents;
        } finally {
            if ($archiveOpen) {
                $archive->close();
            }

            if (is_file($path) && ! unlink($path)) {
                throw new \RuntimeException('Unable to remove the temporary XLSX template.');
            }
        }
    }

    /**
     * @return list<array{line: int, values: list<string>}>
     */
    private function readCsv(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $stream = $path === false ? false : fopen($path, 'rb');

        if ($stream === false) {
            $this->invalidFile('Không thể đọc tệp CSV đã tải lên.');
        }

        try {
            $rows = [];
            $line = 0;

            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $line++;
                $values = array_map(static fn ($value): string => (string) $value, $values);

                if ($line === 1 && isset($values[0])) {
                    $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', $values[0]) ?? $values[0];
                }

                if (count($values) === 1 && trim($values[0]) === '') {
                    continue;
                }

                $rows[] = ['line' => $line, 'values' => $values];

                if (count($rows) > self::MAX_ROWS + 1) {
                    $this->invalidFile('Tệp không được vượt quá 1.000 dòng dữ liệu.');
                }
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return list<array{line: int, values: list<string>}>
     */
    private function readXlsx(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $archive = new ZipArchive;

        if ($path === false || $archive->open($path, ZipArchive::RDONLY) !== true) {
            $this->invalidFile('Tệp XLSX không hợp lệ hoặc không thể đọc.');
        }

        try {
            $this->validateArchiveSize($archive);

            $workbook = $this->parseXml($this->readArchiveEntry($archive, 'xl/workbook.xml'));
            $worksheetPath = $this->worksheetPath($archive, $workbook);
            $worksheet = $this->parseXml($this->readArchiveEntry($archive, $worksheetPath));
            $sharedStrings = $this->sharedStrings($archive);
            $sheetData = $worksheet->children(self::MAIN_NAMESPACE)->sheetData;

            if ($sheetData === null) {
                $this->invalidFile('Tệp XLSX không có dữ liệu trang tính.');
            }

            $rows = [];
            $fallbackLine = 0;

            foreach ($sheetData->children(self::MAIN_NAMESPACE)->row as $row) {
                $fallbackLine++;
                $rowAttributes = $row->attributes();
                $line = (int) ($rowAttributes['r'] ?? $fallbackLine);
                $values = [];
                $nextColumn = 0;

                foreach ($row->children(self::MAIN_NAMESPACE)->c as $cell) {
                    $attributes = $cell->attributes();
                    $reference = (string) ($attributes['r'] ?? '');
                    $column = $reference === ''
                        ? $nextColumn
                        : $this->columnIndex($reference);

                    if ($column >= 64) {
                        $this->invalidFile("Tệp XLSX có cột vượt giới hạn ở dòng {$line}.");
                    }

                    $cellContents = $cell->children(self::MAIN_NAMESPACE);
                    $value = match ((string) ($attributes['t'] ?? '')) {
                        'inlineStr' => $this->xmlText($cellContents->is),
                        's' => $this->sharedString($sharedStrings, (string) $cellContents->v),
                        'b' => (string) $cellContents->v === '1' ? '1' : '0',
                        default => (string) $cellContents->v,
                    };

                    $values[$column] = $value;
                    $nextColumn = $column + 1;
                }

                if ($values === []) {
                    continue;
                }

                $lastColumn = max(array_keys($values));
                $orderedValues = array_fill(0, $lastColumn + 1, '');

                foreach ($values as $column => $value) {
                    $orderedValues[$column] = $value;
                }

                $rows[] = ['line' => $line, 'values' => $orderedValues];

                if (count($rows) > self::MAX_ROWS + 1) {
                    $this->invalidFile('Tệp không được vượt quá 1.000 dòng dữ liệu.');
                }
            }

            return $rows;
        } finally {
            $archive->close();
        }
    }

    private function validateArchiveSize(ZipArchive $archive): void
    {
        if ($archive->numFiles > 512) {
            $this->invalidFile('Tệp XLSX chứa quá nhiều thành phần.');
        }

        $totalSize = 0;

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->statIndex($index);

            if ($entry === false) {
                $this->invalidFile('Không thể đọc cấu trúc tệp XLSX.');
            }

            $totalSize += (int) ($entry['size'] ?? 0);

            if ($totalSize > self::MAX_ARCHIVE_BYTES) {
                $this->invalidFile('Dữ liệu giải nén trong tệp XLSX vượt quá giới hạn cho phép.');
            }
        }
    }

    private function readArchiveEntry(ZipArchive $archive, string $name): string
    {
        $entry = $archive->statName($name);

        if ($entry === false || (int) ($entry['size'] ?? 0) > self::MAX_ENTRY_BYTES) {
            $this->invalidFile('Tệp XLSX thiếu thành phần cần thiết hoặc vượt quá giới hạn cho phép.');
        }

        $contents = $archive->getFromName($name);

        if ($contents === false) {
            $this->invalidFile('Không thể đọc thành phần trong tệp XLSX.');
        }

        return $contents;
    }

    private function parseXml(string $contents): SimpleXMLElement
    {
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }

        if (! $xml instanceof SimpleXMLElement) {
            $this->invalidFile('Tệp XLSX chứa dữ liệu XML không hợp lệ.');
        }

        return $xml;
    }

    private function worksheetPath(ZipArchive $archive, SimpleXMLElement $workbook): string
    {
        $sheet = $workbook->children(self::MAIN_NAMESPACE)
            ->sheets
            ->children(self::MAIN_NAMESPACE)
            ->sheet[0] ?? null;

        if (! $sheet instanceof SimpleXMLElement) {
            $this->invalidFile('Tệp XLSX không có trang tính.');
        }

        $relationId = (string) ($sheet->attributes(self::RELATIONSHIPS_NAMESPACE)['id'] ?? '');
        $relationships = $this->parseXml($this->readArchiveEntry($archive, 'xl/_rels/workbook.xml.rels'));
        $target = null;

        foreach ($relationships->children(self::PACKAGE_RELATIONSHIPS_NAMESPACE)->Relationship as $relationship) {
            $attributes = $relationship->attributes();

            if ((string) ($attributes['Id'] ?? '') === $relationId) {
                $target = (string) ($attributes['Target'] ?? '');

                break;
            }
        }

        if ($target === null || str_contains($target, '..') || str_contains($target, '\\')) {
            $this->invalidFile('Đường dẫn trang tính trong tệp XLSX không hợp lệ.');
        }

        $path = str_starts_with($target, '/')
            ? ltrim($target, '/')
            : (str_starts_with($target, 'xl/') ? $target : 'xl/'.$target);

        if (! str_starts_with($path, 'xl/worksheets/')) {
            $this->invalidFile('Trang tính XLSX không nằm trong thư mục hợp lệ.');
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $archive): array
    {
        if ($archive->statName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $sharedStrings = $this->parseXml($this->readArchiveEntry($archive, 'xl/sharedStrings.xml'));
        $values = [];

        foreach ($sharedStrings->children(self::MAIN_NAMESPACE)->si as $item) {
            $values[] = $this->xmlText($item);
        }

        return $values;
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function sharedString(array $sharedStrings, string $index): string
    {
        if (! ctype_digit($index) || ! array_key_exists((int) $index, $sharedStrings)) {
            $this->invalidFile('Tệp XLSX tham chiếu đến chuỗi không hợp lệ.');
        }

        return $sharedStrings[(int) $index];
    }

    private function xmlText(?SimpleXMLElement $element): string
    {
        if (! $element instanceof SimpleXMLElement) {
            return '';
        }

        if ($element->getName() === 't') {
            return (string) $element;
        }

        $text = '';

        foreach ($element->children(self::MAIN_NAMESPACE) as $child) {
            $text .= $this->xmlText($child);
        }

        return $text;
    }

    private function columnIndex(string $reference): int
    {
        if (! preg_match('/^([A-Z]+)/i', $reference, $matches)) {
            $this->invalidFile('Tệp XLSX có địa chỉ ô không hợp lệ.');
        }

        $column = 0;

        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $column = ($column * 26) + ord($letter) - 64;

            if ($column > 64) {
                $this->invalidFile('Tệp XLSX có cột vượt giới hạn.');
            }
        }

        return $column - 1;
    }

    /**
     * @param  list<string>  $headers
     */
    private function worksheetTemplate(array $headers): string
    {
        $cells = '';

        foreach ($headers as $index => $header) {
            $column = '';
            $number = $index + 1;

            while ($number > 0) {
                $number--;
                $column = chr(65 + ($number % 26)).$column;
                $number = intdiv($number, 26);
            }

            $reference = $column.'1';
            $value = htmlspecialchars($header, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $cells .= '<c r="'.$reference.'" t="inlineStr"><is><t>'.$value.'</t></is></c>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1">'.$cells.'</row>'
            .'</sheetData></worksheet>';
    }

    private function invalidFile(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
