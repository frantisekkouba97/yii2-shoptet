<?php

namespace app\controllers;

use app\components\ShoptetClient;
use app\models\Product;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;

class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'update-description' => ['post'],
                    'detail' => ['get'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Homepage with product list.
     */
    public function actionIndex(): string
    {
        $dataProvider = new ActiveDataProvider([
            'query' => Product::find()->with('categories')->orderBy(['id' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Returns product detail JSON with current price and categories.
     */
    public function actionDetail(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $product = Product::findOne($id);

        if (!$product) {
            return ['success' => false, 'error' => 'Product not found'];
        }

        $client = new ShoptetClient();
        $priceText = null;
        $categories = [];
        $imageUrl = $product->image_url;
        $stockQty = $product->stock_qty;

        try {
            $detail = $client->getProductDetail($product->guid, ['images', 'allCategories', 'perPricelistPrices']);
            $data = $detail['data'] ?? [];

            // Price: use first variant price + currencyCode if present
            if (!empty($data['variants'][0])) {
                $variant = $data['variants'][0];
                if (isset($variant['price']) && isset($variant['currencyCode'])) {
                    $priceText = $variant['price'] . ' ' . $variant['currencyCode'];
                } elseif (isset($variant['price'])) {
                    $priceText = (string) $variant['price'];
                }
            }

            // Categories (requires include=allCategories)
            if (!empty($data['categories']) && is_array($data['categories'])) {
                foreach ($data['categories'] as $category) {
                    $name = $category['name'] ?? '';
                    if ($name !== '') {
                        $categories[] = $name;
                    }
                }
            }

            // Image
            if (!empty($data['mainImage']) && is_array($data['mainImage'])) {
                $built = $client->buildImageUrl($data['mainImage']);
                if ($built) {
                    $imageUrl = $built;
                }
            }

            // Stock: sum variant stocks
            if (!empty($data['variants']) && is_array($data['variants'])) {
                $sum = 0.0;
                foreach ($data['variants'] as $variant) {
                    if (isset($variant['stock']) && $variant['stock'] !== '') {
                        $sum += (float) $variant['stock'];
                    }
                }
                $stockQty = (int) round($sum);
            }
        } catch (\Throwable $e) {
        }

        if (empty($categories)) {
            $categories = array_map(static function ($c) { return $c->name; }, $product->categories);
        }

        return [
            'success' => true,
            'data' => [
                'name' => $product->name,
                'code' => $product->code,
                'price' => $priceText,
                'categories' => $categories,
                'url' => $product->url,
                'imageUrl' => $imageUrl,
                'stock' => $stockQty,
                'description' => $product->description,
            ],
        ];
    }

    /**
     * Updates product description by prefixing with "testFrantisek".
     */
    public function actionUpdateDescription(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $product = Product::findOne($id);

        if (!$product) {
            return ['success' => false, 'error' => 'Product not found'];
        }

        $prefix = 'testFrantisek ';
        $newDesc = $product->description ?? '';

        if (stripos($newDesc, $prefix) !== 0) {
            $newDesc = $prefix . $newDesc;
        }

        $client = new ShoptetClient();

        try {
            $client->updateProductDescription($product->guid, $newDesc);
            $product->description = $newDesc;
            $product->save(false);

            return ['success' => true];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
