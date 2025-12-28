<?php

namespace console\controllers;

use common\models\Movie;
use Yii;
use yii\console\Controller;
use yii\db\Expression;
use yii\helpers\FileHelper;

class MybaseController extends Controller
{
    public $file = '';
    public $apiKey = '';
    public $delayMs = 250;
    public $userId = 1;

    private $lastApiCallAt = 0.0;
    private $ambiguousMatches = [];
    private $missingMatches = [];
    private $apiErrors = [];

    public function options($actionID): array
    {
        return ['file', 'apiKey', 'delayMs', 'userId'];
    }

    public function actionAdd(): int
    {
        if ($this->file === '') {
            $this->stderr("--file is required.\n");
            return self::EXIT_CODE_ERROR;
        }

        if ($this->apiKey === '') {
            $this->apiKey = (string)(Yii::$app->params['kinopoiskApiKey'] ?? '');
        }

        if ($this->apiKey === '') {
            $this->stderr("--apiKey is required (or set params['kinopoiskApiKey']).\n");
            return self::EXIT_CODE_ERROR;
        }

        $path = Yii::getAlias($this->file);
        if (!is_file($path)) {
            $this->stderr("File not found: {$path}\n");
            return self::EXIT_CODE_ERROR;
        }

        $rows = $this->readTsv($path);
        $total = count($rows);
        if ($total === 0) {
            $this->stderr("No rows found in {$path}.\n");
            return self::EXIT_CODE_ERROR;
        }

        $baseUrl = 'https://kinopoiskapiunofficial.tech';

        foreach ($rows as $index => $row) {
            $position = $index + 1;
            $title = trim((string)($row['Название'] ?? ''));
            $year = trim((string)($row['Год'] ?? ''));
            $link = trim((string)($row['Ссылка'] ?? ''));
            $ratingRaw = trim((string)($row['Оценка'] ?? ''));
            $rating = $this->normalizeRating($ratingRaw);

            if ($title === '') {
                $this->stderr("[{$position}/{$total}] Skipping row without title.\n");
                continue;
            }

            $this->stdout("[{$position}/{$total}] {$title} ({$year})...\n");

            $kinopoiskId = $this->extractKinopoiskId($link);
            if ($kinopoiskId === null) {
                $kinopoiskId = $this->searchKinopoiskId($baseUrl, $title, $year);
                if ($kinopoiskId === null) {
                    $this->missingMatches[] = $this->formatRowLabel($title, $year);
                    $this->stderr("  Not found by title/year, skipped.\n");
                    continue;
                }
            }

            $film = $this->fetchFilm($baseUrl, $kinopoiskId, $title);
            if ($film === null) {
                $this->stderr("  Failed to fetch film data, skipped.\n");
                continue;
            }

            if ($this->hasDuplicate($title, $year)) {
                $this->stderr("  Duplicate detected, skipped.\n");
                continue;
            }

            $movie = new Movie();
            $movie->id = Yii::$app->security->generateRandomString(32);
            $movie->user_id = $this->userId;
            $movie->list = Movie::LIST_MY;
            $movie->title = $this->pickTitle($film, $title);
            $movie->year = $this->normalizeYear($year, $film['year'] ?? null);
            $movie->runtime_min = $this->normalizeInt($film['filmLength'] ?? null);
            $movie->genres_csv = $this->formatGenres($film['genres'] ?? []);
            $movie->description = $film['description'] ?? $film['shortDescription'] ?? null;
            $movie->watched = true;
            $movie->rating = $rating;
            $movie->watched_at = '2017-01-01';
            $movie->url = $this->resolveKinopoiskUrl($link, $film, $kinopoiskId);

            $posterUrl = $film['posterUrl'] ?? null;
            if ($posterUrl) {
                $posterPath = $this->downloadPoster($movie->id, $posterUrl);
                if ($posterPath !== null) {
                    $movie->poster_path = $posterPath;
                }
            }

            if (!$movie->save()) {
                $this->apiErrors[] = $this->formatRowLabel($title, $year);
                $this->stderr("  Save failed: " . json_encode($movie->getFirstErrors(), JSON_UNESCAPED_UNICODE) . "\n");
                continue;
            }

            $this->stdout("  Saved.\n");
        }

        $this->printSummary();

        return self::EXIT_CODE_NORMAL;
    }

