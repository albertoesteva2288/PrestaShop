<?php

namespace Tests\Integration\Behaviour\Features\Context;

use Behat\Behat\Context\Context as BehatContext;
use Behat\Behat\Tester\Exception\PendingException;
use Cart;
use Context;
use LegacyTests\Unit\Core\Cart\Calculation\CartOld;

class CartFeatureContext implements BehatContext
{

    use CartAwareTrait;

    /**
     * @var CartOld
     */
    protected $cart;

    /**
     * @Given I have an empty default cart
     */
    public function iHaveAnEmptyDefaultCart()
    {
        $cart = new CartOld();
        $cart->id_lang = (int)Context::getContext()->language->id;
        $cart->id_currency = (int)Context::getContext()->currency->id;
        $cart->id_shop = (int)Context::getContext()->shop->id;
        $cart->add(); // required, else we cannot get the content when calculating total
        Context::getContext()->cart = $cart;
    }

    /**
     * This hook can be used to perform a database cleaning of added objects
     *
     * @AfterScenario
     */
    public function emptyCartProducts()
    {
        if ($this->getCurrentCart() !== null) {
            // remove products from cart
            $productDatas = $this->getCurrentCart()->getProducts(true);
            foreach ($productDatas as $productData) {
                $this->getCurrentCart()->updateQty(0, $productData['id_product'], $productData['id_product_attribute']);
            }
            // delete cart
            $this->getCurrentCart()->delete();
        }
    }

    /**
     * @Then /^Distinct product count in my cart should be (\d+)$/
     */
    public function productCountInMyCartShouldBe($productCount)
    {
        $currentCartProducts = $this->getCurrentCart()->getProducts(true);
        if ($productCount != count($currentCartProducts)) {
            throw new \RuntimeException(
                sprintf(
                    'Expects %s, got %s instead',
                    $productCount,
                    count($currentCartProducts)
                )
            );
        }
    }

    /**
     * @Then /^Total product count in my cart should be (\d+)$/
     */
    public function totalProductCountInMyCartShouldBe($productCount)
    {
        $currentCartProducts = Cart::getNbProducts($this->getCurrentCart()->id);
        if ($productCount != $currentCartProducts) {
            throw new \RuntimeException(
                sprintf(
                    'Expects %s, got %s instead',
                    $productCount,
                    $currentCartProducts
                )
            );
        }
    }

    /**
     * @Then /^Expected total of my cart tax included should be (precisely )?([\d\.]+)$/
     */
    public function totalCartWithTaxtShouldBe($precisely, $expectedTotal)
    {
        $this->expectsTotal($expectedTotal, 'v2', true, !empty($precisely));
    }

    /**
     * @Then /^Expected total of my cart tax included should be (precisely )?([\d\.]+) with previous calculation method$/
     */
    public function totalCartWithTaxtOnPreviousCaclculationMethodShouldBe($precisely, $expectedTotal)
    {
        $this->expectsTotal($expectedTotal, 'v1', true, !empty($precisely));
    }

    /**
     * @Then /^Expected total of my cart tax excluded should be (precisely )?([\d\.]+)$/
     */
    public function totalCartWithoutTaxShouldBe($precisely, $expectedTotal)
    {
        $this->expectsTotal($expectedTotal, 'v2', false, !empty($precisely));
    }

    /**
     * @Then /^Expected total of my cart tax excluded should be (precisely )?([\d\.]+) with previous calculation method$/
     */
    public function totalCartWithoutTaxOnPreviousCaclculationMethodShouldBe($precisely, $expectedTotal)
    {
        $this->expectsTotal($expectedTotal, 'v1', false, !empty($precisely));
    }

    protected function expectsTotal($expectedTotal, $method, $withTax = true, $precisely = false)
    {
        $cart = $this->getCurrentCart();
        $carrierId = (int)$cart->id_carrier <= 0 ? null : $cart->id_carrier;
        if ($method == 'v1') {
            $total = $cart->getOrderTotalV1($withTax, Cart::BOTH, null, $carrierId);
        } else {
            $total = $cart->getOrderTotal($withTax, Cart::BOTH, null, $carrierId);
        }
        if (!$precisely) {
            // here we round values to avoid round issues : rounding modes are tested by specific tests
            $expectedTotal = round($expectedTotal, 1);
            $total = round($total, 1);
        }
        if ($expectedTotal != $total) {
            throw new \RuntimeException(
                sprintf(
                    'Expects %s, got %s instead',
                    $expectedTotal,
                    $total
                )
            );
        }
    }

    /**
     * @When /^I select gift wrapping$/
     */
    public function iSelectGiftWrapping()
    {
        $this->getCurrentCart()->gift = true;
    }

    /**
     * @Then /^Cart shipping fees should be ([\d\.]+)$/
     */
    public function calculateCartShippingFees($expectedShippingFees)
    {
        $expectedTotal = round($expectedShippingFees, 1);
        $shippingFees = round($this->getCurrentCart()->getPackageShippingCost($this->getCurrentCart()->id_carrier), 1);
        if ($expectedTotal != $shippingFees) {
            throw new \RuntimeException(
                sprintf(
                    'Expects %s, got %s instead',
                    $expectedTotal,
                    $shippingFees
                )
            );
        }
    }
}
