<?php

namespace SilverCommerce\ShoppingCart\Model;

use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverCommerce\OrdersAdmin\Model\Estimate;
use SilverCommerce\ShoppingCart\Control\ShoppingCart as ShoppingCartController;

/**
 * Custom version of an estimate that is be mapped to the ShoppingCartController
 */
class ShoppingCart extends Estimate
{
    private static $table_name = "ShoppingCart";

    /**
     * Get the link to this controller
     *
     * @param string $action The action you want to add to the link
     * @return string
     */
    public function Link(string $action = "")
    {
        $controller = Injector::inst()->create(ShoppingCartController::class);
        return $controller->Link($action);
    }

    /**
     * Get an absolute link to this controller
     *
     * @param string $action The action you want to add to the link
     * @return string
     */
    public function AbsoluteLink(string $action = "")
    {
        return Director::absoluteURL($this->Link($action));
    }

    /**
     * Get a relative (to the root url of the site) link to this
     * controller
     *
     * @param string $action The action you want to add to the link
     * @return string
     */
    public function RelativeLink(string $action = "")
    {
        return Controller::join_links(
            Director::baseURL(),
            $this->Link($action)
        );
    }
}
