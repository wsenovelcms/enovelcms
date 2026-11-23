<?php

class ChapterService
{

    public function getChapterPrice(int $chapterId): int
    {
        $chapter = ChapterModel::findById($chapterId, 'word_count, price, is_vip');
        if (!$chapter || (int)$chapter['is_vip'] !== 1) return 0;
        if ((int)$chapter['price'] > 0) return (int)$chapter['price'];

        $per300 = (int)SettingModel::get('vip_default_price_per_300', 1);
        if ($per300 <= 0) $per300 = 1;
        return max(1, (int)ceil((int)$chapter['word_count'] / 300) * $per300);
    }

    public function logVipReading(int $userId, int $chapterId): void
    {
        $chapter = ChapterModel::findById($chapterId, 'novel_id, word_count');
        if ($chapter) {
            VipReadingLogModel::log($userId, (int)$chapter['novel_id'], $chapterId, (int)$chapter['word_count']);
        }
    }

    public function checkPermission(int $userId, int $chapterId): array
    {
        $chapter = ChapterModel::findById($chapterId, 'is_vip, novel_id');
        if (!$chapter) {
            return ['allowed' => false, 'need_pay' => false, 'price' => 0, 'msg' => Language::get('msg_chapter_not_exist', '章节不存在')];
        }
        if ((int)$chapter['is_vip'] !== 1) {
            if ($userId <= 0 && (string)SettingModel::get('allow_guest_read', '1') === '0') {
                return ['allowed' => false, 'need_pay' => false, 'price' => 0, 'guest_limited' => true, 'msg' => Language::get('guest_read_limited', '登录后可阅读全文')];
            }
            return ['allowed' => true, 'need_pay' => false, 'price' => 0, 'msg' => Language::get('normal_chapter', '普通章节')];
        }
        if ($userId > 0 && ChapterPurchaseModel::exists($userId, $chapterId)) {
            return ['allowed' => true, 'need_pay' => false, 'price' => 0, 'msg' => Language::get('purchased_msg', '已购买')];
        }
        if ($userId > 0 && $this->isOwnNovel($userId, (int)$chapter['novel_id'])) {
            return ['allowed' => true, 'need_pay' => false, 'price' => 0, 'msg' => Language::get('author_free', '作者本人免费')];
        }
        if ($userId > 0) {
            $user = UserModel::findById($userId, 'vip_level, vip_expire');
            if ($user && UserService::isVipActive($user)) {
                $this->logVipReading($userId, $chapterId);
                return ['allowed' => true, 'need_pay' => false, 'price' => 0, 'msg' => Language::get('vip_free', 'VIP会员免费')];
            }
        }
        $price = $this->getChapterPrice($chapterId);
        return ['allowed' => false, 'need_pay' => true, 'price' => $price, 'msg' => Language::get('chapter_paid', '该章节为付费章节')];
    }

    private function isOwnNovel(int $userId, int $novelId): bool
    {
        if ($userId <= 0 || $novelId <= 0) return false;
        $novel = NovelModel::findById($novelId, 'user_id');
        return $novel && (int)$novel['user_id'] === $userId;
    }

    public function purchase(int $userId, int $chapterId, ?bool $autoBuy = null): array
    {
        if (ChapterPurchaseModel::exists($userId, $chapterId)) {
            return ['ok' => false, 'msg' => Language::get('already_purchased', '你已购买该章节')];
        }
        $chapter = ChapterModel::findById($chapterId, 'novel_id, word_count, is_vip, price');
        if (!$chapter || (int)$chapter['is_vip'] !== 1) {
            return ['ok' => false, 'msg' => Language::get('chapter_not_purchasable', '该章节无需购买或不存在')];
        }
        if ($this->isOwnNovel($userId, (int)$chapter['novel_id'])) {
            return ['ok' => false, 'msg' => Language::get('author_no_purchase_needed', '本书作者无需购买')];
        }
        $price = $this->getChapterPrice($chapterId);
        if ($price <= 0) return ['ok' => false, 'msg' => Language::get('price_error', '价格异常')];

        $user = UserModel::findById($userId, 'gold');
        if (!$user || (int)$user['gold'] < $price) {
            return ['ok' => false, 'msg' => Language::get('gold_insufficient', '金币不足'), 'price' => $price];
        }

        $novel = NovelModel::findById((int)$chapter['novel_id'], 'title');
        $chapterTitle = ChapterModel::findById($chapterId, 'title')['title'];
        $remark = "购买《{$novel['title']}》- {$chapterTitle}";

        $db = UserModel::db();
        $db->beginTransaction();
        try {
            if (!CommonService::addGold($userId, -$price, 'purchase_chapter', $remark,
                                        (int)$chapter['novel_id'], $chapterId)) {
                $db->rollBack();
                return ['ok' => false, 'msg' => Language::get('gold_insufficient', '金币不足'), 'price' => $price];
            }
            ChapterPurchaseModel::record($userId, (int)$chapter['novel_id'], $chapterId, $price);

            $author = NovelModel::findById((int)$chapter['novel_id'], 'user_id');
            if ($author && (int)$author['user_id'] > 0 && (int)$author['user_id'] !== $userId) {
                $authorId = (int)$author['user_id'];
                UserModel::increment('total_income', $price, ['id' => $authorId]);
                UserModel::increment('available_income', $price, ['id' => $authorId]);
                AuthorIncomeModel::log($authorId, (int)$chapter['novel_id'], $chapterId, $price, 'sale', 0);
            }
            $db->commit();
            try { DiyBlockModel::invalidateAll(); } catch (\Throwable $e) {}
            if ($autoBuy !== null) {
                AutoPurchaseModel::set($userId, (int)$chapter['novel_id'], $autoBuy);
            }
            return ['ok' => true, 'msg' => Language::get('purchase_success', '购买成功'), 'price' => $price];
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[purchase] ' . $e->getMessage());
            return ['ok' => false, 'msg' => Language::get('purchase_failed_retry', '购买失败，请稍后重试')];
        }
    }

