<?php

class SearchController extends Controller
{
    public function index(): void
    {
        $keyword = trim($this->request->get('keyword', '', 'str'));
        if ($keyword === '') {
            $keyword = trim($this->request->get('q', '', 'str'));
        }
        $page = $this->request->get('page', 1, 'int') ?: 1;
        $perPage = (int)SettingModel::get('library_per_page', 20);

        $result = ['list' => [], 'total' => 0, 'keyword' => $keyword, 'page' => $page, 'totalPages' => 0];
        if ($keyword !== '') {
            $search = NovelModel::search($keyword, $page, $perPage);
            if (!empty($search['list'])) $search['list'] = NovelService::attachDisplayNames($search['list']);
            $result = array_merge($result, $search);
            View::assign('seo', SeoService::getMeta('search', [
                'keyword'       => $keyword,
                'page'          => $page,
                'total_results' => (int)($result['total'] ?? 0),
            ]));
        }
        $this->view('search.tpl', $result);
    }
}
