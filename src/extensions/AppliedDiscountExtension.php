<?php

namespace SilverCommerce\ShoppingCart\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Control\Controller;
use SilverCommerce\ShoppingCart\ShoppingCartFactory;

class AppliedDiscountExtension extends DataExtension
{
    public function RemoveLink()
    {
        $cart = ShoppingCartFactory::create()->getOrder();

        $controller = Controller::curr();

        return Controller::join_links(
            $cart->Link('removediscount'),
            $this->owner->ID
        );
    }
}
