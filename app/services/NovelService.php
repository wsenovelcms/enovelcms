<?php

class NovelService
{

    public function getHomeData(): array
    {
        $blocks = $this->getHomeBlocks();

        $rankNames   = ['rank_views', 'rank_favorites', 'rank_words'];
        $randomNames = ['random_novels'];

        $heroNames   = ['hero_slider', 'hero_table', 'hero_rank'];

        $homeSkip    = ['related_novels'];

        $heroSlider = $heroTable = $heroRank = null;
        $fallback = ['slider' => null, 'table' => null, 'rank' => null];
        $sections = [];
        $rankBlocks = [];
        $randomBlocks = [];
        foreach ($blocks as $b) {
            if (in_array($b['name'], $homeSkip, true)) continue;
            switch ($b['name']) {
                case 'hero_slider':   $heroSlider = $b; break;
                case 'hero_table':    $heroTable  = $b; break;
                case 'hero_rank':     $heroRank   = $b; break;
                case 'featured_novels': $fallback['slider'] = $b; break;
                case 'latest_updates':   $fallback['table']  = $b; break;
                case 'rank_views':       $fallback['rank']   = $b; break;
            }

            if (in_array($b['name'], $heroNames, true)) continue;
            if (in_array($b['name'], $rankNames, true)) {
                $rankBlocks[] = $b;
            } elseif (in_array($b['name'], $randomNames, true)) {
                $randomBlocks[] = $b;
            } else {
                $sections[] = $b;
            }
        }

        if (empty($heroSlider['novels']) && !empty($fallback['slider']['novels'])) $heroSlider = $fallback['slider'];
        if (empty($heroTable['novels'])  && !empty($fallback['table']['novels']))  $heroTable  = $fallback['table'];
        if (empty($heroRank['novels'])   && !empty($fallback['rank']['novels']))   $heroRank   = $fallback['rank'];

        return [
            'announcements' => AnnouncementModel::getActive(10),
            'categories'    => CategoryModel::getCategoryTree(),
            'heroSlider'    => $heroSlider,
            'heroTable'     => $heroTable,
            'heroRank'      => $heroRank,
            'sections'      => $sections,
            'rankBlocks'    => $rankBlocks,
            'randomBlocks'  => $randomBlocks,
            'blocks'        => $blocks,
        ];
    }

    public static function attachDisplayNames(array $rows): array
    {
        if (!$rows) return $rows;
        $ids = [];
        foreach ($rows as $row) {
            $uid = (int)($row['user_id'] ?? 0);
            if ($uid > 0) $ids[] = $uid;
        }
        $map = UserModel::displayNameMap($ids);
        $fallback = Language::get('anonymous_author', '佚名');
        foreach ($rows as $key => $row) {
            $uid = (int)($row['user_id'] ?? 0);
            $name = $map[$uid] ?? '';
            if ($name === '') $name = trim((string)($row['author'] ?? ''));
            if ($name === '') $name = $fallback;
            $rows[$key]['display_name'] = $name;
        }
        return $rows;
    }

    public function getHomeBlocks(): array
    {
        $blocks = [];
        foreach (DiyBlockModel::all([], 'id ASC') as $block) {
            $blocks[] = [
                'id'       => (int)$block['id'],
                'name'     => (string)$block['name'],
                'title'    => (string)($block['title'] ?: $block['name']),
                'order_by' => (string)($block['order_by'] ?? 'views'),
                'num'      => (int)$block['num'],
                'novels'   => self::attachDisplayNames($this->resolveBlockNovels($block)),
            ];
        }
        return $blocks;
    }

    private function resolveBlockNovels(array $block): array
    {
        $cacheTime = (int)($block['cache_time'] ?? 0);
        if ($cacheTime > 0 && !empty($block['data']) && !empty($block['updated_at'])) {
            $age = time() - strtotime((string)$block['updated_at']);
            if ($age >= 0 && $age < $cacheTime) {
                $decoded = json_decode((string)$block['data'], true);
                if (is_array($decoded)) return $decoded;
            }
        }

        $novels = $this->generateBlockData($block);

        if ($cacheTime > 0) {
            DiyBlockModel::cacheData((int)$block['id'], json_encode($novels, JSON_UNESCAPED_UNICODE));
        }
        return $novels;
    }

    public function getBlockNovelsByName(string $name): array
    {
        $block = DiyBlockModel::getByName($name);
        if (!$block) return [];
        return $this->resolveBlockNovels($block);
    }

    public function getRelatedNovels(): array
    {
        $related = $this->getBlockNovelsByName('related_novels');
        if (!empty($related)) return $related;
        return NovelModel::getRandom(6);
    }

