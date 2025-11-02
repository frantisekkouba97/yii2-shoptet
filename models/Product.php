<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * @property int $id
 * @property string $guid
 * @property string|null $code
 * @property string $name
 * @property string|null $url
 * @property string|null $image_url
 * @property int|null $stock_qty
 * @property string|null $description
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 *
 * @property Category[] $categories
 */
class Product extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'product';
    }

    public function behaviors(): array
    {
        return [[
            'class' => TimestampBehavior::class,
            'createdAtAttribute' => 'created_at',
            'updatedAtAttribute' => 'updated_at',
            'value' => new Expression('NOW()'),
        ]];
    }

    public function rules(): array
    {
        return [
            [['guid', 'name'], 'required'],
            [['description'], 'string'],
            [['stock_qty'], 'integer'],
            [['guid'],
                'string',
                'max' => 64],
            [['code'],
                'string',
                'max' => 128],
            [['name'],
                'string',
                'max' => 512],
            [['url', 'image_url'],
                'string',
                'max' => 1024],
            [['guid'], 'unique'],
        ];
    }

    public function getCategories(): ActiveQuery
    {
        return $this->hasMany(Category::class, [
            'id' => 'category_id',
        ])
            ->viaTable('product_category', [
                'product_id' => 'id',
            ]);
    }
}
