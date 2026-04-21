<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class RichContentImportService
{
    protected const MAX_IMAGE_COUNT = 20;
    protected const DEFAULT_MAX_SINGLE_IMAGE_BYTES = 8 * 1024 * 1024;

    protected int $decodedImageBytes = 0;
    protected int $maxSingleImageBytes;
    protected int $maxTotalImageBytes;

    public function __construct(?int $maxSingleImageBytes = null, ?int $maxTotalImageBytes = null)
    {
        $single = max(1, (int) ($maxSingleImageBytes ?? self::DEFAULT_MAX_SINGLE_IMAGE_BYTES));
        $total = (int) ($maxTotalImageBytes ?? ($single * self::MAX_IMAGE_COUNT));

        $this->maxSingleImageBytes = $single;
        $this->maxTotalImageBytes = max($single, $total);
    }

    /**
     * @return array{html:string,warnings:array<int,string>,image_count:int,image_limit:int}
     */
    public function importFromHtml(string $html): array
    {
        $sanitized = ContentHtmlSanitizer::sanitize($html);
        $imageCount = $this->countImageNodes($sanitized);

        if ($imageCount > self::MAX_IMAGE_COUNT) {
            throw new RuntimeException(sprintf('图片数量超出限制：当前 %d 张，最多允许 %d 张。', $imageCount, self::MAX_IMAGE_COUNT));
        }

        return [
            'html' => $sanitized,
            'warnings' => [],
            'image_count' => $imageCount,
            'image_limit' => self::MAX_IMAGE_COUNT,
        ];
    }

    /**
     * @return array{html:string,warnings:array<int,string>,image_count:int,image_limit:int}
     */
    public function importFromOfficeFile(UploadedFile $file): array
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: ''));
        if (! in_array($extension, ['docx', 'doc', 'wps'], true)) {
            throw new RuntimeException('仅支持导入 docx、doc、wps 文件。');
        }

        $warnings = [];
        $sourcePath = (string) $file->getRealPath();
        if ($sourcePath === '' || ! is_file($sourcePath)) {
            throw new RuntimeException('读取上传文件失败，请重新选择文件。');
        }

        $cleanupPaths = [];
        $cleanupDirs = [];
        $docxPath = $sourcePath;

        if ($extension !== 'docx') {
            [$convertedPath, $convertedDir] = $this->convertOfficeFileToDocx($sourcePath, $extension);
            $cleanupPaths[] = $convertedPath;
            $cleanupDirs[] = $convertedDir;
            $docxPath = $convertedPath;
            $warnings[] = '当前文件已通过兼容模式转换为 docx 后导入，复杂格式可能有细微差异。';
        }

        try {
            $this->resetDocxImageCounters();
            $html = $this->buildHtmlFromDocx($docxPath);
        } finally {
            foreach ($cleanupPaths as $path) {
                @unlink($path);
            }
            foreach ($cleanupDirs as $dir) {
                $this->deleteDirectory($dir);
            }
        }

        $sanitized = ContentHtmlSanitizer::sanitize($html);
        $imageCount = $this->countImageNodes($sanitized);

        if ($imageCount > self::MAX_IMAGE_COUNT) {
            throw new RuntimeException(sprintf('图片数量超出限制：当前 %d 张，最多允许 %d 张。', $imageCount, self::MAX_IMAGE_COUNT));
        }

        return [
            'html' => $sanitized,
            'warnings' => $warnings,
            'image_count' => $imageCount,
            'image_limit' => self::MAX_IMAGE_COUNT,
        ];
    }

    protected function resetDocxImageCounters(): void
    {
        $this->decodedImageBytes = 0;
    }

    protected function countImageNodes(string $html): int
    {
        if (trim($html) === '') {
            return 0;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $encoded = mb_encode_numericentity('<div id="import-root">'.$html.'</div>', [0x80, 0x10FFFF, 0, ~0], 'UTF-8');
        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($encoded, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        if (! $loaded) {
            return 0;
        }

        $root = $document->getElementById('import-root');
        if (! $root instanceof DOMElement) {
            return 0;
        }

        return $root->getElementsByTagName('img')->length;
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function convertOfficeFileToDocx(string $sourcePath, string $sourceExt): array
    {
        $officeBinary = $this->detectLibreOfficeBinary();
        if ($officeBinary === null) {
            throw new RuntimeException('服务器未安装 LibreOffice，当前仅可直接导入 docx。请先将文件另存为 docx 后重试。');
        }

        $tempDir = storage_path('app/tmp/office-import-'.Str::uuid()->toString());
        if (! is_dir($tempDir) && ! @mkdir($tempDir, 0775, true) && ! is_dir($tempDir)) {
            throw new RuntimeException('创建临时目录失败，无法导入该文件。');
        }

        $targetStem = pathinfo((string) basename($sourcePath), PATHINFO_FILENAME);
        $targetPath = $tempDir.DIRECTORY_SEPARATOR.$targetStem.'.docx';

        $process = new Process([
            $officeBinary,
            '--headless',
            '--convert-to',
            'docx',
            '--outdir',
            $tempDir,
            $sourcePath,
        ]);
        $process->setTimeout(25);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($targetPath)) {
            $this->deleteDirectory($tempDir);

            throw new RuntimeException(sprintf(
                '%s 文件转换失败，请优先另存为 docx 后重试。',
                strtoupper($sourceExt)
            ));
        }

        return [$targetPath, $tempDir];
    }

    protected function detectLibreOfficeBinary(): ?string
    {
        foreach (['soffice', '/usr/bin/soffice', '/usr/local/bin/soffice'] as $candidate) {
            if (str_starts_with($candidate, '/')) {
                if (is_file($candidate) && is_executable($candidate)) {
                    return $candidate;
                }
                continue;
            }

            if (! function_exists('shell_exec')) {
                continue;
            }

            $resolved = trim((string) @\shell_exec('command -v '.escapeshellarg($candidate).' 2>/dev/null'));
            if ($resolved !== '' && is_file($resolved) && is_executable($resolved)) {
                return $resolved;
            }
        }

        return null;
    }

    protected function buildHtmlFromDocx(string $docxPath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($docxPath) !== true) {
            throw new RuntimeException('无法读取 docx 文件内容。');
        }

        try {
            $documentXml = (string) $zip->getFromName('word/document.xml');
            if ($documentXml === '') {
                throw new RuntimeException('未找到可解析的正文内容。');
            }

            $relsMap = $this->loadDocumentRelationsMap((string) $zip->getFromName('word/_rels/document.xml.rels'));

            $xml = new DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $loaded = $xml->loadXML($documentXml);
            libxml_clear_errors();
            if (! $loaded) {
                throw new RuntimeException('正文解析失败，请尝试另存为 docx 后重试。');
            }

            $xpath = new DOMXPath($xml);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

            $html = new DOMDocument('1.0', 'UTF-8');
            $htmlRoot = $html->createElement('div');
            $html->appendChild($htmlRoot);

            $bodyNodes = $xpath->query('/w:document/w:body/*');
            if ($bodyNodes === false) {
                return '';
            }

            foreach ($bodyNodes as $bodyNode) {
                if (! $bodyNode instanceof \DOMElement) {
                    continue;
                }

                if ($bodyNode->localName === 'p') {
                    $this->appendParagraph($html, $htmlRoot, $xpath, $bodyNode, $zip, $relsMap);
                    continue;
                }

                if ($bodyNode->localName === 'tbl') {
                    $this->appendTable($html, $htmlRoot, $xpath, $bodyNode, $zip, $relsMap);
                }
            }

            $output = '';
            foreach ($htmlRoot->childNodes as $child) {
                $output .= $html->saveHTML($child);
            }

            return trim($output);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<string, string>
     */
    protected function loadDocumentRelationsMap(string $relsXml): array
    {
        if ($relsXml === '') {
            return [];
        }

        $xml = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $loaded = $xml->loadXML($relsXml);
        libxml_clear_errors();
        if (! $loaded) {
            return [];
        }

        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $map = [];
        $nodes = $xpath->query('/rel:Relationships/rel:Relationship');
        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $id = trim((string) $node->getAttribute('Id'));
            $target = trim((string) $node->getAttribute('Target'));
            if ($id === '' || $target === '') {
                continue;
            }

            $normalized = str_replace('\\', '/', $target);
            $normalized = str_starts_with($normalized, '/') ? ltrim($normalized, '/') : 'word/'.ltrim($normalized, './');
            $map[$id] = $normalized;
        }

        return $map;
    }

    /**
     * @param array<string, string> $relsMap
     */
    protected function appendParagraph(DOMDocument $html, DOMElement $parent, DOMXPath $xpath, \DOMElement $paragraphNode, \ZipArchive $zip, array $relsMap): void
    {
        $styleValue = trim((string) $xpath->evaluate('string(./w:pPr/w:pStyle/@w:val)', $paragraphNode));
        $tagName = 'p';

        if (preg_match('/^Heading([1-4])$/i', $styleValue, $matches) === 1) {
            $tagName = 'h'.$matches[1];
        }

        $paragraph = $html->createElement($tagName);
        $hasVisibleContent = false;

        foreach ($xpath->query('./w:r', $paragraphNode) ?: [] as $runNode) {
            if (! $runNode instanceof \DOMElement) {
                continue;
            }

            $text = '';
            foreach ($xpath->query('.//w:t', $runNode) ?: [] as $textNode) {
                $text .= (string) ($textNode->textContent ?? '');
            }

            foreach ($xpath->query('.//w:br', $runNode) ?: [] as $_) {
                $paragraph->appendChild($html->createElement('br'));
                $hasVisibleContent = true;
            }

            $imageNode = $this->extractRunImageNode($html, $xpath, $runNode, $zip, $relsMap);
            if ($imageNode instanceof DOMElement) {
                $paragraph->appendChild($imageNode);
                $hasVisibleContent = true;
            }

            if ($text === '') {
                continue;
            }

            $fragment = $this->buildFormattedTextNode($html, $xpath, $runNode, $text);
            $paragraph->appendChild($fragment);
            $hasVisibleContent = true;
        }

        if ($hasVisibleContent) {
            $parent->appendChild($paragraph);
        }
    }

    /**
     * @param array<string, string> $relsMap
     */
    protected function appendTable(DOMDocument $html, DOMElement $parent, DOMXPath $xpath, \DOMElement $tableNode, \ZipArchive $zip, array $relsMap): void
    {
        $table = $html->createElement('table');
        $tbody = $html->createElement('tbody');
        $table->appendChild($tbody);

        foreach ($xpath->query('./w:tr', $tableNode) ?: [] as $rowNode) {
            if (! $rowNode instanceof \DOMElement) {
                continue;
            }

            $tr = $html->createElement('tr');
            $hasCell = false;

            foreach ($xpath->query('./w:tc', $rowNode) ?: [] as $cellNode) {
                if (! $cellNode instanceof \DOMElement) {
                    continue;
                }

                $td = $html->createElement('td');
                $cellContent = '';

                foreach ($xpath->query('.//w:p', $cellNode) ?: [] as $pNode) {
                    if (! $pNode instanceof \DOMElement) {
                        continue;
                    }

                    $text = trim((string) $xpath->evaluate('string(.)', $pNode));
                    if ($text !== '') {
                        $cellContent .= ($cellContent === '' ? '' : "\n").$text;
                    }
                }

                if ($cellContent !== '') {
                    $td->appendChild($html->createTextNode($cellContent));
                }

                foreach ($xpath->query('.//w:r', $cellNode) ?: [] as $runNode) {
                    if (! $runNode instanceof \DOMElement) {
                        continue;
                    }

                    $imageNode = $this->extractRunImageNode($html, $xpath, $runNode, $zip, $relsMap);
                    if ($imageNode instanceof DOMElement) {
                        $td->appendChild($imageNode);
                    }
                }

                $tr->appendChild($td);
                $hasCell = true;
            }

            if ($hasCell) {
                $tbody->appendChild($tr);
            }
        }

        if ($tbody->childNodes->length > 0) {
            $parent->appendChild($table);
        }
    }

    protected function buildFormattedTextNode(DOMDocument $html, DOMXPath $xpath, \DOMElement $runNode, string $text): DOMElement|\DOMText
    {
        $node = $html->createTextNode($text);
        $formatTags = [];

        if ((string) $xpath->evaluate('string(./w:rPr/w:b/@w:val)', $runNode) !== 'false' && $xpath->query('./w:rPr/w:b', $runNode)?->length) {
            $formatTags[] = 'strong';
        }
        if ((string) $xpath->evaluate('string(./w:rPr/w:i/@w:val)', $runNode) !== 'false' && $xpath->query('./w:rPr/w:i', $runNode)?->length) {
            $formatTags[] = 'em';
        }
        if ((string) $xpath->evaluate('string(./w:rPr/w:u/@w:val)', $runNode) !== 'none' && $xpath->query('./w:rPr/w:u', $runNode)?->length) {
            $formatTags[] = 'u';
        }

        if ($formatTags === []) {
            return $node;
        }

        $wrapped = $node;
        foreach (array_reverse($formatTags) as $tagName) {
            $element = $html->createElement($tagName);
            $element->appendChild($wrapped);
            $wrapped = $element;
        }

        return $wrapped;
    }

    /**
     * @param array<string, string> $relsMap
     */
    protected function extractRunImageNode(DOMDocument $html, DOMXPath $xpath, \DOMElement $runNode, \ZipArchive $zip, array $relsMap): ?DOMElement
    {
        $embedId = trim((string) $xpath->evaluate('string(.//a:blip/@r:embed)', $runNode));
        if ($embedId === '' || ! isset($relsMap[$embedId])) {
            return null;
        }

        $imagePath = $relsMap[$embedId];
        $content = $zip->getFromName($imagePath);
        if (! is_string($content) || $content === '') {
            return null;
        }

        $bytes = strlen($content);
        if ($bytes <= 0) {
            return null;
        }
        if ($bytes > $this->maxSingleImageBytes) {
            throw new RuntimeException(sprintf(
                '导入失败：检测到单张图片超过 %dMB，请压缩后重试。',
                (int) ceil($this->maxSingleImageBytes / 1024 / 1024)
            ));
        }

        $this->decodedImageBytes += $bytes;
        if ($this->decodedImageBytes > $this->maxTotalImageBytes) {
            throw new RuntimeException(sprintf(
                '导入失败：图片总量过大（超过 %dMB），请减少图片后重试。',
                (int) ceil($this->maxTotalImageBytes / 1024 / 1024)
            ));
        }

        $extension = strtolower((string) pathinfo($imagePath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        $img = $html->createElement('img');
        $img->setAttribute('src', 'data:'.$mime.';base64,'.base64_encode($content));
        $img->setAttribute('alt', '导入图片');

        return $img;
    }

    protected function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = array_diff(scandir($path) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $target = $path.DIRECTORY_SEPARATOR.$item;
            if (is_dir($target)) {
                $this->deleteDirectory($target);
                continue;
            }
            @unlink($target);
        }

        @rmdir($path);
    }
}
