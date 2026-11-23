<?php

class LibraryController extends Controller
{
    public function index(int $categoryId = 0): void
    {
        $page = $this->request->get('page', 1, 'int') ?: 1;
        $perPage = (int)SettingModel::get('library_per_page', 20);

        $vipInput = $this->request->get('vip_filter', 'all', 'str');
        if ($vipInput === '1' || $vipInput === 'vip') $vipFilter = 'vip';
        elseif ($vipInput === '0' || $vipInput === 'free') $vipFilter = 'free';
        else $vipFilter = 'all';

        $filters = [
            'category_id' => $categoryId ?: $this->request->get('category', 0, 'int'),
            'tag_id'      => $this->request->get('tag_id', 0, 'int'),
            'order_by'    => $this->request->get('order_by', 'update', 'str'),
            'vip_filter'  => $vipFilter,
            'status'      => $this->request->get('status', 'all', 'str'),
            'keyword'     => $this->request->get('keyword', '', 'str'),
            'min_words'   => $this->request->get('min_words', 0, 'int'),
            'max_words'   => $this->request->get('max_words', 0, 'int'),
        ];

        $validOrders = Hook::applyFilters('library_order_options', ['update', 'new', 'views', 'favorites', 'words']);
        $orderLabels = Hook::applyFilters('library_order_labels', [
            'update'     => Language::get('sort_update', '最近更新'),
            'new'        => Language::get('sort_latest', '最新入库'),
            'views'      => Language::get('sort_views', '点击最多'),
            'favorites'  => Language::get('sort_favorites', '最多收藏'),
            'words'      => Language::get('sort_words', '最多字数'),
        ]);

        if (!in_array($filters['order_by'], $validOrders, true)) {
            $filters['order_by'] = 'update';
        }

        $filters = Hook::applyFilters('library_filters', $filters, $this->request);

        $service = new NovelService();
        $result = $service->getLibraryData($filters, $page, $perPage);

        $catName = '';
        if (!empty($filters['category_id'])) {
            $cat = CategoryModel::findById((int)$filters['category_id']);
            $catName = $cat['name'] ?? '';
        }
        $statusLabels = Hook::applyFilters('library_status_labels', [
            'all'      => Language::get('all_label', '全部'),
            'ongoing'  => Language::get('status_ongoing_short', '连载'),
            'finished' => Language::get('status_finished_short', '完本'),
        ]);
        $vipLabels = Hook::applyFilters('library_vip_labels', [
            'all'  => Language::get('all_label', '全部'),
            'vip'  => Language::get('vip_label_filter', 'VIP'),
            'free' => Language::get('free_label', '免费'),
        ]);
        $statusLabel = $statusLabels[$filters['status']] ?? ($statusLabels['all'] ?? Language::get('all_label', '全部'));
        $orderLabel = $orderLabels[$filters['order_by']] ?? Language::get('sort_update', '最近更新');
        $vipLabel = $vipLabels[$filters['vip_filter']] ?? ($vipLabels['all'] ?? Language::get('all_label', '全部'));

        View::assign('seo', SeoService::getMeta('library', [
            'page'           => $page,
            'total_pages'    => (int)($result['totalPages'] ?? 1),
            'total'          => (int)($result['total'] ?? 0),
            'category_name'  => $catName,
            'status_label'   => $statusLabel,
            'order_by_label' => $orderLabel,
            'vip_filter_label' => $vipLabel,
            'min_words'      => (int)$filters['min_words'],
            'max_words'      => (int)$filters['max_words'],
        ]));

        $this->view('library.tpl', array_merge($result, [
            'categories'      => CategoryModel::getCategoryTree(),
            'allTags'         => TagModel::getAllTags(),
            'currentTag'      => (int)$filters['tag_id'],
            'filters'         => $filters,
            'currentCategory' => (int)$filters['category_id'],
            'novels'         => $result['list'] ?? [],
            'orderOptions'    => $validOrders,
            'orderLabels'     => $orderLabels,
            'statusLabels'    => $statusLabels,
            'vipLabels'       => $vipLabels,
        ]));
    }
}
