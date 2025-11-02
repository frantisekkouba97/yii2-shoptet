<?php

namespace app\components;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Yii;

class ShoptetClient
{
    private Client $client;
    private string $token;
    private int $sleepMs;
    private ?array $imageCuts = null;

    public function __construct(string $token = '', int $sleepMs = 300)
    {
        $this->token = $token ?: (string)(Yii::$app->params['shoptetPrivateApiToken'] ?? '');
        $this->sleepMs = $sleepMs;
        $this->client = new Client([
            'base_uri' => 'https://api.myshoptet.com',
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=UTF-8',
                'Shoptet-Private-API-Token' => $this->token,
            ],
        ]);
    }

    private function sleep(): void
    {
        if ($this->sleepMs > 0) {
            usleep($this->sleepMs * 1000);
        }
    }

    private function request(string $method, string $uri, array $options = [])
    {
        $attempts = 0;
        start:

        try {
            $response = $this->client->request($method, $uri, $options);
            $this->sleep();

            return json_decode((string)$response->getBody(), true);

        } catch (RequestException $e) {
            $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;

            if ($status === 429 && $attempts < 3) {
                $attempts++;
                usleep(($this->sleepMs + 200 * $attempts) * 1000);
                goto start;
            }

            throw $e;
        }
    }

    public function listProducts(int $page = 1, int $perPage = 100, array $includes = []): array
    {
        $query = [
            'page' => $page,
            'itemsPerPage' => $perPage,
        ];

        if (!empty($includes)) {
            $query['include'] = implode(',', $includes);
        }

        return $this->request('GET', '/api/products', [
            'query' => $query,
        ]);
    }

    public function getProductDetail(string $guid, array $includes = []): array
    {
        $query = [];

        if (!empty($includes)) {
            $query['include'] = implode(',', $includes);
        }

        return $this->request('GET', '/api/products/' . rawurlencode($guid), [
            'query' => $query,
        ]);
    }

    public function updateProductDescription(string $guid, string $description): array
    {
        $payload = [
            'data' => [
                'description' => $description,
            ],
        ];

        return $this->request('PATCH', '/api/products/' . rawurlencode($guid), [
            'json' => $payload,
        ]);
    }

    /**
     * Fetch e-shop info, optionally including imageCuts, and cache them.
     */
    public function getEshopInfo(array $includes = ['imageCuts']): array
    {
        $query = [];

        if (!empty($includes)) {
            $query['include'] = implode(',', $includes);
        }

        $data = $this->request('GET', '/api/eshop', [
            'query' => $query,
        ]);

        if (isset($data['data']['imageCuts']) && is_array($data['data']['imageCuts'])) {
            $this->imageCuts = $data['data']['imageCuts'];
        }

        return $data;
    }

    private function getImageCuts(): array
    {
        if ($this->imageCuts === null) {
            $this->getEshopInfo(['imageCuts']);
            if ($this->imageCuts === null) {
                $this->imageCuts = [];
            }
        }

        return $this->imageCuts;
    }

    /**
     * Build full image URL from mainImage descriptor using e-shop imageCuts.
     * Prefers CDN path and the given cut (default "big"), falls back to "orig".
     */
    public function buildImageUrl(?array $image, string $preferredCut = 'big'): ?string
    {
        if (!$image) {
            return null;
        }

        $cuts = $this->getImageCuts();

        if (empty($cuts)) {
            return null;
        }

        $selected = null;

        foreach ($cuts as $cut) {
            if (($cut['name'] ?? '') === $preferredCut) {
                $selected = $cut;
                break;
            }
        }

        if ($selected === null) {
            foreach ($cuts as $cut) {
                if (($cut['name'] ?? '') === 'orig') {
                    $selected = $cut;
                    break;
                }
            }
        }

        if ($selected === null) {
            $selected = $cuts[0];
        }

        $base = $selected['cdnPath'] ?? ($selected['urlPath'] ?? null);
        $file = $image['cdnName'] ?? ($image['name'] ?? null);

        if ($base && $file) {
            return rtrim($base, '/') . '/' . ltrim($file, '/');
        }

        return null;
    }
}
