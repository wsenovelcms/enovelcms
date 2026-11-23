<?php

class AuthorController extends Controller
{
    public function index(): void
    {
        $page = $this->request->get('page', 1, 'int') ?: 1;
        $result = UserModel::getAuthors($page, 20);
        $urlTpl = url('authors', ['page' => '@@PAGE@@']);
        View::assign('pageUrlTpl', $urlTpl);

        View::assign('seo', SeoService::getMeta('authors', [
            'page'        => $page,
            'total_pages' => (int)($result['totalPages'] ?? 1),
            'total'       => (int)($result['total'] ?? 0),
        ]));

        $this->view('authors.tpl', $result);
    }

    public function detail(int $id): void
    {
        $author = UserModel::findById($id);
        if (!$author) {
            http_response_code(404);
            $this->view('404.tpl', ['message' => Language::get('author_not_exists', '作者不存在')]);
            return;
        }

        unset($author['password'], $author['email'], $author['phone'],
              $author['alipay_account'], $author['wechat_account'],
              $author['withdraw_qrcode'], $author['total_income'],
              $author['available_income'], $author['real_name']);

        $page = $this->request->get('page', 1, 'int') ?: 1;
        $perPage = 5;
        $novelPage = NovelModel::getByAuthor($id, $page, $perPage);
        if (!empty($novelPage['list'])) {
            $novelPage['list'] = NovelService::attachDisplayNames($novelPage['list']);
            $catMap = [];
            foreach (CategoryModel::getAllCategories() as $cat) {
                $catMap[(int)$cat['id']] = $cat['name'];
            }
            foreach ($novelPage['list'] as &$row) {
                $row['category_name'] = $catMap[(int)($row['category_id'] ?? 0)] ?? '';
            }
            unset($row);
        }

        $statRow = NovelModel::getAuthorStats($id);

        View::assign('pageUrlTpl', url('author', ['id' => $id, 'query' => ['page' => '@@PAGE@@']]));

        $intro = excerpt(trim((string)($author['signature'] ?? '')), 150, '');

        View::assign('seo', SeoService::getMeta('author_detail', [
            'author'           => $author['pen_name'] ?: $author['username'],
            'author_username'  => $author['username'],
            'novel_count'     => $statRow['count'],
            'total_words'     => number_format($statRow['words']),
            'chapter_count'   => (int)($novelPage['total'] ?? 0),
            'introduction'    => $intro,
            'page'            => $page,
            'total_pages'     => (int)($novelPage['totalPages'] ?? 1),
        ]));

        $this->view('author_detail.tpl', [
            'author' => $author,
            'novels' => $novelPage['list'] ?? [],
            'page' => $page,
            'totalPages' => (int)($novelPage['totalPages'] ?? 1),
            'totalWorks' => $statRow['count'],
            'totalWords' => $statRow['words'],
            'totalViews' => $statRow['views'],
        ]);
    }
}
