<?php

namespace app\commands;

use app\components\ShoptetClient;
use app\models\Category;
use app\models\Product;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class ShoptetController extends Controller
{
    /**
     * Synchronize products from Shoptet into local DB, including categories mapping.
     * Usage: php yii shoptet/sync-products
     *
     * @param int|null $maxPages Limit number of pages for testing
     */
    public function actionSyncProducts(int $perPage = 100, ?int $maxPages = null): int
    {
        $client = new ShoptetClient();
        $page = 1;
        $totalImported = 0;

        while (true) {
            try {
                $list = $client->listProducts($page, $perPage, ['images']);
            } catch (Throwable $e) {
                $this->stderr("Request failed on page {$page}: " . $e->getMessage() . "\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $products = $list['data']['products'] ?? ($list['products'] ?? []);
            if (empty($products)) {
                $this->stdout("No more products on page {$page}.\n", Console::FG_YELLOW);
                break;
            }

            foreach ($products as $product) {
                $guid = $product['guid'] ?? null;
                if (! $guid) {
                    continue;
                }

                $model = Product::findOne([
                    'guid' => (string) $guid,
                ]);
                if ($model === null) {
                    $model = new Product([
                        'guid' => (string) $guid,
                    ]);
                }

                $model->name = $product['name'] ?? $model->name ?? ('#' . substr((string) $guid, 0, 8));
                $model->url = $product['url'] ?? $model->url;

                // main image from list (include=images) if available
                if (! empty($product['mainImage']) && is_array($product['mainImage'])) {
                    $imgUrl = $client->buildImageUrl($product['mainImage']);

                    if ($imgUrl) {
                        $model->image_url = $imgUrl;
                    }
                }

                // Always fetch detail to fill description, categories and stock (with required includes)
                $categories = [];

                try {
                    $detail = $client->getProductDetail((string) $guid, ['images', 'allCategories']);
                    $data = $detail['data'] ?? [];

                    if (! empty($data)) {
                        // description
                        if (array_key_exists('description', $data)) {
                            $model->description = $data['description'];
                        }

                        // code from first variant
                        if (! empty($data['variants']) && is_array($data['variants'])) {
                            $first = $data['variants'][0];
                            if (! empty($first['code'])) {
                                $model->code = (string) $first['code'];
                            }
                            // stock: sum of variant stocks
                            $sum = 0.0;
                            foreach ($data['variants'] as $variant) {
                                if (isset($variant['stock']) && $variant['stock'] !== '') {
                                    $sum += (float) $variant['stock'];
                                }
                            }

                            $model->stock_qty = (int) round($sum);
                        }

                        // image from detail if not set from list
                        if (empty($model->image_url) && ! empty($data['mainImage'])) {
                            $imgUrl = $client->buildImageUrl($data['mainImage']);
                            if ($imgUrl) {
                                $model->image_url = $imgUrl;
                            }
                        }

                        // categories (requires include=allCategories per spec)
                        if (! empty($data['categories']) && is_array($data['categories'])) {
                            $categories = $data['categories'];
                        }
                    }
                } catch (Throwable $e) {
                    $this->stderr("Failed to load detail for {$guid}: " . $e->getMessage() . "\n", Console::FG_YELLOW);
                }

                // fallback categories: use defaultCategory from list
                if (empty($categories) && ! empty($product['defaultCategory'])) {
                    $dc = $product['defaultCategory'];
                    if (is_array($dc) && (! empty($dc['guid']) || ! empty($dc['name']))) {
                        $categories = [[
                            'guid' => $dc['guid'] ?? null,
                            'name' => $dc['name'] ?? 'Unknown',
                        ]];
                    }
                }

                if (! $model->save()) {
                    $this->stderr(
                        'Failed to save product ' . $model->guid . ': ' . json_encode($model->errors) . "\n",
                        Console::FG_RED
                    );
                    continue;
                }

                // map categories
                if (! empty($categories) && is_array($categories)) {
                    $catIds = [];

                    foreach ($categories as $category) {
                        $categoryId = (string) ($category['guid'] ?? '');
                        $categoryName = $category['name'] ?? null;

                        if (! $categoryId && ! $categoryName) {
                            continue;
                        }

                        $cat = null;

                        if ($categoryId) {
                            $cat = Category::findOne([
                                'shoptet_id' => $categoryId,
                            ]);
                        }

                        if ($cat === null) {
                            $cat = new Category([
                                'shoptet_id' => $categoryId ?: md5((string) $categoryName),
                                'name' => $categoryName ?: ($categoryId ?: 'Unknown'),
                            ]);
                        } else {
                            if ($categoryName && $cat->name !== $categoryName) {
                                $cat->name = $categoryName;
                            }
                        }

                        if (! $cat->save()) {
                            $this->stderr(
                                'Failed to save category: ' . json_encode($cat->errors) . "\n",
                                Console::FG_RED
                            );
                            continue;
                        }

                        $catIds[] = $cat->id;
                    }
                    // sync pivot
                    $this->syncProductCategories($model->id, $catIds);
                }

                $totalImported++;
            }

            $this->stdout("Page {$page}: imported/updated " . count($products) . " products.\n", Console::FG_GREEN);

            $page++;
            if ($maxPages !== null && $page > $maxPages) {
                break;
            }

            // if API provided pagination info, and we are on last page, break
            $totalPages = $list['data']['paginator']['pages'] ?? ($list['pagination']['totalPages'] ?? null);
            if ($totalPages !== null && $page > (int) $totalPages) {
                break;
            }
        }

        $this->stdout("Done. Total processed: {$totalImported}.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    private function syncProductCategories(int $productId, array $categoryIds): void
    {
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();

        try {
            $db->createCommand()
                ->delete('product_category', [
                    'product_id' => $productId,
                ])->execute();
            foreach ($categoryIds as $cid) {
                $db->createCommand()
                    ->insert('product_category', [
                        'product_id' => $productId,
                        'category_id' => $cid,
                    ])->execute();
            }
            $tx->commit();
        } catch (Throwable $e) {
            $tx->rollBack();
            $this->stderr('Failed to sync product categories: ' . $e->getMessage() . "\n", Console::FG_RED);
        }
    }
}
