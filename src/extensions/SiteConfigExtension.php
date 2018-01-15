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
    
    private static $db = array(
        "EnableClickAndCollect" => "Boolean",
        "ShowPriceAndTax" => "Boolean",
        'LastEstimateClean' => 'DBDatetime'
    );
    
    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName("LastEstimateClean");
        
        $misc_fields = $fields->findByName("MiscFields");

        if (!$misc_fields) {
            $misc_fields = ToggleCompositeField::create(
                'MiscSettings',
                _t("ShoppingCart.MiscSettings", "Misc Settings"),
                []
            );

            $fields->addFieldToTab(
                "Root.Shop",
                $misc_fields
            );
        }

        $misc_fields->push(CheckboxField::create(
            "ShowPriceAndTax",
            $this->owner->fieldLabel("ShowPriceAndTax")
        ));

        $misc_fields->push(CheckboxField::create(
            "EnableClickAndCollect",
            $this->owner->fieldLabel("EnableClickAndCollect")
        ));
    }
}
