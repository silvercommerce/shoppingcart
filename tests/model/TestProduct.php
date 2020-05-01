<?php

namespace SilverCommerce\ShoppingCart\Tests\Model;

use SilverCommerce\TaxAdmin\Interfaces\TaxableProvider;
use SilverCommerce\TaxAdmin\Model\TaxRate;
use SilverCommerce\TaxAdmin\Traits\Taxable;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class TestProduct extends DataObject implements TestOnly, TaxableProvider
{
    use Taxable;

    private static $db = [
        "Title" => "Varchar",
        "StockID" => "Varchar",
        "BasePrice" => "Decimal(9,3)",
        "StockLevel" => "Int",
    ];

    public function getBasePrice()
    {
        return $this->dbObject('BasePrice')->getValue();
    }

    public function getBulkPrice()
    {
        return $this->dbObject('BasePrice')->getValue();
    }

    public function getTaxRate()
    {
        return TaxRate::create(
            [
                "Title" => "VAT",
                "Rate" => 20.00,
                'Global' => true
            ]
        );
    }

    public function getLocale()
    {
        return 'en_GB';
    }

    public function getShowPriceWithTax()
    {
        return true;
    }

    public function getShowTaxString()
    {
        return false;
    }

    public function getPricingGroup()
    {
        // Slightly hacky way to get around BulkPricingModule (if installed)
        return self::create(['ID' => -1]);
    }
}
