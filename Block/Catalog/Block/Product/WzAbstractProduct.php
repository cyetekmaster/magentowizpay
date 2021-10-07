<?php
 
namespace Wizpay\Wizpay\Block\Catalog\Block\Product;
 
use \Wizpay\Wizpay\Helper\Data;
use Magento\Framework\View\Asset\Repository;

class WzAbstractProduct
{
    private $wizpay_helper;
    private $assetRepository;
    

    public function __construct(
         Data $helper,
         Repository $assetRepository
    ){
        $this->wizpay_helper = $helper;
        $this->assetRepository = $assetRepository;
    }

    private $displayBlocks = ['product.info.addtocart'];


    public function aroundGetProductDetailsHtml(
        $subject,
        $proceed,
        \Magento\Catalog\Model\Product 	$product)
    {         

        $price = $product->getPrice();
        $min_price =  $product->getMinPrice();
        $max_price = $product->getMaxPrice();

       return $this->wizpay_helper->getWizpayMessage('List', $price, $this->assetRepository, $min_price, $max_price);        
    }


}