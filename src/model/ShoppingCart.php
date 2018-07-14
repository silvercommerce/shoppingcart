<?php

namespace SilverCommerce\ShoppingCart\Model;

use SilverCommerce\OrdersAdmin\Model\Estimate;

/**
 * Custom version of an estimate that is be mapped to the ShoppingCartController 
 */
class ShoppingCart extends Estimate
{
    private static $table_name = "ShoppingCart";
}
