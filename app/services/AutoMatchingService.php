<?php

namespace App\Services;

use App\Models\LostItem;
use App\Models\FoundItem;
use App\Models\MatchAlert;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoMatchingService
{
    const CATEGORY_WEIGHT = 30;
    const LOCATION_WEIGHT = 25;
    const TIME_WEIGHT = 20;
    const DESCRIPTION_WEIGHT = 25;

    const MIN_MATCH_SCORE = 60;
    const HIGH_MATCH_SCORE = 80;

    /**
     * Menjalankan algoritma auto-matching untuk semua item
     */
    public function runAutoMatching()
    {
        try {
            $lostItems = LostItem::where('status', 'lost')->get();

            $foundItems = FoundItem::where('status', 'pending')->get();

            $totalMatches = 0;

            foreach ($lostItems as $lostItem) {
                $matches = $this->findMatchesForLostItem($lostItem, $foundItems);
                $totalMatches += count($matches);
            }

            Log::info("Auto-matching completed. Total matches found: {$totalMatches}");

            return [
                'success' => true,
                'total_matches' => $totalMatches,
                'message' => "Auto-matching selesai. Ditemukan {$totalMatches} kecocokan potensial."
            ];
        } catch (\Exception $e) {
            Log::error('Auto-matching failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat menjalankan auto-matching.'
            ];
        }
    }

    /**
     * Mencari kecocokan untuk satu lost item
     */
    public function findMatchesForLostItem(LostItem $lostItem, $foundItems = null)
    {
        if (!$foundItems) {
            $foundItems = FoundItem::where('status', 'pending')->get();
        }

        $matches = [];

        foreach ($foundItems as $foundItem) {
            $existingMatch = MatchAlert::where('lost_item_id', $lostItem->id)
                ->where('found_item_id', $foundItem->id)
                ->first();

            if ($existingMatch) {
                continue;
            }

            $matchScore = $this->calculateMatchScore($lostItem, $foundItem);

            if ($matchScore >= self::MIN_MATCH_SCORE) {
                $match = $this->createMatchAlert($lostItem, $foundItem, $matchScore);
                $matches[] = $match;

                if ($matchScore >= self::HIGH_MATCH_SCORE) {
                    $this->sendHighMatchNotifications($lostItem, $foundItem, $matchScore);
                }
            }
        }

        return $matches;
    }

    /**
     * Menghitung skor kecocokan antara lost item dan found item
     */
    private function calculateMatchScore(LostItem $lostItem, FoundItem $foundItem)
    {
        $categoryScore = $this->calculateCategoryScore($lostItem, $foundItem);
        $locationScore = $this->calculateLocationScore($lostItem, $foundItem);
        $timeScore = $this->calculateTimeScore($lostItem, $foundItem);
        $descriptionScore = $this->calculateDescriptionScore($lostItem, $foundItem);

        $totalScore = (
            ($categoryScore * self::CATEGORY_WEIGHT) +
            ($locationScore * self::LOCATION_WEIGHT) +
            ($timeScore * self::TIME_WEIGHT) +
            ($descriptionScore * self::DESCRIPTION_WEIGHT)
        ) / 100;

        return round($totalScore, 2);
    }

    /**
     * Menghitung skor kecocokan kategori
     */
    private function calculateCategoryScore(LostItem $lostItem, FoundItem $foundItem)
    {
        if ($lostItem->category_id === $foundItem->category_id) {
            return 100;
        }

        return 0;
    }

    /**
     * Menghitung skor kecocokan lokasi
     */
    private function calculateLocationScore(LostItem $lostItem, FoundItem $foundItem)
    {
        if ($lostItem->location_id === $foundItem->location_id) {
            return 100;
        }

        // Hitung jarak berdasarkan koordinat
        $lostLocation = $lostItem->location;
        $foundLocation = $foundItem->location;

        if (
            $lostLocation && $foundLocation &&
            $lostLocation->latitude && $lostLocation->longitude &&
            $foundLocation->latitude && $foundLocation->longitude
        ) {

            $distance = $this->calculateDistance(
                $lostLocation->latitude,
                $lostLocation->longitude,
                $foundLocation->latitude,
                $foundLocation->longitude
            );

            // Skor berdasarkan jarak (dalam km)
            if ($distance <= 0.5) return 90;      // 500m
            if ($distance <= 1.0) return 80;      // 1km
            if ($distance <= 2.0) return 70;      // 2km
            if ($distance <= 5.0) return 50;      // 5km
            if ($distance <= 10.0) return 30;     // 10km

            return 10; // Lebih dari 10km
        }

        return 30; // Default jika tidak ada koordinat
    }

    /**
     * Menghitung skor kecocokan waktu
     */
    private function calculateTimeScore(LostItem $lostItem, FoundItem $foundItem)
    {
        $lostDateTime = Carbon::parse($lostItem->lost_date . ' ' . $lostItem->lost_time);
        $foundDateTime = Carbon::parse($foundItem->found_date . ' ' . $foundItem->found_time);

        // Found item harus setelah lost item
        if ($foundDateTime->lt($lostDateTime)) {
            return 0;
        }

        $hoursDiff = $lostDateTime->diffInHours($foundDateTime);

        // Skor berdasarkan selisih waktu
        if ($hoursDiff <= 2) return 100;      // 2 jam
        if ($hoursDiff <= 6) return 90;       // 6 jam
        if ($hoursDiff <= 12) return 80;      // 12 jam
        if ($hoursDiff <= 24) return 70;      // 1 hari
        if ($hoursDiff <= 72) return 60;      // 3 hari
        if ($hoursDiff <= 168) return 40;     // 1 minggu
        if ($hoursDiff <= 720) return 20;     // 1 bulan

        return 5; // Lebih dari 1 bulan
    }

    /**
     * Menghitung skor kecocokan deskripsi menggunakan text similarity
     */
    private function calculateDescriptionScore(LostItem $lostItem, FoundItem $foundItem)
    {
        $lostDesc = strtolower($lostItem->description);
        $foundDesc = strtolower($foundItem->description);

        // Jika salah satu deskripsi kosong
        if (empty($lostDesc) || empty($foundDesc)) {
            return 50; // Netral
        }

        // Hitung similarity menggunakan similar_text
        $similarity = 0;
        similar_text($lostDesc, $foundDesc, $similarity);

        // Tambahan poin untuk kata kunci yang sama
        $lostWords = explode(' ', $lostDesc);
        $foundWords = explode(' ', $foundDesc);
        $commonWords = array_intersect($lostWords, $foundWords);
        $keywordBonus = (count($commonWords) / max(count($lostWords), count($foundWords))) * 20;

        return min(100, $similarity + $keywordBonus);
    }

    /**
     * Menghitung jarak antara dua koordinat (Haversine formula)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Membuat match alert baru
     */
    private function createMatchAlert(LostItem $lostItem, FoundItem $foundItem, $matchScore)
    {
        $matchAlert = MatchAlert::create([
            'lost_item_id' => $lostItem->id,
            'found_item_id' => $foundItem->id,
            'match_score' => $matchScore,
            'status' => 'pending'
        ]);

        // Kirim notifikasi ke pemilik barang hilang
        $this->sendMatchNotification($lostItem->user_id, $matchAlert, 'lost');

        // Kirim notifikasi ke penemu barang
        $this->sendMatchNotification($foundItem->user_id, $matchAlert, 'found');

        return $matchAlert;
    }

    /**
     * Mengirim notifikasi untuk match baru
     */
    private function sendMatchNotification($userId, MatchAlert $matchAlert, $type)
    {
        $lostItem = $matchAlert->lostItem;
        $foundItem = $matchAlert->foundItem;

        if ($type === 'lost') {
            $title = 'Kemungkinan Barang Anda Ditemukan!';
            $content = "Barang '{$lostItem->title}' mungkin telah ditemukan. Skor kecocokan: {$matchAlert->match_score}%";
        } else {
            $title = 'Barang yang Anda Temukan Cocok!';
            $content = "Barang '{$foundItem->title}' yang Anda temukan cocok dengan barang hilang. Skor kecocokan: {$matchAlert->match_score}%";
        }

        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
            'read' => false,
            'type' => 'match',
            'related_id' => $matchAlert->id
        ]);
    }

    /**
     * Mengirim notifikasi khusus untuk match dengan skor tinggi
     */
    private function sendHighMatchNotifications(LostItem $lostItem, FoundItem $foundItem, $matchScore)
    {
        // Notifikasi urgent untuk pemilik barang hilang
        Notification::create([
            'user_id' => $lostItem->user_id,
            'title' => '🔥 KECOCOKAN TINGGI! Barang Anda Mungkin Ditemukan',
            'content' => "Barang '{$lostItem->title}' memiliki kecocokan sangat tinggi ({$matchScore}%) dengan barang yang ditemukan. Segera periksa!",
            'read' => false,
            'type' => 'match',
            'related_id' => null
        ]);

        // Notifikasi untuk penemu
        Notification::create([
            'user_id' => $foundItem->user_id,
            'title' => '🔥 KECOCOKAN TINGGI! Pemilik Barang Mungkin Ditemukan',
            'content' => "Barang '{$foundItem->title}' yang Anda temukan memiliki kecocokan sangat tinggi ({$matchScore}%) dengan barang hilang. Segera hubungi pemiliknya!",
            'read' => false,
            'type' => 'match',
            'related_id' => null
        ]);
    }

    /**
     * Menjalankan auto-matching untuk item baru
     */
    public function processNewItem($item, $type)
    {
        if ($type === 'lost') {
            $this->findMatchesForLostItem($item);
        } elseif ($type === 'found') {
            // Cari lost items yang cocok dengan found item baru
            $lostItems = LostItem::where('status', 'lost')->get();
            foreach ($lostItems as $lostItem) {
                $existingMatch = MatchAlert::where('lost_item_id', $lostItem->id)
                    ->where('found_item_id', $item->id)
                    ->first();

                if (!$existingMatch) {
                    $matchScore = $this->calculateMatchScore($lostItem, $item);

                    if ($matchScore >= self::MIN_MATCH_SCORE) {
                        $match = $this->createMatchAlert($lostItem, $item, $matchScore);

                        if ($matchScore >= self::HIGH_MATCH_SCORE) {
                            $this->sendHighMatchNotifications($lostItem, $item, $matchScore);
                        }
                    }
                }
            }
        }
    }

    /**
     * Mendapatkan statistik matching
     */
    public function getMatchingStats()
    {
        return [
            'total_matches' => MatchAlert::count(),
            'pending_matches' => MatchAlert::where('status', 'pending')->count(),
            'confirmed_matches' => MatchAlert::where('status', 'confirmed')->count(),
            'rejected_matches' => MatchAlert::where('status', 'rejected')->count(),
            'high_score_matches' => MatchAlert::where('match_score', '>=', self::HIGH_MATCH_SCORE)->count(),
            'today_matches' => MatchAlert::whereDate('created_at', today())->count()
        ];
    }
}
