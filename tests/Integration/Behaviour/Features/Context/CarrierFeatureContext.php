<?php

namespace Tests\Integration\Behaviour\Features\Context;

use Address;
use Behat\Behat\Context\Context as BehatContext;
use Carrier;
use CartRule;
use Configuration;
use Country;
use RangePrice;
use State;
use Zone;

class CarrierFeatureContext implements BehatContext
{

    use CartAwareTrait;

    /**
     * @var Zone[]
     */
    protected $zones = [];

    /**
     * @var Country[]
     */
    protected $countries = [];

    /**
     * @var Country[]
     */
    protected $previousCountries = [];

    /**
     * @var State[]
     */
    protected $states = [];

    /**
     * @var Address[]
     */
    protected $addresses = [];

    /**
     * @var Carrier[]
     */
    protected $carriers = [];

    /**
     * @var RangePrice[]
     */
    protected $priceRanges = [];

    /**
     * @Given /^There is a zone with name (.+)$/
     */
    public function setZone($zoneName)
    {
        $zone = new Zone();
        $zone->name = $zoneName;
        $zone->add();
        $this->zones[$zoneName] = $zone;
    }

    /**
     * @param $zoneName
     */
    public function checkZoneWithNameExists($zoneName)
    {
        if (!isset($this->zones[$zoneName])) {
            throw new \Exception('Zone with name "' . $zoneName . '" was not added in fixtures');
        }
    }

    /**
     * @Given /^There is a country with name (.+) and iso code (.+) in zone named (.+)$/
     */
    public function setCountry($countryName, $isoCode, $zoneName)
    {
        $this->checkZoneWithNameExists($zoneName);
        $countryId = Country::getByIso($isoCode, false);
        if (!$countryId) {
            throw new \Exception('Country not found with iso code = ' . $isoCode);
        }
        $country = new Country($countryId);
        // clone country to be able to properly reset previous data
        $this->previousCountries[$countryName] = clone($country);
        $this->countries[$countryName] = $country;
        $country->id_zone = $this->zones[$zoneName]->id;
        $country->active = 1;
        $country->save();
    }

    /**
     * @param $countryName
     */
    public function checkCountryWithNameExists($countryName)
    {
        if (!isset($this->countries[$countryName])) {
            throw new \Exception('Country with name "' . $countryName . '" was not added in fixtures');
        }
    }

    /**
     * @Given /^There is a state with name (.+) and iso code (.+) in country named (.+) and zone named (.+)$/
     */
    public function setState($stateName, $stateIsoCode, $countryName, $zoneName)
    {
        $this->checkZoneWithNameExists($zoneName);
        $this->checkCountryWithNameExists($countryName);
        $state = new State();
        $state->name = $stateName;
        $state->iso_code = $stateIsoCode;
        $state->id_zone = $this->zones[$zoneName]->id;
        $state->id_country = $this->countries[$countryName]->id;
        $state->add();
        $this->states[$stateName] = $state;
    }

    /**
     * @param $stateName
     */
    public function checkStateWithNameExists($stateName)
    {
        if (!isset($this->states[$stateName])) {
            throw new \Exception('State with name "' . $stateName . '" was not added in fixtures');
        }
    }

    /**
     * @Given /^There is an address with name (.+) and post code (.+) in country named (.+) and state named (.+)$/
     */
    public function setAddress($addressName, $postCode, $countryName, $stateName)
    {
        $this->checkCountryWithNameExists($countryName);
        $this->checkStateWithNameExists($stateName);
        $address = new Address();
        $address->id_country = $this->countries[$countryName]->id;
        $address->id_state = $this->states[$stateName]->id;
        $address->postcode = $postCode;
        $address->lastname = 'lastname';
        $address->firstname = 'firstname';
        $address->address1 = 'address1';
        $address->city = 'city';
        $address->alias = 'alias';
        $address->add();
        $this->addresses[$addressName] = $address;
    }

