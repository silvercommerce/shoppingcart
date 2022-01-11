<?php

namespace SilverCommerce\ShoppingCart\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\CheckboxField;

/**
 * Extension for Site Config that provides extra features
 *
 * @author ilateral (http://www.ilateral.co.uk)
 * @package shoppingcart
 */
class SiteConfigExtension extends DataExtension
{
    
    private static $db = [
        "ShowCartPostageForm" => "Boolean",
        "ShowCartDiscountForm" => "Boolean",
        'LastEstimateClean' => 'DBDatetime'
    ];
    
    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName("ShowCartPostageForm");
        $fields->removeByName("ShowCartDiscountForm");
        $fields->removeByName("LastEstimateClean");

        $fields->addFieldToTab(
            "Root.Shop",
            ToggleCompositeField::create(
                'ShoppingCartSettings',
                _t("ShoppingCart.ShoppingCartSettings", "Basket Settings"),
                [
                    CheckboxField::create(
                        "ShowCartPostageForm",
                        $this->owner->fieldLabel("ShowCartPostageForm")
                    ),
                    CheckboxField::create(
                        "ShowCartDiscountForm",
                        $this->owner->fieldLabel("ShowCartDiscountForm")
                    )
                ]
            )
        );
    }
}