    public function generateBlockData(array $block): array
    {
        $t = NovelModel::tableName();
        $where = '1=1';
        $params = [];

        if (in_array($block['order_by'], ['vip_sales_amount', 'vip_sales_count'], true)) {
            $where .= ' AND n.is_vip = 1';
        }
        if (!empty($block['category_id']) && $block['category_id'] !== '0') {
            $rawIds = explode(',', $block['category_id']);
            $catIds = array();
            foreach ($rawIds as $rawId) {
                $cid = (int)trim($rawId);
                if ($cid > 0) $catIds[] = $cid;
            }
            $catIds = array_values(array_unique($catIds));
            if ($catIds) {
                $placeholders = implode(',', array_fill(0, count($catIds), '?'));
                $where .= " AND n.category_id IN ({$placeholders})";
                foreach ($catIds as $cid) {
                    $params[] = $cid;
                }
            }
        }
        if (!empty($block['tag_id']) && $block['tag_id'] !== '0') {
            $rawTagIds = explode(',', $block['tag_id']);
            $blockTagIds = array();
            foreach ($rawTagIds as $rawTagId) {
                $tid = (int)trim($rawTagId);
                if ($tid > 0) $blockTagIds[] = $tid;
            }
            $blockTagIds = array_values(array_unique($blockTagIds));
            if ($blockTagIds && NovelModel::db()->tableExists('novel_tags')) {
                $nt = tb('novel_tags');
                $tagPh = implode(',', array_fill(0, count($blockTagIds), '?'));
                $where .= " AND EXISTS (SELECT 1 FROM {$nt} nt WHERE nt.novel_id = n.id AND nt.tag_id IN ({$tagPh}))";
                foreach ($blockTagIds as $tid) {
                    $params[] = $tid;
                }
            }
        }
        if (($block['status'] ?? 'all') !== 'all') {
            $where .= ' AND n.status = ?';
            $params[] = ($block['status'] === 'ongoing') ? 0 : 1;
        }
        if ((int)($block['min_words'] ?? 0) > 0) {
            $where .= ' AND n.total_words >= ?';
            $params[] = (int)$block['min_words'];
        }
        if ((int)($block['max_words'] ?? 0) > 0) {
            $where .= ' AND n.total_words <= ?';
            $params[] = (int)$block['max_words'];
        }
        $timeCondition = '';
        if (($block['time_range'] ?? 'all') !== 'all') {
            $interval = null;
            switch ($block['time_range']) {
                case 'today': $interval = '1 DAY'; break;
                case 'week':  $interval = '7 DAY'; break;
                case 'month': $interval = '1 MONTH'; break;
                case 'year':  $interval = '1 YEAR'; break;
            }
            if ($interval) $timeCondition = "n.created_at >= DATE_SUB(NOW(), INTERVAL {$interval})";
        }

        $extraWhere = Hook::applyFilters('diy_block_where', ['where' => '', 'params' => []], $block);
        if (is_array($extraWhere) && !empty($extraWhere['where']) && is_string($extraWhere['where'])) {
            $where .= ' ' . $extraWhere['where'];
            if (!empty($extraWhere['params']) && is_array($extraWhere['params'])) {
                array_push($params, ...array_values($extraWhere['params']));
            }
        }

        $orderBy = '';
        switch ($block['order_by']) {
            case 'views':     $orderBy = 'n.views DESC'; break;
            case 'new':       $orderBy = 'n.created_at DESC'; break;
            case 'update':    $orderBy = 'n.last_chapter_time DESC, n.id DESC'; break;
            case 'favorites': $orderBy = 'n.favorites DESC'; break;
            case 'chapters':  $orderBy = 'n.total_chapters DESC'; break;
            case 'words':     $orderBy = 'n.total_words DESC'; break;
            case 'vip_sales_amount': $orderBy = 'vip_sales_amount DESC, n.id DESC'; break;
            case 'vip_sales_count':  $orderBy = 'vip_sales_count DESC, n.id DESC'; break;
            case 'random':    $orderBy = 'RAND()'; break;
            case 'custom':    $orderBy = ''; break;
            default:          $orderBy = 'n.id DESC';
        }

        $defaultOrderBy = $orderBy;
        $orderBy = (string)Hook::applyFilters('diy_block_order_sql', $orderBy, $block);
        if ($block['order_by'] !== 'random' && $orderBy !== '' && !Model::isSafeOrderSql($orderBy)) {
            $orderBy = $defaultOrderBy;
        }

        $extraFields = (string)Hook::applyFilters('diy_block_fields', '', $block);
        $selectRaw = "n.id, n.title, n.author, n.user_id, n.category_id, n.cover, n.description,
                       n.status, n.views, n.total_words, n.favorites, n.created_at,
                       n.is_vip, c.name AS category_name,
                       n.last_chapter_id,
                       n.last_chapter_title,
                       n.last_chapter_is_vip,
                       n.last_chapter_time, n.total_chapters,
                       (SELECT COALESCE(SUM(price),0) FROM " . tb('chapter_purchases') . " WHERE novel_id = n.id) AS vip_sales_amount,
                       (SELECT COUNT(*) FROM " . tb('chapter_purchases') . " WHERE novel_id = n.id) AS vip_sales_count";
        if ($extraFields !== '') $selectRaw .= ', ' . $extraFields;

        $qb = NovelModel::db()->from('novels', 'n')
            ->selectRaw($selectRaw)
            ->leftJoin('categories', 'c', 'n.category_id = c.id');

        if ($where) $qb->where($where, $params);
        if ($timeCondition) $qb->where($timeCondition);

        $num = (int)($block['num'] ?? 10);
        if ($block['order_by'] !== 'custom') {
            if ($orderBy) $qb->orderBy($orderBy);
            $novels = $qb->limit($num)->get();
        } else {
            $all = $qb->get();
            if (!$all) return [];
            $map = [];
            foreach ($all as $n) $map[$n['id']] = $n;
            $customIds = array_filter(array_map('trim', explode(',', $block['custom_ids'] ?? '')), 'is_numeric');
            $novels = [];
            foreach ($customIds as $id) {
                $id = (int)$id;
                if (isset($map[$id])) { $novels[] = $map[$id]; unset($map[$id]); }
            }
            $remaining = array_values($map);
            usort($remaining, function ($a, $b) { return (int)$b['id'] - (int)$a['id']; });
            $novels = array_slice(array_merge($novels, $remaining), 0, $num);
        }
        return $this->normalize($novels);
    }

