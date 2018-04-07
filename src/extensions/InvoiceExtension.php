<?php

namespace SilverCommerce\ShoppingCart\Extensions;

use SilverStripe\ORM\DataExtension;

/**
 * Ensure we disable shopping cart status when marked as paid
 *
 */
class InvoiceExtension extends DataExtension
{
    public function onBeforeWrite()
    {
        if ($this->owner->isPaid()) {
            $this->owner->ShoppingCart = 0;
        }
    }
}
