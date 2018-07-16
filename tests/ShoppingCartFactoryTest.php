<?php

namespace SilverCommerce\ShoppingCart\Tests;

use SilverStripe\ORM\DataList;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverCommerce\Discounts\DiscountFactory;
use SilverCommerce\ShoppingCart\ShoppingCartFactory;
use SilverCommerce\OrdersAdmin\Model\LineItem;

class ShoppingCartFactoryTest extends SapphireTest
{
    /**
     * Add some scaffold order records
     *
     * @var string
     */
    protected static $fixture_file = 'ShoppingCart.yml';

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
        Cookie::set(ShoppingCartFactory::COOKIE_NAME, "abc123");

        $cart = ShoppingCartFactory::create()->getCurrent();

        $this->assertEquals("abc123", $cart->AccessKey);
    }

    /**
     * Test adding an item to the shopping cart
     * 
     * @return null
     */
    public function testAddItem()
    {
        Cookie::set(ShoppingCartFactory::COOKIE_NAME, "abc123");

        $item = LineItem::create([
            "Title" => "A stock item",
            "Price" => 5.99,
            "Quantity" => 2
        ]);

        $cart = ShoppingCartFactory::create()
            ->addItem($item)
            ->getCurrent();

        $this->assertEquals(1, $cart->Items()->count());
        $this->assertEquals(2, $cart->TotalItems);
        $this->assertEquals(11.98, $cart->Total);
    }

    /**
     * Test updating an item increases the quantity and that
     * adding an existing item calls "update"
     * 
     * @return null
     */
    public function testUpdateItem()
    {
        Cookie::set(ShoppingCartFactory::COOKIE_NAME, "123abc");

        $cart = ShoppingCartFactory::create();
        $item = $cart->getCurrent()->Items()->first();
        $cart = $cart->updateItem($item, 2)->getCurrent();

        $this->assertEquals(1, $cart->Items()->count());
        $this->assertEquals(2, $cart->TotalItems);
        $this->assertEquals(11.98, $cart->Total);
    
        // Now test adding an new item that should update
        $item = LineItem::create([
            "Title" => "A cheap item",
            "Price" => 5.99,
            "Quantity" => 1,
            "StockID" => "Item1"
        ]);

        $cart = ShoppingCartFactory::create()
            ->addItem($item)
            ->getCurrent();

        $this->assertEquals(1, $cart->Items()->count());
        $this->assertEquals(3, $cart->TotalItems);
        $this->assertEquals(17.97, $cart->Total);
    }

    /**
     * Test removing an item
     * 
     * @return null
     */
    public function testRemoveItem()
    {
        Cookie::set(ShoppingCartFactory::COOKIE_NAME, "123abc");

        $cart = ShoppingCartFactory::create();
        $item = $cart->getCurrent()->Items()->first();
        $cart = $cart->removeItem($item)->getCurrent();

        $this->assertEquals(0, $cart->Items()->count());
        $this->assertEquals(0, $cart->TotalItems);
    }

    /**
     * Test deleting the cart
     * 
     * @return null
     */
    public function testDelete()
    {
        Cookie::set(ShoppingCartFactory::COOKIE_NAME, "123abc");

        $cart = ShoppingCartFactory::create()->delete();

        $this->assertFalse($cart->getCurrent()->exists());
    }

    /**
     * Test saving a new cart
     * 
     * @return null
     */
    public function testSave()
    {
        $cart = ShoppingCartFactory::create()
            ->save()
            ->getCurrent();

        $this->assertTrue($cart->exists());
    }
}