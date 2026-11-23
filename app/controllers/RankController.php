<?php

class RankController extends Controller
{
    public function index(string $type = 'views'): void
    {
        $valid = ['views', 'favorites', 'words', 'new', 'chapters'];

        $valid = Hook::applyFilters('rank_types', $valid);
        if (!in_array($type, $valid, true)) $type = 'views';

        $typeLabels = [
            'views' => Language::get('rank_views', '点击榜'), 'favorites' => Language::get('rank_favorites', '收藏榜'), 'words' => Language::get('rank_words', '字数榜'),
            'new' => Language::get('rank_new_book', '新书榜'), 'chapters' => Language::get('rank_chapters', '章节榜'),
        ];
        $typeLabels = Hook::applyFilters('rank_type_labels', $typeLabels);
        View::assign('seo', SeoService::getMeta('rank', [
            'rank_type'       => $type,
            'rank_type_label' => $typeLabels[$type] ?? Language::get('rank', '排行榜'),
        ]));
        $list = NovelService::attachDisplayNames(NovelModel::getRank($type, 50));
        $this->view('rank.tpl', [
            'type'       => $type,
            'list'       => $list,
            'novels'     => $list,
            'types'      => $valid,
            'typeLabels' => $typeLabels,
        ]);
    }
}