    public function actionBackfillUrl(): int
    {
        if ($this->file === '') {
            $this->stderr("--file is required.\n");
            return self::EXIT_CODE_ERROR;
        }

        if ($this->apiKey === '') {
            $this->apiKey = (string)(Yii::$app->params['kinopoiskApiKey'] ?? '');
        }

        if ($this->apiKey === '') {
            $this->stderr("--apiKey is required (or set params['kinopoiskApiKey']).\n");
            return self::EXIT_CODE_ERROR;
        }

        $path = Yii::getAlias($this->file);
        if (!is_file($path)) {
            $this->stderr("File not found: {$path}\n");
            return self::EXIT_CODE_ERROR;
        }

        $rows = $this->readTsv($path);
        $total = count($rows);
        if ($total === 0) {
            $this->stderr("No rows found in {$path}.\n");
            return self::EXIT_CODE_ERROR;
        }

        $baseUrl = 'https://kinopoiskapiunofficial.tech';

        foreach ($rows as $index => $row) {
            $position = $index + 1;
            $title = trim((string)($row['Название'] ?? ''));
            $year = trim((string)($row['Год'] ?? ''));
            $link = trim((string)($row['Ссылка'] ?? ''));

            if ($title === '') {
                $this->stderr("[{$position}/{$total}] Skipping row without title.\n");
                continue;
            }

            $movie = $this->findMovieByTitleYear($title, $year);
            if ($movie === null) {
                $this->missingMatches[] = $this->formatRowLabel($title, $year);
                $this->stderr("[{$position}/{$total}] Movie not found in DB.\n");
                continue;
            }

            if (!empty($movie->url)) {
                $this->stdout("[{$position}/{$total}] {$title} ({$year})... already set.\n");
                continue;
            }

            $this->stdout("[{$position}/{$total}] {$title} ({$year})... updating.\n");

            $kinopoiskId = $this->extractKinopoiskId($link);
            if ($kinopoiskId === null) {
                $kinopoiskId = $this->searchKinopoiskId($baseUrl, $title, $year);
            }

            $film = null;
            if ($kinopoiskId !== null) {
                $film = $this->fetchFilm($baseUrl, $kinopoiskId, $title);
            }

            $movie->url = $this->resolveKinopoiskUrl($link, $film, $kinopoiskId);
            if (empty($movie->url)) {
                $this->missingMatches[] = $this->formatRowLabel($title, $year);
                $this->stderr("  URL not resolved.\n");
                continue;
            }

            if (!$movie->save()) {
                $this->apiErrors[] = $this->formatRowLabel($title, $year);
                $this->stderr("  Save failed: " . json_encode($movie->getFirstErrors(), JSON_UNESCAPED_UNICODE) . "\n");
                continue;
            }

            $this->stdout("  URL saved.\n");
        }

        $this->printSummary();

        return self::EXIT_CODE_NORMAL;
    }

