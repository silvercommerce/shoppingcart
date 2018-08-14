<?php

namespace SilverCommerce\ShoppingCart\Tasks;

use DateTime;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;
use SilverCommerce\OrdersAdmin\Model\Estimate;
use SilverCommerce\ShoppingCart\Model\ShoppingCart as ShoppingCart;

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

        $all = ShoppingCart::get()->filter([
            "StartDate:LessThan" => $past->format('Y-m-d H:i:s')
        ]);

        $i = 0;
        foreach ($all as $cart) {
            // Is the cart currentyl assigned to a member?
            $curr = Member::get()->find("CartID", $cart->ID);

            if (empty($curr)) {
                $cart->delete();
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