    public function reorderChapter(array $chapter, array $op): bool
    {
        $chapterId = (int)($chapter['id'] ?? 0);
        $fresh = ChapterModel::findById($chapterId, 'id, novel_id, volume_id');
        if (!$fresh) return false;
        $chapter = $fresh;

        $novelId = (int)$chapter['novel_id'];
        $targetId = (int)($op['target_id'] ?? 0);
        $position = (string)($op['position'] ?? '');
        $direction = (string)($op['direction'] ?? '');

        $byVolume = [];
        foreach (ChapterModel::getListByNovel($novelId) as $row) {
            $byVolume[(int)$row['volume_id']][] = (int)$row['id'];
        }

        $db = ChapterModel::db();
        $db->beginTransaction();
        try {
            if ($targetId > 0 && in_array($position, ['before', 'after'], true)) {
                if ($targetId !== $chapterId) {
                    $target = ChapterModel::findById($targetId);
                    if (!$target || (int)$target['novel_id'] !== $novelId) {
                        throw new \RuntimeException('invalid target chapter');
                    }
                    $srcVol = (int)$chapter['volume_id'];
                    $dstVol = (int)$target['volume_id'];

                    if ($srcVol === $dstVol) {
                        $ids = array_values(array_diff($byVolume[$srcVol], [$chapterId]));
                        $anchorIdx = array_search($targetId, $ids, true);
                        $insertAt = $position === 'before' ? $anchorIdx : $anchorIdx + 1;
                        array_splice($ids, $insertAt, 0, [$chapterId]);
                        $this->writeVolumeOrder($srcVol, $ids);
                    } else {
                        $srcIds = array_values(array_diff($byVolume[$srcVol], [$chapterId]));
                        $dstIds = $byVolume[$dstVol] ?? [];
                        $anchorIdx = array_search($targetId, $dstIds, true);
                        $insertAt = $position === 'before' ? $anchorIdx : $anchorIdx + 1;
                        array_splice($dstIds, $insertAt, 0, [$chapterId]);
                        ChapterModel::update(['volume_id' => $dstVol], ['id' => $chapterId]);
                        $this->writeVolumeOrder($srcVol, $srcIds);
                        $this->writeVolumeOrder($dstVol, $dstIds);
                    }
                }
            } else {
                $vol = (int)$chapter['volume_id'];
                $ids = $byVolume[$vol] ?? [];
                $idx = array_search($chapterId, $ids, true);
                $swapIdx = $direction === 'up' ? $idx - 1 : $idx + 1;
                if ($idx !== false && $swapIdx >= 0 && $swapIdx < count($ids)) {
                    $tmp = $ids[$idx];
                    $ids[$idx] = $ids[$swapIdx];
                    $ids[$swapIdx] = $tmp;
                    $this->writeVolumeOrder($vol, $ids);
                }
            }
            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[chapter reorder] ' . $e->getMessage());
            return false;
        }
    }

    private function writeVolumeOrder(int $volumeId, array $ids): void
    {
        $i = 0;
        foreach ($ids as $id) {
            $i++;
            ChapterModel::update(['sort' => $i], ['id' => (int)$id]);
        }
    }
}
