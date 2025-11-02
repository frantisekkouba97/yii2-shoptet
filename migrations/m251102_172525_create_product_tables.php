<?php

use yii\db\Migration;

/**
 * Handles the creation of tables `product`, `category` and pivot `product_category`.
 */
class m251102_172525_create_product_tables extends Migration
{
    public function safeUp()
    {
        // product table
        $this->createTable('product', [
            'id' => $this->primaryKey(),
            'guid' => $this->string(64)->notNull()->unique(),
            'code' => $this->string(128)->null(),
            'name' => $this->string(512)->notNull(),
            'url' => $this->string(1024)->null(),
            'image_url' => $this->string(1024)->null(),
            'stock_qty' => $this->integer()->null(),
            'description' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        // category table
        $this->createTable('category', [
            'id' => $this->primaryKey(),
            'shoptet_id' => $this->string(64)->notNull()->unique(),
            'name' => $this->string(512)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        // pivot table
        $this->createTable('product_category', [
            'product_id' => $this->integer()->notNull(),
            'category_id' => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey('pk_product_category', 'product_category', ['product_id', 'category_id']);
        $this->addForeignKey('fk_pc_product', 'product_category', 'product_id', 'product', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_pc_category', 'product_category', 'category_id', 'category', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_pc_product', 'product_category');
        $this->dropForeignKey('fk_pc_category', 'product_category');
        $this->dropTable('product_category');
        $this->dropTable('category');
        $this->dropTable('product');
    }
}
