<?php

namespace SilverCommerce\ShoppingCart;

use SilverStripe\Control\Cookie;
use SilverStripe\Security\Security;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverCommerce\ShoppingCart\Tasks\CleanExpiredEstimatesTask;
use SilverCommerce\ShoppingCart\Model\ShoppingCart as ShoppingCartModel;
use SilverCommerce\ShoppingCart\Control\ShoppingCart as ShoppingCartController;
use SilverCommerce\OrdersAdmin\Factory\OrderFactory;

/**
 * Factory to handle setting up and interacting with a ShoppingCart
 * object.
 *
 */
class ShoppingCartFactory extends OrderFactory
{
    /**
     * Name of the test cookie used to check if cookies are allowed
     */
    const TEST_COOKIE = "ShoppingCartFactoryTest";

    /**
     * Name of Cookie/Session used to track cart access key
     */
    const COOKIE_NAME = "ShoppingCart.Key";

    /**
     * The default class that is used by the factroy
     *
     * @var string
     */
    private static $estimate_class = ShoppingCartModel::class;

    /**
     * The default class that is used by the factroy
     *
     * @var string
     */
    private static $controller = ShoppingCartController::class;

    /**
     * Should the cart globally check for stock levels on items added?
     * Using this setting will ignore individual "Stocked" settings
     * on Shopping Cart Items.
     *
     * @var string
     */
    private static $check_stock_levels = false;

    /**
     * whether or not the cleaning task should be left to a cron job
     *
     * @var boolean
     * @config
     */
    private static $cron_cleaner = false;

    /**
     * Allow the user to add multiple discounts to the cart
     * 0 = unlimited
     *
     * @var int
     * @config
     */
    private static $discount_limit = 1;

    /**
     * Should a cookie be used to track a link to a cart (so it persists
     * between browser sessions)?
     *
     * @var boolean
     * @config
     */
    private static $use_cookies = false;

    /**
     * Setup the shopping cart and return an instance
     *
     * @return ShoppingCart
     **/
    public function __construct()
    {
        $member = Security::getCurrentUser();
        $this->setIsInvoice(false);
        $cart = $this->findOrMake();

        // If we don't have any discounts, a user is logged in and he has
        // access to discounts through a group, add the discount here
        if (!$cart->Discounts()->Count() > 0 && $member && $member->getDiscount()) {
            $discount = $member->getDiscount();
            if ($discount->exists()) {
                $cart->addDiscount($discount);
            }
        }

        if (!$this->config()->cron_cleaner) {
            $this->cleanOld();
        }

        $this->order = $cart;
    }

    /**
     * Legacy get current method
     *
     * @return \SilverCommerce\OrdersAdmin\Model\Estimate
     */
    public function getCurrent()
    {
        return $this->getOrder();
    }

    /**
     * Get the current session from the current request
     *
     * @return Session
     */
    public function getSession()
    {
        $request = Injector::inst()->get(HTTPRequest::class);
        return $request->getSession();
    }

    /**
     * Either find an existing cart, or create a new one.
     *
     * @return ShoppingCartModel
     */
    public function findOrMake()
    {
        $cookies = $this->cookiesSupported();
        $session = $this->getSession();
        $classname = self::config()->get("estimate_class");
        $cart = null;
        $member = Security::getCurrentUser();

        if ($cookies) {
            $cart_id = Cookie::get(self::COOKIE_NAME);
        } else {
            $cart_id = $session->get(self::COOKIE_NAME);
        }

        // Try to get a cart from the the DB
        if (isset($cart_id)) {
            $cart = $classname::get()->find('AccessKey', $cart_id);
        }

        // Does the current member have a cart?
        if (empty($cart) && isset($member) && $member->getCart()) {
            $cart = $member->getCart();
        }

        // Finally, if nothing is set, create a new instance to return
        if (empty($cart)) {
            $cart = $classname::create();

            // Add new cart to current member (if existing)
            if (!empty($member)) {
                $member->setCart($cart);
            }
        }

        $cart->setAllowNegativeValue(false);

        return $cart;
    }

    /**
     * Run the task to clean old shopping carts
     *
     * @return null
     */
    public function cleanOld()
    {
        $siteconfig = SiteConfig::current_site_config();
        $date = $siteconfig->dbobject("LastEstimateClean");
        $request = Injector::inst()->get(HTTPRequest::class);

        if (!$date || ($date && !$date->IsToday())) {
            $task = Injector::inst()->create(CleanExpiredEstimatesTask::class);
            $task->setSilent(true);
            $task->run($request);
            $siteconfig->LastEstimateClean = DBDatetime::now()->Value;
            $siteconfig->write();
        }
    }

    /**
     * Test to see if a cookie should be used, or
     * the current user supports cookies
     *
     * @return boolean
     */
    public function cookiesSupported()
    {
        $use_cookies = $this->config()->use_cookies;

        if (!$use_cookies) {
            return false;
        }

        Cookie::set(self::TEST_COOKIE, 1);
        $cookie = Cookie::get(self::TEST_COOKIE);
        Cookie::force_expiry(self::TEST_COOKIE);

        return (empty($cookie)) ? false : true;
    }

    /**
     * Destroy current shopping cart
     *
     * @return self
     */
    public function delete()
    {
        $cookies = $this->cookiesSupported();
        $cart = $this->getOrder();

        // Only delete the cart if it has been written to the DB
        if ($cart->exists()) {
            $cart->delete();
        }

        if ($cookies) {
            Cookie::force_expiry(self::COOKIE_NAME);
        } else {
            $this->getSession()->clear(self::COOKIE_NAME);
        }

        return $this;
    }

    /**
     * Save the current shopping cart, by writing it to the DB and
     * generating a cookie/session (if user not logged in).
     *
     * @return self
     */
    public function write()
    {
        $cookies = $this->cookiesSupported();
        $session = $this->getSession();
        $member = Security::getCurrentUser();
        $cart = $this->getOrder();
        $cart->recalculateDiscounts();

        // If the cart exists and the current user's cart doesn't
        // match, they have just logged in, replace their cart with
        // the new one.
        if ($cart->exists() && isset($member) && $member->getCart() != $cart) {
            $member->setCart($cart);

            if ($cookies) {
                Cookie::force_expiry(self::COOKIE_NAME);
            } else {
                $session->clear(self::COOKIE_NAME);
            }
        }

        $cart->write();

        if (!$member && $cookies) {
            Cookie::set(self::COOKIE_NAME, $cart->AccessKey);
        } elseif (!$member) {
            $session->set(self::COOKIE_NAME, $cart->AccessKey);
        }

        return $this;
    }

    /**
     * Shortcut for write
     *
     * @return self
     */
    public function save()
    {
        return $this->write();
    }
}