    private function readTsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $rows = [];
        $header = fgetcsv($handle, 0, "\t");
        if ($header === false) {
            fclose($handle);
            return [];
        }

        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }
            $row = array_pad($row, count($header), '');
            $rows[] = array_combine($header, $row);
        }

        fclose($handle);
        return $rows;
    }

    private function normalizeRating(string $value): int
    {
        $rating = (int)$value;
        if ($rating < 1) {
            return 1;
        }
        if ($rating > 10) {
            return 10;
        }
        return $rating;
    }

    private function extractKinopoiskId(string $link): ?int
    {
        if ($link === '') {
            return null;
        }
        if (preg_match('~kinopoisk\.ru/film/(\d+)/~', $link, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    private function searchKinopoiskId(string $baseUrl, string $title, string $year): ?int
    {
        $response = $this->apiGet($baseUrl, '/api/v2.1/films/search-by-keyword', [
            'keyword' => $title,
            'page' => 1,
        ]);

        if (!$response || !isset($response['films'])) {
            return null;
        }

        $candidates = $response['films'];
        if ($year !== '') {
            $candidates = array_filter($candidates, function ($film) use ($year) {
                $filmYear = $film['year'] ?? '';
                return (string)$filmYear === (string)$year;
            });
        }

        $candidates = array_values($candidates);
        if (count($candidates) === 1) {
            return (int)$candidates[0]['filmId'];
        }

        if (count($candidates) > 1) {
            $this->ambiguousMatches[] = $this->formatRowLabel($title, $year);
        }

        return null;
    }

    private function fetchFilm(string $baseUrl, int $kinopoiskId, string $title): ?array
    {
        $response = $this->apiGet($baseUrl, "/api/v2.2/films/{$kinopoiskId}");
        if (!$response) {
            $this->apiErrors[] = $this->formatRowLabel($title, (string)$kinopoiskId);
            return null;
        }

        return $response;
    }

    private function apiGet(string $baseUrl, string $url, array $params = []): ?array
    {
        $this->throttle();

        $requestUrl = rtrim($baseUrl, '/') . $url;
        if (!empty($params)) {
            $requestUrl .= '?' . http_build_query($params);
        }

        $ch = curl_init($requestUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-KEY: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $status < 200 || $status >= 300) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    private function throttle(): void
    {
        $delay = max((int)$this->delayMs, 0) / 1000;
        $now = microtime(true);
        $elapsed = $now - $this->lastApiCallAt;
        if ($this->lastApiCallAt > 0 && $elapsed < $delay) {
            usleep((int)(($delay - $elapsed) * 1000000));
        }
        $this->lastApiCallAt = microtime(true);
    }

    private function pickTitle(array $film, string $fallback): string
    {
        foreach (['nameRu', 'nameOriginal', 'nameEn'] as $field) {
            if (!empty($film[$field])) {
                return trim((string)$film[$field]);
            }
        }
        return $fallback;
    }

    private function normalizeYear(string $csvYear, $apiYear): ?int
    {
        if ($csvYear !== '') {
            return (int)$csvYear;
        }
        if ($apiYear !== null && $apiYear !== '') {
            return (int)$apiYear;
        }
        return null;
    }

    private function normalizeInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int)$value;
    }

    private function formatGenres(array $genres): string
    {
        if (empty($genres)) {
            return '';
        }
        $names = array_map(function ($genre) {
            return $genre['genre'] ?? '';
        }, $genres);
        $names = array_filter($names, static fn($name) => $name !== '');
        return implode(', ', $names);
    }

    private function downloadPoster(string $movieId, string $posterUrl): ?string
    {
        $posterDir = Yii::getAlias('@backend/web/uploads/posters');
        FileHelper::createDirectory($posterDir, 0777, true);

        $extension = pathinfo(parse_url($posterUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        $extension = $extension ?: 'jpg';
        $filename = $movieId . '.' . $extension;
        $fullPath = $posterDir . DIRECTORY_SEPARATOR . $filename;

        $data = @file_get_contents($posterUrl);
        if ($data === false) {
            $this->stderr("  Failed to download poster from {$posterUrl}\n");
            return null;
        }

        $saved = file_put_contents($fullPath, $data);
        if ($saved === false) {
            $this->stderr("  Failed to save poster to {$fullPath}\n");
            return null;
        }

        return 'uploads/posters/' . $filename;
    }

    private function hasDuplicate(string $title, string $year): bool
    {
        $query = Movie::find()->where(['user_id' => $this->userId]);
        $query->andWhere(['=', new Expression('LOWER(title)'), mb_strtolower($title)]);
        if ($year !== '') {
            $query->andWhere(['year' => (int)$year]);
        }

        return $query->exists();
    }

    private function findMovieByTitleYear(string $title, string $year): ?Movie
    {
        $query = Movie::find()->where(['user_id' => $this->userId]);
        $query->andWhere(['=', new Expression('LOWER(title)'), mb_strtolower($title)]);
        if ($year !== '') {
            $query->andWhere(['year' => (int)$year]);
        }

        return $query->one();
    }

    private function resolveKinopoiskUrl(string $link, ?array $film, ?int $kinopoiskId): ?string
    {
        $linkId = $this->extractKinopoiskId($link);
        if ($linkId !== null) {
            return $this->buildKinopoiskUrl($linkId);
        }

        if (!empty($film['webUrl'])) {
            return (string)$film['webUrl'];
        }

        if ($kinopoiskId !== null) {
            return $this->buildKinopoiskUrl($kinopoiskId);
        }

        return null;
    }

    private function buildKinopoiskUrl(int $kinopoiskId): string
    {
        return "https://www.kinopoisk.ru/film/{$kinopoiskId}/";
    }

    private function formatRowLabel(string $title, string $year): string
    {
        $suffix = $year !== '' ? " ({$year})" : '';
        return $title . $suffix;
    }

    private function printSummary(): void
    {
        if (!empty($this->ambiguousMatches)) {
            $this->stdout("\nAmbiguous matches (need manual review):\n");
            foreach ($this->ambiguousMatches as $item) {
                $this->stdout("  - {$item}\n");
            }
        }

        if (!empty($this->missingMatches)) {
            $this->stdout("\nMissing matches (not found):\n");
            foreach ($this->missingMatches as $item) {
                $this->stdout("  - {$item}\n");
            }
        }

        if (!empty($this->apiErrors)) {
            $this->stdout("\nErrors during import:\n");
            foreach ($this->apiErrors as $item) {
                $this->stdout("  - {$item}\n");
            }
        }
    }
}
