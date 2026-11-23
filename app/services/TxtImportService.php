<?php

class TxtImportService
{

    public function splitByAuto(string $content, int $targetWords = 2000, int $maxExtra = 200): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = trim($content);
        if ($content === '') return [];

        $chapters = [];
        $len = mb_strlen($content, 'UTF-8');
        $pos = 0;

        while ($pos < $len) {
            $remaining = $len - $pos;

            if ($remaining <= $targetWords + $maxExtra) {
                $text = trim(mb_substr($content, $pos, $remaining, 'UTF-8'));
                if ($text !== '') {
                    $chapters[] = ['title' => '', 'content' => $text];
                }
                break;
            }

            $targetPos = $pos + $targetWords;
            $newlinePos = mb_strpos($content, "\n", $targetPos, 'UTF-8');

            if ($newlinePos === false) {
                $cutPos = $len;
            } else {
                $diff = $newlinePos - $targetPos;
                $cutPos = ($diff > $maxExtra) ? $targetPos : $newlinePos;
            }

            $text = trim(mb_substr($content, $pos, $cutPos - $pos, 'UTF-8'));
            if ($text !== '') {
                $chapters[] = ['title' => '', 'content' => $text];
            }

            $pos = $cutPos;
            while ($pos < $len && mb_substr($content, $pos, 1, 'UTF-8') === "\n") {
                $pos++;
            }
        }

        return $chapters;
    }

    public function splitByTags(string $content, string $titleStart, string $titleEnd,
                                 string $contentStart, string $contentEnd): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $chapters = [];
        $offset = 0;
        $len = mb_strlen($content, 'UTF-8');
        $csLen = mb_strlen($contentStart, 'UTF-8');
        $ceLen = mb_strlen($contentEnd, 'UTF-8');

        while ($offset < $len) {
            $startPos = mb_strpos($content, $contentStart, $offset, 'UTF-8');
            if ($startPos === false) break;

            $afterStart = $startPos + $csLen;
            $endPos = mb_strpos($content, $contentEnd, $afterStart, 'UTF-8');
            if ($endPos === false) break;

            $chContent = trim(mb_substr($content, $afterStart, $endPos - $afterStart, 'UTF-8'));

            $title = '';
            if ($titleStart !== '' && $titleEnd !== '') {
                $before = mb_substr($content, 0, $startPos, 'UTF-8');
                $tStart = mb_strrpos($before, $titleStart, 0, 'UTF-8');
                if ($tStart !== false) {
                    $tAfter = $tStart + mb_strlen($titleStart, 'UTF-8');
                    $tEnd = mb_strpos($before, $titleEnd, $tAfter, 'UTF-8');
                    if ($tEnd !== false) {
                        $title = trim(mb_substr($before, $tAfter, $tEnd - $tAfter, 'UTF-8'));
                    }
                }
            }

            if ($chContent !== '') {
                $chapters[] = ['title' => $title, 'content' => $chContent];
            }

            $offset = $endPos + $ceLen;
        }

        return $chapters;
    }

    public function normalizeEncoding(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $encoding = mb_detect_encoding(
                $content,
                ['GBK', 'GB2312', 'BIG5', 'UTF-16', 'UTF-16LE', 'UTF-16BE'],
                true
            );
            if ($encoding) {
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            } else {
                return ['ok' => false, 'content' => '', 'error' => t_admin('adm_txt_svc_encoding_bad', '无法识别文件编码，请转换为 UTF-8 或 GBK 后重试')];
            }
        }
        if (trim($content) === '') {
            return ['ok' => false, 'content' => '', 'error' => t_admin('adm_txt_svc_empty', '文件内容为空')];
        }
        return ['ok' => true, 'content' => $content, 'error' => ''];
    }

    public function clearNovelChapters(int $novelId): void
    {
        if ($novelId <= 0) return;
        $dir = ROOT_PATH . 'data/chapters/' . $novelId;
        if (is_dir($dir)) {
            $this->removeDirectoryFiles($dir);
        }
        ChapterModel::deleteByNovel($novelId);
        VolumeModel::deleteByNovel($novelId);
    }

    public function import(int $novelId, array $chapters, string $importMode): array
    {
        if (!$chapters) {
            return ['ok' => false, 'message' => t_admin('adm_txt_svc_no_chapters', '未能提取到任何章节内容，请检查文件或分隔符设置'), 'details' => [], 'overwritten' => 0];
        }

        $overwritten = 0;
        if ($importMode === 'overwrite') {
            $overwritten = ChapterModel::countByNovel($novelId);
            $this->clearNovelChapters($novelId);
        }

        $volume = VolumeModel::firstVolume($novelId);
        if ($volume) {
            $volumeId = (int)$volume['id'];
        } else {
            $volumeId = VolumeModel::create(['novel_id' => $novelId, 'title' => t_admin('adm_txt_default_volume', '默认卷'), 'sort' => 0]);
        }

        $sort = ChapterModel::getMaxSortInVolume($volumeId) + 1;
        $autoNum = $sort + 1;

        $details = [];
        $imported = 0;
        foreach ($chapters as $ch) {
            $title = trim((string)($ch['title'] ?? ''));
            if ($title === '') {
                $title = t_admin('adm_txt_chapter_name_fmt', '第 %d 章', $autoNum);
            }
            $chContent = (string)($ch['content'] ?? '');

            $filePath = ChapterModel::writeContent($novelId, $chContent);
            $wordCount = CommonService::smartWordCount($chContent);

            ChapterModel::create([
                'novel_id'   => $novelId,
                'volume_id'  => $volumeId,
                'title'      => $title,
                'word_count' => $wordCount,
                'file_path'  => $filePath,
                'sort'       => $sort,
                'is_vip'     => 0,
                'price'      => 0,
            ]);

            $details[] = ['title' => $title, 'words' => $wordCount];
            $sort++;
            $autoNum++;
            $imported++;
        }

        NovelModel::refreshStats($novelId);

        $message = $importMode === 'overwrite'
            ? t_admin('adm_txt_svc_overwrite_success', '已覆盖导入 %s 个章节（原有 %s 章及章节文件已清除）', $imported, $overwritten)
            : t_admin('adm_txt_svc_append_success', '成功导入 %s 个章节', $imported);

        return ['ok' => true, 'message' => $message, 'details' => $details, 'overwritten' => $overwritten];
    }

    private function removeDirectoryFiles(string $dir): void
    {
        $entries = glob($dir . '/*');
        if ($entries === false) return;
        foreach ($entries as $entry) {
            if (is_file($entry)) {
                @unlink($entry);
            } elseif (is_dir($entry)) {
                $this->removeDirectoryFiles($entry);
                @rmdir($entry);
            }
        }
    }
}
