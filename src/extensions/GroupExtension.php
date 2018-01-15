<?php

namespace SilverCommerce\ShoppingCart\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverCommerce\ShoppingCart\Control\ShoppingCart;

/**
 * Overwrite group object so we can setup default groups
 * 
 * @author i-lateral (http://www.i-lateral.com)
 * @package shoppingcart
 */
class OrdersGroupExtension extends DataExtension
{
    private static $belongs_many_many = array(
        "Discounts" => "Discount"
    );
}