    /**
     * @param $addressName
     */
    public function checkAddressWithNameExists($addressName)
    {
        if (!isset($this->addresses[$addressName])) {
            throw new \Exception('Address with name "' . $addressName . '" was not added in fixtures');
        }
    }

    /**
     * @Given /^There is a carrier with name (.+)$/
     */
    public function setCarrier($carrierName)
    {
        $carrier = new Carrier(null, Configuration::get('PS_LANG_DEFAULT'));
        $carrier->name = $carrierName;
        $carrier->shipping_method = Carrier::SHIPPING_METHOD_PRICE;
        $carrier->delay = '28 days later';
        $carrier->active = 1;
        $carrier->add();
        $this->carriers[$carrierName] = $carrier;
    }

    /**
     * @param $carrierName
     */
    public function checkCarrierWithNameExists($carrierName)
    {
        if (!isset($this->carriers[$carrierName])) {
            throw new \Exception('Carrier with name "' . $carrierName . '" was not added in fixtures');
        }
    }

    /**
     * @param $carrierName
     * @return Carrier
     */
    public function getCarrierWithName($carrierName)
    {
        return $this->carriers[$carrierName];
    }

    /**
     * @Given /^carrier with name (.+) has a shipping fees of ([\d\.]+) in zone with name (.+) for quantities between (\d+) and (\d+)$/
     */
    public function setCarrierFees($carrierName, $shippingPrices, $zoneName, $fromQuantity, $toQuantity)
    {
        $this->checkCarrierWithNameExists($carrierName);
        $this->checkZoneWithNameExists($zoneName);
        $rangeId = RangePrice::rangeExist($this->carriers[$carrierName]->id, $fromQuantity, $toQuantity);
        if (!empty($rangeId)) {
            $range = new RangePrice($rangeId);
        } else {
            $range = new RangePrice();
            $range->id_carrier = $this->carriers[$carrierName]->id;
            $range->delimiter1 = $fromQuantity;
            $range->delimiter2 = $toQuantity;
            $range->add();
            $this->priceRanges[] = $range;
        }
        $carrierPriceRange = [
            'id_range_price' => (int)$range->id,
            'id_range_weight' => null,
            'id_carrier' => (int)$this->carriers[$carrierName]->id,
            'id_zone' => (int)$this->zones[$zoneName]->id,
            'price' => $shippingPrices,
        ];
        $this->carriers[$carrierName]->addDeliveryPrice([$carrierPriceRange]);
    }

    /**
     * @AfterScenario
     */
    public function cleanData()
    {
        foreach ($this->priceRanges as $priceRange) {
            $priceRange->delete();
        }
        $this->priceRanges = [];
        foreach ($this->carriers as $carrier) {
            $carrier->delete();
        }
        $this->carriers = [];
        foreach ($this->addresses as $address) {
            $address->delete();
        }
        $this->addresses = [];
        foreach ($this->states as $state) {
            $state->delete();
        }
        $this->states = [];
        foreach ($this->countries as $countryName => $country) {
            $country->id_zone = $this->previousCountries[$countryName]->id_zone;
            $country->active = $this->previousCountries[$countryName]->active;
            $country->save();
        }
        $this->previousCountries = [];
        $this->countries = [];
        foreach ($this->zones as $zone) {
            $zone->delete();
        }
        $this->zones = [];
    }

    /**
     * @When /^I select in my cart carrier with name (.+)$/
     */
    public function setCartCarrier($carrierName)
    {
        $this->checkCarrierWithNameExists($carrierName);
        $this->getCurrentCart()->id_carrier = $this->carriers[$carrierName]->id;

        $this->getCurrentCart()->update();

        CartRule::autoRemoveFromCart();
        CartRule::autoAddToCart();
    }

    /**
     * @When /^I select in my cart address with name (.+)$/
     */
    public function setCartAddress($addresssName)
    {
        $this->checkAddressWithNameExists($addresssName);
        $this->getCurrentCart()->id_address_delivery = $this->addresses[$addresssName]->id;
    }
}
