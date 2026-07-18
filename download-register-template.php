<?php
session_start();

function drt_xml_escape($value)
{
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function drt_excel_column_name($index)
{
    $index = (int)$index;
    $name = '';
    while ($index >= 0) {
        $name = chr(($index % 26) + 65) . $name;
        $index = intdiv($index, 26) - 1;
    }
    return $name;
}

function drt_build_sheet_xml($headers)
{
    $lastColumn = drt_excel_column_name(count($headers) - 1);
    $cells = '';

    foreach ($headers as $index => $header) {
        $cellRef = drt_excel_column_name($index) . '1';
        $cells .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . drt_xml_escape($header) . '</t></is></c>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="A1:' . $lastColumn . '1"/>'
        . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<sheetData><row r="1">' . $cells . '</row></sheetData>'
        . '<autoFilter ref="A1:' . $lastColumn . '1"/>'
        . '</worksheet>';
}

function drt_output_tsv_template($headers, $filename)
{
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    echo implode("\t", $headers) . "\r\n";
    exit();
}

$type = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : 'student';
if ($type !== 'teacher') {
    $type = 'student';
}

$headers = array(
    'userid',
    'firstname',
    'surname',
    'othernames',
    'gender',
    'residencetype',
    'birthday',
    'age',
    'postaladdress',
    'homeaddress',
    'hometown',
    'religion',
    'relationship',
    'beceindexnumber',
    'nextofkin_fullname',
    'nextofkin_contact',
    'email',
    'mobile',
    'username',
    'password',
    'systemtype',
    'staffstatus'
);

$sheetName = $type === 'teacher' ? 'Teacher Register' : 'Student Register';
$filename = $type === 'teacher'
    ? 'teacher_register_bulk_upload_template.xlsx'
    : 'student_register_bulk_upload_template.xlsx';

if (!class_exists('ZipArchive')) {
    drt_output_tsv_template($headers, preg_replace('/\.xlsx$/', '', $filename));
}

$sheetXml = drt_build_sheet_xml($headers);
$contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
    . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
    . '</Types>';
$rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
    . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
    . '</Relationships>';
$workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets><sheet name="' . drt_xml_escape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
    . '</workbook>';
$workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '</Relationships>';
$appXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
    . '<Application>OpenAI Codex</Application>'
    . '</Properties>';
$createdAt = gmdate('Y-m-d\TH:i:s\Z');
$coreXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
    . '<dc:title>' . drt_xml_escape($sheetName) . '</dc:title>'
    . '<dc:creator>OpenAI Codex</dc:creator>'
    . '<cp:lastModifiedBy>OpenAI Codex</cp:lastModifiedBy>'
    . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $createdAt . '</dcterms:created>'
    . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $createdAt . '</dcterms:modified>'
    . '</cp:coreProperties>';

$tempFile = tempnam(sys_get_temp_dir(), 'regtpl_');
$zip = new ZipArchive();
$openResult = $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($openResult !== true) {
    if (is_file($tempFile)) {
        @unlink($tempFile);
    }
    http_response_code(500);
    echo "Unable to create the Excel template right now.";
    exit();
}

$zip->addFromString('[Content_Types].xml', $contentTypesXml);
$zip->addFromString('_rels/.rels', $rootRelsXml);
$zip->addFromString('docProps/app.xml', $appXml);
$zip->addFromString('docProps/core.xml', $coreXml);
$zip->addFromString('xl/workbook.xml', $workbookXml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tempFile));
header('Cache-Control: max-age=0');
header('Pragma: public');
readfile($tempFile);
@unlink($tempFile);
exit();
?>
