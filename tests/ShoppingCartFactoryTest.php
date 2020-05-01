<?php

namespace SilverCommerce\ShoppingCart\Tests;

use SilverStripe\Control\Cookie;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverCommerce\ShoppingCart\ShoppingCartFactory;
use SilverCommerce\ShoppingCart\Tests\Model\TestProduct;

class ShoppingCartFactoryTest extends SapphireTest
{
    /**
     * Add some scaffold order records
     *
     * @var string
     */
    protected static $fixture_file = 'ShoppingCart.yml';

    /**
     * Setup test only objects
     *
     * @var array
     */
    protected static $extra_dataobjects = [
        TestProduct::class
    ];

    public function setUp()
    {
        // Ensure we setup a session and the current request
        $request = new HTTPRequest('GET', '/');
        $session = new Session(null);
        $session->init($request);
        $request->setSession($session);
        Injector::inst()
            ->registerService($request, HTTPRequest::class);

        parent::setUp();
    }

    /**
     * Test setting up the cart using ShoppingCartFactory
     *
     * @return null
     */
    public function testConstruction()
    {
        $use_cookies = ShoppingCartFactory::config()->use_cookies;
        ShoppingCartFactory::config()->use_cookies = true;
        Cookie::set(ShoppingCartFactory::COOKIE_NAME, "abc123");

        $cart = ShoppingCartFactory::create()->getOrder();

        $this->assertEquals("abc123", $cart->AccessKey);

        ShoppingCartFactory::config()->use_cookies = $use_cookies;
    }

    /**
     * Test adding an item to the shopping cart
     *
     * @return null
     */
    public function testAddItem()
    {
        $use_cookies = ShoppingCartFactory::config()->use_cookies;
        ShoppingCartFactory::config()->use_cookies = true;
        Cookie::set(ShoppingCartFactory::COOKIE_NAME, "abc123");

        $product = TestProduct::create(
            [
                "Title" => "A stock item",
                "BasePrice" => 5.99
            ]
        );
        $product->write();

        $cart = ShoppingCartFactory::create()
            ->addItem($product, 2)
            ->getOrder();

        $this->assertEquals(1, $cart->Items()->count());
        $this->assertEquals(2, $cart->TotalItems);
        $this->assertEquals(11.98, $cart->Total);

        ShoppingCartFactory::config()->use_cookies = $use_cookies;
    }

    /**
     * Test updating an item increases the quantity and that
     * adding an existing item calls "update"
     *
     * @return null
     */
    public function testUpdateItem()
    {
        $use_cookies = ShoppingCartFactory::config()->use_cookies;
        ShoppingCartFactory::config()->use_cookies = true;
        Cookie::set(ShoppingCartFactory::COOKIE_NAME, "123abc");

        $cart = ShoppingCartFactory::create();
        $item = $cart->getOrder()->Items()->first();
        $cart = $cart->updateItem($item->Key, 1)->getOrder();

        $this->assertEquals(1, $cart->Items()->count());
        $this->assertEquals(2, $cart->TotalItems);
        $this->assertEquals(11.98, $cart->Total);
    
        // Now test adding an new item that should update
        $product = TestProduct::create(
            [
                "Title" => "A cheap item",
                "BasePrice" => 5.99,
                "Quantity" => 1,
                "StockID" => "Item1"
            ]
        );
        $product->write();

        $cart = ShoppingCartFactory::create()
            ->addItem($product)
            ->getOrder();

        $this->assertEquals(1, $cart->Items()->count());
        $this->assertEquals(3, $cart->TotalItems);
        $this->assertEquals(17.97, $cart->Total);

        ShoppingCartFactory::config()->use_cookies = $use_cookies;
    }

    /**
     * Test removing an item
     *
     * @return null
     */
    public function testRemoveItem()
    {
        $use_cookies = ShoppingCartFactory::config()->use_cookies;
        ShoppingCartFactory::config()->use_cookies = true;
        Cookie::set(ShoppingCartFactory::COOKIE_NAME, "123abc");

        $cart = ShoppingCartFactory::create();
        $item = $cart->getOrder()->Items()->first();
        $cart = $cart->removeItem($item->Key)->getOrder();

        $this->assertEquals(0, $cart->Items()->count());
        $this->assertEquals(0, $cart->TotalItems);

        ShoppingCartFactory::config()->use_cookies = $use_cookies;
    }

    /**
     * Test deleting the cart
     *
     * @return null
     */
    public function testDelete()
    {
        $use_cookies = ShoppingCartFactory::config()->use_cookies;
        ShoppingCartFactory::config()->use_cookies = true;
        Cookie::set(ShoppingCartFactory::COOKIE_NAME, "123abc");

        $cart = ShoppingCartFactory::create()->delete();

        $this->assertFalse($cart->getOrder()->exists());

        ShoppingCartFactory::config()->use_cookies = $use_cookies;
    }

    /**
     * Test saving a new cart
     *
     * @return null
     */
    public function testWrite()
    {
        $cart = ShoppingCartFactory::create()
            ->write()
            ->getOrder();

        $this->assertTrue($cart->exists());
    }
}
