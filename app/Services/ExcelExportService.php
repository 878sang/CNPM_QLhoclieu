<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Xuất báo cáo Excel chuẩn OpenXML (.xlsx) nhiều Sheet bằng PHP thuần (dùng zlib/crc32 có sẵn, không phụ thuộc ext-zip).
 */
class ExcelExportService
{
    /**
     * Tạo phản hồi tải xuống tệp .xlsx gồm nhiều trang tính (sheets).
     *
     * @param  array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, scalar|null>>, widths?: array<int, int|float>}>  $sheets
     */
    public function download(string $filename, array $sheets): StreamedResponse
    {
        $binary = $this->buildXlsxBinary($sheets);

        if (! str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * Xây dựng nội dung nhị phân của tệp .xlsx (PKZIP chứa các thành phần OpenXML).
     *
     * @param  array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, scalar|null>>, widths?: array<int, int|float>}>  $sheets
     */
    public function buildXlsxBinary(array $sheets): string
    {
        if (empty($sheets)) {
            $sheets = [[
                'title' => 'Báo cáo',
                'headers' => ['Thông báo'],
                'rows' => [['Không có dữ liệu']],
            ]];
        }

        $sharedStrings = [];
        $sharedStringIndex = [];
        $addSharedString = function (string $value) use (&$sharedStrings, &$sharedStringIndex): int {
            // Loại bỏ các ký tự điều khiển XML không hợp lệ
            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
            if (! array_key_exists($clean, $sharedStringIndex)) {
                $sharedStringIndex[$clean] = count($sharedStrings);
                $sharedStrings[] = $clean;
            }

            return $sharedStringIndex[$clean];
        };

        $files = [];
        $sheetTitles = [];

        foreach (array_values($sheets) as $idx => $sheet) {
            $sheetNo = $idx + 1;
            $safeTitle = $this->sanitizeSheetTitle((string) ($sheet['title'] ?? "Sheet{$sheetNo}"), $sheetTitles);
            $sheetTitles[] = $safeTitle;

            $headers = array_values($sheet['headers'] ?? []);
            $rows = array_values($sheet['rows'] ?? []);
            $widths = $sheet['widths'] ?? [];

            $files["xl/worksheets/sheet{$sheetNo}.xml"] = $this->buildWorksheetXml(
                $headers,
                $rows,
                $widths,
                $addSharedString
            );
        }

        $files['[Content_Types].xml'] = $this->buildContentTypesXml(count($sheets));
        $files['_rels/.rels'] = $this->buildRootRelsXml();
        $files['xl/workbook.xml'] = $this->buildWorkbookXml($sheetTitles);
        $files['xl/_rels/workbook.xml.rels'] = $this->buildWorkbookRelsXml(count($sheets));
        $files['xl/styles.xml'] = $this->buildStylesXml();
        $files['xl/sharedStrings.xml'] = $this->buildSharedStringsXml($sharedStrings);

        return $this->packZipArchive($files);
    }

    /**
     * @param  array<int, string>  $existing
     */
    private function sanitizeSheetTitle(string $title, array $existing): string
    {
        $clean = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '-', trim($title));
        if ($clean === '') {
            $clean = 'BaoCao';
        }
        $clean = mb_substr($clean, 0, 31);
        $candidate = $clean;
        $counter = 2;
        while (in_array($candidate, $existing, true)) {
            $suffix = " ({$counter})";
            $candidate = mb_substr($clean, 0, 31 - mb_strlen($suffix)).$suffix;
            $counter++;
        }

        return $candidate;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, scalar|null>>  $rows
     * @param  array<int, int|float>  $customWidths
     */
    private function buildWorksheetXml(array $headers, array $rows, array $customWidths, callable $addSharedString): string
    {
        $colCount = max(count($headers), ...array_map('count', $rows ?: [[]]));
        $colWidths = [];
        for ($c = 0; $c < $colCount; $c++) {
            if (isset($customWidths[$c])) {
                $colWidths[$c] = (float) $customWidths[$c];

                continue;
            }
            $maxLen = isset($headers[$c]) ? mb_strlen((string) $headers[$c]) : 10;
            foreach ($rows as $row) {
                if (isset($row[$c])) {
                    $maxLen = max($maxLen, min(55, mb_strlen((string) $row[$c])));
                }
            }
            $colWidths[$c] = max(12, min(52, $maxLen + 5));
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if ($colCount > 0) {
            $xml .= '<cols>';
            for ($c = 0; $c < $colCount; $c++) {
                $colIdx = $c + 1;
                $width = number_format($colWidths[$c], 2, '.', '');
                $xml .= "<col min=\"{$colIdx}\" max=\"{$colIdx}\" width=\"{$width}\" customWidth=\"1\"/>";
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        $rowNumber = 1;

        if (! empty($headers)) {
            $xml .= "<row r=\"{$rowNumber}\" ht=\"24\" customHeight=\"1\">";
            foreach ($headers as $cIdx => $headerText) {
                $cellRef = $this->columnLetter($cIdx + 1).$rowNumber;
                $sIdx = $addSharedString((string) $headerText);
                $xml .= "<c r=\"{$cellRef}\" t=\"s\" s=\"1\"><v>{$sIdx}</v></c>";
            }
            $xml .= '</row>';
            $rowNumber++;
        }

        foreach ($rows as $row) {
            $xml .= "<row r=\"{$rowNumber}\" ht=\"20\" customHeight=\"1\">";
            foreach (array_values($row) as $cIdx => $cellValue) {
                $cellRef = $this->columnLetter($cIdx + 1).$rowNumber;
                if ($cellValue === null || $cellValue === '') {
                    $sIdx = $addSharedString('');
                    $xml .= "<c r=\"{$cellRef}\" t=\"s\" s=\"2\"><v>{$sIdx}</v></c>";
                } elseif (is_int($cellValue) || is_float($cellValue)) {
                    $style = is_float($cellValue) ? 4 : 3;
                    $xml .= "<c r=\"{$cellRef}\" s=\"{$style}\"><v>{$cellValue}</v></c>";
                } else {
                    $sIdx = $addSharedString((string) $cellValue);
                    $xml .= "<c r=\"{$cellRef}\" t=\"s\" s=\"2\"><v>{$sIdx}</v></c>";
                }
            }
            $xml .= '</row>';
            $rowNumber++;
        }

        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        return $xml;
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $index = intdiv($index - $mod, 26);
        }

        return $letter;
    }

    private function buildContentTypesXml(int $sheetCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $xml .= '<Default Extension="xml" ContentType="application/xml"/>';
        $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $xml .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= "<Override PartName=\"/xl/worksheets/sheet{$i}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }
        $xml .= '</Types>';

        return $xml;
    }

    private function buildRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /**
     * @param  array<int, string>  $sheetTitles
     */
    private function buildWorkbookXml(array $sheetTitles): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheets>';
        foreach ($sheetTitles as $idx => $title) {
            $sheetId = $idx + 1;
            $escaped = $this->escapeXml($title);
            $xml .= "<sheet name=\"{$escaped}\" sheetId=\"{$sheetId}\" r:id=\"rId{$sheetId}\"/>";
        }
        $xml .= '</sheets>';
        $xml .= '</workbook>';

        return $xml;
    }

    private function buildWorkbookRelsXml(int $sheetCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= "<Relationship Id=\"rId{$i}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$i}.xml\"/>";
        }
        $stylesId = $sheetCount + 1;
        $sharedId = $sheetCount + 2;
        $xml .= "<Relationship Id=\"rId{$stylesId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>";
        $xml .= "<Relationship Id=\"rId{$sharedId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings\" Target=\"sharedStrings.xml\"/>";
        $xml .= '</Relationships>';

        return $xml;
    }

    private function buildStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1">'
            .'<numFmt numFmtId="164" formatCode="0.0"/>'
            .'</numFmts>'
            .'<fonts count="2">'
            .'<font><sz val="11"/><color rgb="FF1F2937"/><name val="Arial"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1D4ED8"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border>'
            .'<left style="thin"><color rgb="FFD1D5DB"/></left>'
            .'<right style="thin"><color rgb="FFD1D5DB"/></right>'
            .'<top style="thin"><color rgb="FFD1D5DB"/></top>'
            .'<bottom style="thin"><color rgb="FFD1D5DB"/></bottom>'
            .'<diagonal/>'
            .'</border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="5">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    /**
     * @param  array<int, string>  $strings
     */
    private function buildSharedStringsXml(array $strings): string
    {
        $count = count($strings);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= "<sst xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" count=\"{$count}\" uniqueCount=\"{$count}\">";
        foreach ($strings as $str) {
            $escaped = $this->escapeXml($str);
            $xml .= "<si><t xml:space=\"preserve\">{$escaped}</t></si>";
        }
        $xml .= '</sst>';

        return $xml;
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Đóng gói mảng [đường_dẫn => nội_dung] thành tệp nén PKZIP tiêu chuẩn bằng gzdeflate + crc32.
     *
     * @param  array<string, string>  $files
     */
    private function packZipArchive(array $files): string
    {
        $localData = '';
        $centralDir = '';
        $offset = 0;
        $entriesCount = 0;

        foreach ($files as $name => $content) {
            $nameBytes = str_replace('\\', '/', $name);
            $nameLen = strlen($nameBytes);
            $uncompressedLen = strlen($content);
            // Unsigned 32-bit CRC32
            $crc = unpack('V', pack('N', crc32($content)))[1];
            // Alternatively crc32($content) & 0xFFFFFFFF directly in pack('V', crc32($content))
            $crcVal = crc32($content);
            $compressed = gzdeflate($content, 6);
            $compressedLen = strlen($compressed);

            // DOS timestamp cố định hợp lệ
            $dosTime = 0x0000;
            $dosDate = 0x597B;

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034B50,      // Local file header signature
                20,              // Version needed to extract (2.0)
                0x0800,          // General purpose bit flag (UTF-8 filenames)
                8,               // Compression method (8 = Deflate)
                $dosTime,
                $dosDate,
                $crcVal,
                $compressedLen,
                $uncompressedLen,
                $nameLen,
                0                // Extra field length
            );

            $localEntry = $localHeader.$nameBytes.$compressed;
            $localData .= $localEntry;

            $centralHeader = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014B50,      // Central file header signature
                20,              // Version made by
                20,              // Version needed to extract
                0x0800,          // UTF-8 flag
                8,               // Compression method (Deflate)
                $dosTime,
                $dosDate,
                $crcVal,
                $compressedLen,
                $uncompressedLen,
                $nameLen,
                0,               // Extra field length
                0,               // File comment length
                0,               // Disk number start
                0,               // Internal file attributes
                32,              // External file attributes
                $offset          // Relative offset of local header
            );

            $centralDir .= $centralHeader.$nameBytes;
            $offset += strlen($localEntry);
            $entriesCount++;
        }

        $endOfCentralDir = pack(
            'VvvvvVVv',
            0x06054B50,          // End of central dir signature
            0,                   // Number of this disk
            0,                   // Disk where central directory starts
            $entriesCount,       // Total entries on this disk
            $entriesCount,       // Total entries
            strlen($centralDir), // Size of central directory
            $offset,             // Offset of start of central directory
            0                    // ZIP file comment length
        );

        return $localData.$centralDir.$endOfCentralDir;
    }
}
