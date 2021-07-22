<?php
namespace Codilar\Shipment\Model\Carrier;

use Magento\Catalog\Model\ProductRepository;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Psr\Log\LoggerInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
/**
 * Class Custom
 * @package Codilar\Shipment\Model\Carrier
 */
class Custom extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'custom';
    /**
     * @var ResultFactory
     */
    protected $rateResultFactory;
    /**
     * @var MethodFactory
     */
    protected $rateMethodFactory;
    /**
     * @var Cart
     */
    private $cart;
    /**
     * @var ProductRepository
     */
    private $productRepository;
    /**
     * @var ManagerInterface
     */
    private $manager;

    /**
     * Custom constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param ProductRepository $productRepository
     * @param ManagerInterface $manager
     * @param Cart $cart
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        ProductRepository $productRepository,
        ManagerInterface $manager,
        Cart $cart,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->cart = $cart;
        $this->productRepository = $productRepository;
        $this->manager = $manager;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return ['custom' => $this->getConfigData('name')];
    }

    /**
     * @param RateRequest $request
     * @return false|Result
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier('custom');
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod('custom');
        $method->setMethodTitle($this->getConfigData('name'));

        $amount = $this->getShippingPrice();

        $method->setPrice($amount);
        $method->setCost($amount);

        $result->append($method);

        return $result;
    }
    /**
     * @return float|int|mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getShippingPrice(){
        $items = $this->cart->getQuote()->getAllItems();
        $baseAmount = $this->getConfigData('price');
        $price=0;
        foreach ($items as $item){
            $productId = $item->getProductId();
            $product = $this->productRepository->getById($productId);
            $zipcode = $product->getCustomAttribute('region_zipcodes');
            $weight = $product->getWeight();
            $qty = $item->getQty();
            if(isset($zipcode)){
                $price = $price +($baseAmount*$weight*$qty);
            }
            else{
                $productName = $product->getName();
                $this->manager->addErrorMessage(
                    __(sprintf(
                            'The Product %s not deliverable at the moment. Please try again later',
                            $productName)
                    )
                );
            }
        }
        return $price;
    }
}
