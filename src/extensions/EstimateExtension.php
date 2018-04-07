<?php

namespace SilverCommerce\ShoppingCart\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverCommerce\ShoppingCart\Control\ShoppingCart;
use SilverStripe\Forms\FieldList;

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

    public function updateCMSFields(FieldList $fields)
    {
        $cart_field = $fields->dataFieldByName("ShoppingCart");
        $fields->removeByName("ShoppingCart");
        $sidebar = $fields->find("Name","OrdersSidebar");

        foreach ($fields->flattenFields() as $field) {
            if ($field->getName() == "OrdersSidebar") {
                $field->push($cart_field);
            }
        }
    }
}
