<?php
 
namespace Wizpay\Wizpay\Block\Catalog\Block\Product;
 


class WzListProduct
{


    private $wizpay_helper;
    private $assetRepository;
    

    public function __construct(
       \Wizpay\Wizpay\Helper\Data $helper,
       \Magento\Framework\View\Asset\Repository $assetRepository         
    ){
        $this->wizpay_helper = $helper;
        $this->assetRepository = $assetRepository;
    }



    public function aroundGetProductDetailsHtml(
        $subject,
        $proceed,
        \Magento\Catalog\Model\Product 	$product
    )
    {

        $price = $product->getPrice();
        $min_price =  $product->getMinPrice();
        $max_price = $product->getMaxPrice();

        
        return $this->wizpay_helper->getWizpayMessage('List', $price, $this->assetRepository, $min_price, $max_price);    

    }
}