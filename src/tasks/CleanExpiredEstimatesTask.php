<?php

namespace SilverCommerce\ShoppingCart\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\Director;
use SilverCommerce\OrdersAdmin\Model\Estimate;
use DateTime;

/**
 * Simple task that removes estimates that have passed their end date
 * and are not assigned to a customer.
 * 
 * @author ilateral (http://www.ilateral.co.uk)
 * @package shoppingcart
 */
class CleanExpiredEstimatesTask extends BuildTask
{
    protected $title = 'Clean expired estimates';

    protected $description = 'Clean all estimates that are past their expiration date and have no users assifgned';

    protected $enabled = true;

    /**
     * Should this task output commands 
     *
     * @var boolean
     */
    protected $silent = false;

    /**
     * @return boolean
     */
    public function getSilent()
    {
        return $this->silent;
    }

    /**
     * set the silent parameter
     *
     * @param boolean $set
     * @return CleanExpiredEstimatesTask
     */
    public function setSilent($set)
    {
        $this->silent = $set;
        return $this;
    }

    function run($request) {
        $now = new DateTime();
        $days = Estimate::config()->default_end;
        $past = $now->modify("-{$days} days");

        $estimates = Estimate::get()->filter([
            'ShoppingCart' => true,
            "Date:LessThan" => $past->format('Y-m-d H:i:s')
        ]);

        $i = 0;
        foreach ($estimates as $estimate) {
            if (!$estimate->CustomerID) {
                $estimate->delete();
                $i++;
            }
        }

        $this->log('removed '.$i.' expired estimates.');
    }

    private function log($message)
    {
        if (!$this->silent) {
            if(Director::is_cli()) {
                echo $message . "\n";
            } else {
                echo $message . "<br/>";
            }
        }
    }
}