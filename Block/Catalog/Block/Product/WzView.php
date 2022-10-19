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



    private $displayBlocks = ['product.info.addtocart', 'customize.button', 'product.info.addtocart.additional'];



    public function afterToHtml(
        \Magento\Catalog\Block\Product\View $subject,
        $html
    ){

        if (in_array($subject->getNameInLayout(), $this->displayBlocks)){
            $product = $subject->getProduct();

            

            $wizpay_info = '';
            if(isset($product)){

                $price = $product->getPrice();
                $min_price = 0;
                $max_price = 9999;
                
                
                // Simple Product
                $price = $product->getPriceInfo()->getPrice('regular_price')->getValue();


                // Configurable product
                if ($product->getTypeId() == 'configurable') {
                    $basePrice = $product->getPriceInfo()->getPrice('regular_price');              
                    $price = $basePrice->getMinRegularAmount()->getValue();
                }

                // Bundle product
                if ($product->getTypeId() == 'bundle') {
                    $price = $product->getPriceInfo()->getPrice('regular_price')->getMinimalPrice()->getValue();
                    $min_price = $product->getPriceInfo()->getPrice('final_price')->getMinimalPrice()->getValue(); 
                    $max_price = $product->getPriceInfo()->getPrice('final_price')->getMaximalPrice()->getValue();             
                }


                // Group product
                if ($product->getTypeId() == 'grouped') {
                    $usedProds = $product->getTypeInstance(true)->getAssociatedProducts($product);            
                    foreach ($usedProds as $child) {
                        if ($child->getId() != $product->getId()) {
                              $price += $child->getPrice();
                        }
                    }
                }


                $wizpay_info = $this->wizpay_helper->getWizpayMessage('Detail',  $price, $this->assetRepository, $min_price, $max_price, $product->getId());
            }
                  
            return  $html . $wizpay_info; 
        }else{
            return  $html;
        }
        
    }


}