    private function normalize(array $novels): array
    {
        foreach ($novels as &$n) {
            $n['last_chapter_id'] = (int)($n['last_chapter_id'] ?? 0);
            $n['last_chapter_title'] = $n['last_chapter_title'] ?? '';
            $n['last_chapter_is_vip'] = (int)($n['last_chapter_is_vip'] ?? 0);
            $n['total_chapters'] = (int)$n['total_chapters'];
            $n['category_name'] = $n['category_name'] ?? '';
            $n['vip_sales_amount'] = (int)($n['vip_sales_amount'] ?? 0);
            $n['vip_sales_count'] = (int)($n['vip_sales_count'] ?? 0);
            if (empty($n['last_chapter_time'])) $n['last_chapter_time'] = $n['created_at'];
        }
        return $novels;
    }

    public function getLibraryData(array $filters, int $page, int $perPage = 20): array
    {
        if ($page <= 10) {
            $key = 'library_' . md5(json_encode($filters) . '_' . $page . '_' . $perPage);
            $result = Cache::remember($key, 300, function () use ($filters, $page, $perPage) {
                return NovelModel::getLibrary($filters, $page, $perPage);
            });
        } else {
            $result = NovelModel::getLibrary($filters, $page, $perPage);
        }
        if (!empty($result['list'])) $result['list'] = self::attachDisplayNames($result['list']);
        return $result;
    }

    public function getNovelDetail(int $id): ?array
    {
        $novel = NovelModel::getDetail($id);
        if (!$novel) return null;
        NovelModel::addViews($id);
        $novel = self::attachDisplayNames([$novel])[0];

        $author = array();
        if (!empty($novel['user_id'])) {
            $row = UserModel::findById((int)$novel['user_id'], 'id, pen_name, signature, avatar');
            if ($row) $author = $row;
        }

        $related = $this->getRelatedNovels();

        return [
            'novel'    => $novel,
            'author'   => $author,
            'volumes'  => VolumeModel::getByNovel($id),
            'chapters' => ChapterModel::getListByNovel($id),
            'related'  => $related,
            'tags'     => TagModel::getTagsByNovel($id),
        ];
    }

