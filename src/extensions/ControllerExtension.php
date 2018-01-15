<?php

namespace SilverCommerce\ShoppingCart\Extensions;

use SilverStripe\Core\Extension;
use SilverCommerce\ShoppingCart\Control\ShoppingCart;

/**
 * Extension for Content Controller that provide methods such
 * as cart link and category list to templates
 *
 * @author ilateral (http://www.ilateral.co.uk)
 * @package shoppingcart
 */
class ControllerExtension extends Extension
{
    
    /**
     * Get the current shoppingcart
     * 
     * @return ShoppingCart
     */
    public function getShoppingCart()
    {
        return ShoppingCart::get();
    }
}
