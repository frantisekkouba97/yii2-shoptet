<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * @property int $id
 * @property string $shoptet_id
 * @property string $name
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 *
 * @property Product[] $products
 */
class Category extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'category';
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
            [['shoptet_id', 'name'], 'required'],
            [['shoptet_id'],
                'string',
                'max' => 64],
            [['name'],
                'string',
                'max' => 512],
            [['shoptet_id'], 'unique'],
        ];
    }

    public function getProducts(): ActiveQuery
    {
        return $this->hasMany(Product::class, [
            'id' => 'product_id',
        ])
            ->viaTable('product_category', [
                'category_id' => 'id',
            ]);
    }
}