    public function getReadData(int $novelId, int $chapterId, int $userId): array
    {
        $novel = NovelModel::findById($novelId);
        $chapter = ChapterModel::getChapter($chapterId);
        if (!$novel || !$chapter) return ['error' => Language::get('msg_chapter_not_exist', '章节不存在')];

        $chapterService = new ChapterService();
        $perm = $chapterService->checkPermission($userId, $chapterId);

        $autoOn = $userId > 0 && AutoPurchaseModel::isOn($userId, (int)$novel['id']);
        $perm['auto_on'] = $autoOn;
        if (!$perm['allowed'] && !empty($perm['need_pay']) && $autoOn) {
            $auto = $chapterService->purchase($userId, $chapterId);
            if ($auto['ok']) {
                $perm['allowed'] = true;
                $perm['need_pay'] = false;
                $perm['auto_purchased'] = true;
            } else {
                $perm['auto_purchase_failed'] = $auto['msg'] ?? Language::get('auto_purchase_failed', '自动购买失败');
            }
        }

        if ($perm['allowed']) {
            $content = ChapterModel::getContent($chapter);
        } else {

            $previewWords = (int)(SettingModel::get('vip_preview_words', 1000));
            if (!empty($perm['guest_limited'])) $previewWords = 300;
            $full = ChapterModel::getRawContent($chapter);
            if ($previewWords > 0) {

                $content = excerpt(CommonService::htmlToPlainText((string)$full), $previewWords, '……');
                $perm['preview'] = true;
                $perm['preview_words'] = $previewWords;
            } else {
                $content = '';
            }
        }
        $content = CommonService::plainTextToHtml((string)$content);
        $prevNext = ChapterModel::getPrevNext($novelId, $chapterId);

        if ($userId > 0 && $perm['allowed']) {
            ReadingHistoryModel::record($userId, $novelId, $chapterId);
            BookshelfModel::updateLastRead($userId, $novelId, $chapterId);
        }
        return [
            'novel'   => $novel,
            'chapter' => $chapter,
            'content' => $content,
            'perm'    => $perm,
            'prev'    => $prevNext['prev'],
            'next'    => $prevNext['next'],
        ];
    }

    public function deleteNovel(int $id): bool
    {
        $novel = NovelModel::findById($id);
        foreach (ChapterModel::getListByNovel($id) as $ch) {
            ChapterModel::deleteChapterFile($ch);
        }
        ChapterModel::delete(['novel_id' => $id]);
        VolumeModel::delete(['novel_id' => $id]);
        NovelModel::delete(['id' => $id]);
        if ($novel) {
            $this->deleteCoverFile((string)($novel['cover'] ?? ''));
        }
        return true;
    }

    private function deleteCoverFile(string $cover): void
    {
        $cover = str_replace('\\', '/', ltrim($cover, '/'));
        if ($cover === '') return;
        $candidate = realpath(ROOT_PATH . $cover);
        if ($candidate === false || !is_file($candidate)) return;
        $roots = array_filter(array(
            realpath(ROOT_PATH . 'uploads/covers'),
            realpath(ROOT_PATH . 'data/uploads/covers'),
        ));
        foreach ($roots as $root) {
            if (strpos($candidate, $root . DIRECTORY_SEPARATOR) === 0) {
                @unlink($candidate);
                break;
            }
        }
    }

    public function deleteVolume(int $volumeId, string $mode, int $targetVolumeId = 0): array
    {
        $volume = VolumeModel::findById($volumeId);
        if (!$volume) {
            return ['ok' => false, 'msg' => Language::get('volume_not_exists', '分卷不存在或已被删除')];
        }
        $novelId = (int)$volume['novel_id'];

        $allVolumes = VolumeModel::getByNovel($novelId);
        if (count($allVolumes) <= 1) {
            return ['ok' => false, 'msg' => Language::get('last_volume_kept', '本书至少需要保留一个分卷，不能删除最后一卷')];
        }

        $chapters = ChapterModel::all(['volume_id' => $volumeId], 'sort ASC, id ASC');
        $count = count($chapters);

        if ($mode === 'move') {
            if ($targetVolumeId <= 0) {
                return ['ok' => false, 'msg' => Language::get('target_volume_required', '请选择要迁移到的目标分卷')];
            }
            $target = VolumeModel::findById($targetVolumeId);
            if (!$target || (int)$target['novel_id'] !== $novelId) {
                return ['ok' => false, 'msg' => Language::get('target_volume_not_exists', '目标分卷不存在')];
            }
            if ($targetVolumeId === $volumeId) {
                return ['ok' => false, 'msg' => Language::get('target_volume_is_self', '目标分卷不能是当前分卷')];
            }
            if ($count > 0) {
                ChapterModel::update(['volume_id' => $targetVolumeId], ['volume_id' => $volumeId]);
                ChapterModel::renumberVolume($targetVolumeId);
            }
            VolumeModel::delete(['id' => $volumeId]);
            NovelModel::refreshStats($novelId);
            $msg = $count > 0
                ? Language::get('volume_deleted_moved_fmt', '分卷已删除，%d 章已迁移到「%s」', $count, $target['title'])
                : Language::get('msg_volume_deleted', '分卷已删除');
            return ['ok' => true, 'msg' => $msg];
        }

        if ($count > 0) {
            foreach ($chapters as $ch) {
                ChapterModel::deleteChapterFile($ch);
            }
            ChapterModel::delete(['volume_id' => $volumeId]);
        }
        VolumeModel::delete(['id' => $volumeId]);
        NovelModel::refreshStats($novelId);
        $msg = $count > 0 ? Language::get('volume_deleted_with_chapters_fmt', '分卷已删除，卷内 %d 章已一并删除', $count) : Language::get('msg_volume_deleted', '分卷已删除');
        return ['ok' => true, 'msg' => $msg];
    }
}
