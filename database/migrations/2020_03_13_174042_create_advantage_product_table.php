<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdvantageProductTable extends Migration
{
    public function up()
    {
        Schema::create('advantage_product', function (Blueprint $table) {
            $table->unsignedInteger('advantage_id');
            $table->unsignedBigInteger('product_id');

            $table
                ->foreign('advantage_id')
                ->references('id')
                ->on('advantages');

            $table
                ->foreign('product_id')
                ->references('id')
                ->on('products');
        });
    }

    public function down()
    {
        Schema::dropIfExists('advantage_product');
    }
}
