<?php
 
namespace Wizpay\Wizpay\Block\Catalog\Block\Product;
 
use \Wizpay\Wizpay\Helper\Data;
use Magento\Framework\View\Asset\Repository;

class WzView
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



    private $displayBlocks = ['product.info.addtocart', 'customize.button'];



    public function afterToHtml(
        \Magento\Catalog\Block\Product\View $subject,
        $html
    ){

        if (in_array($subject->getNameInLayout(), $this->displayBlocks)){
            $product = $subject->getProduct();

            

            $wizpay_info = '';
            if(isset($product)){

                $price = $product->getPrice();

                $min_price =  $product->getMinPrice();
                $max_price = $product->getMaxPrice();


                $wizpay_info = $this->wizpay_helper->getWizpayMessage('Detail',  $price, $this->assetRepository, $min_price, $max_price);
            }
                  
            return  $html . $wizpay_info; 
        }else{
            return  $html;
        }
        
    }


}