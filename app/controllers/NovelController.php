<?php

class NovelController extends Controller
{
    public function detail(int $id): void
    {
        $service = new NovelService();
        $data = $service->getNovelDetail($id);
        if (!$data) {
            http_response_code(404);
            $this->view('404.tpl', ['message' => Language::get('novel_not_exists', '小说不存在')]);
            return;
        }

        $novel = $data['novel'];
        $catName = '';
        if (!empty($novel['category_id'])) {
            $cat = CategoryModel::findById((int)$novel['category_id']);
            $catName = $cat['name'] ?? '';
        }
        $statusLabel = ((int)($novel['status'] ?? 0) === 1) ? Language::get('status_finished_short', '完本') : Language::get('status_ongoing_short', '连载');
        $descPreview = excerpt(trim((string)($novel['description'] ?? '')), 150, '');

        $perPage = 100;
        $flatChapters = array();
        foreach (($data['volumes'] ?? array()) as $vol) {

            $chaptersByVolume[$vol['id']] = array('volume' => $vol, 'chapters' => array());
        }
        foreach (($data['chapters'] ?? array()) as $ch) {
            $flatChapters[] = $ch;
        }
        $totalChapters = count($flatChapters);
        $totalPages = max(1, (int)ceil($totalChapters / $perPage));
        $page = max(1, g('chapter_page', 1, 'int'));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;
        $pageChapters = array_slice($flatChapters, $offset, $perPage);
        foreach ($pageChapters as $ch) {
            $vid = $ch['volume_id'] ?? 0;
            if (!isset($chaptersByVolume[$vid])) {
                $chaptersByVolume[$vid] = array('volume' => array('id' => $vid, 'name' => Language::get('volume_main_text', '正文')), 'chapters' => array());
            }
            $chaptersByVolume[$vid]['chapters'][] = $ch;
        }

        foreach ($chaptersByVolume as $vid => $g) {
            if (empty($g['chapters'])) unset($chaptersByVolume[$vid]);
        }
        $data['chaptersByVolume'] = $chaptersByVolume;
        $data['chapterPage'] = $page;
        $data['chapterTotalPages'] = $totalPages;
        $data['chapterTotal'] = $totalChapters;
        $data['chapterPerPage'] = $perPage;

        $uid = $this->request->isLoggedIn() ? $this->request->userId() : 0;
        $data['inBookshelf'] = ($uid > 0) && BookshelfModel::exists($uid, $id);

        View::assign('seo', SeoService::getMeta('novel_detail', [
            'novel_title'         => $novel['title'] ?? '',
            'author'              => $novel['author'] ?? '',
            'category_name'        => $catName,
            'status_label'        => $statusLabel,
            'total_words'         => number_format((int)($novel['total_words'] ?? 0)),
            'total_chapters'      => (int)($novel['total_chapters'] ?? 0),
            'views'               => number_format((int)($novel['views'] ?? 0)),
            'favorites'           => number_format((int)($novel['favorites'] ?? 0)),
            'description_preview' => $descPreview,
            'chapter_page'        => $page,
            'chapter_total_pages'  => $totalPages,
        ]));

        $this->view('novel_detail.tpl', $data);
    }
}
