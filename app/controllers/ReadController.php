<?php

class ReadController extends Controller
{
    public function index(int $novelId, int $chapterId): void
    {
        $userId = $this->request->userId();
        $service = new NovelService();
        $data = $service->getReadData($novelId, $chapterId, $userId);

        if (isset($data['error'])) {
            http_response_code(404);
            $this->view('404.tpl', ['message' => $data['error']]);
            return;
        }

        if ($this->request->isPost() && $this->request->post('do_purchase', 0, 'int')) {
            if (!Security::verifyCsrf()) {
                $this->redirect(UrlService::read($novelId, $chapterId));
            }
            $chapterService = new ChapterService();
            $result = $chapterService->purchase($userId, $chapterId);
            if ($result['ok']) {
                $this->redirect(UrlService::read($novelId, $chapterId));
            }
            View::assign('purchaseError', $result['msg']);

            $data = $service->getReadData($novelId, $chapterId, $userId);
        }

        $novel = $data['novel'];
        $chapter = $data['chapter'];
        $catName = '';
        if (!empty($novel['category_id'])) {
            $cat = CategoryModel::findById((int)$novel['category_id']);
            $catName = $cat['name'] ?? '';
        }
        $chapterIntro = excerpt(trim((string)($chapter['content'] ?? '')), 150, '');

        View::assign('seo', SeoService::getMeta('read', [
            'novel_title'       => $novel['title'] ?? '',
            'chapter_title'     => $chapter['title'] ?? '',
            'author'            => $novel['author'] ?? '',
            'category_name'     => $catName,
            'chapter_word_count' => number_format((int)($chapter['word_count'] ?? 0)),
            'chapter_intro'     => $chapterIntro,
            'chapter_views'     => number_format((int)($chapter['views'] ?? 0)),
            'total_chapters'    => (int)($novel['chapter_count'] ?? 0),
        ]));
        $this->view('read.tpl', $data);
    }
}
