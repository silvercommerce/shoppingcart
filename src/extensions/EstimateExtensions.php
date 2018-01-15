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
class EstimateExtension extends DataExtension
{
    private static $db = [
        "ShoppingCart" => "Boolean"
    ];
}
