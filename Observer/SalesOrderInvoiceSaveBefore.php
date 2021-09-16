<?php
namespace Wizpay\Wizpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Framework\ObjectManager\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Catalog\Model\ProductRepository;

class SalesOrderInvoiceSaveBefore implements \Magento\Framework\Event\ObserverInterface
{

    /**
     *
     * @var \Magento\Framework\ObjectManager\ObjectManager
     */
    protected $_objectManager;
    protected $_orderFactory;
    protected $_checkoutSession;
    protected $productRepository;
    protected $stockRegistry;
    
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\ObjectManager\ObjectManager $objectManager,
        ProductRepository $productRepository,
        StockRegistryInterface $stockRegistry
    ) {
        $this->_objectManager = $objectManager;
        $this->_orderFactory = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
    }
    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer) // phpcs:ignore
    {
    }
}
