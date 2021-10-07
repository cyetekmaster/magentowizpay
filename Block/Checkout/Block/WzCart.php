<?php
 
namespace Wizpay\Wizpay\Block\Checkout\Block;
 
use \Wizpay\Wizpay\Helper\Data;
use Magento\Framework\View\Asset\Repository;

class WzCart
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



    private $displayBlocks = ['checkout.cart.methods.bottom'];



    public function afterToHtml(
        \Magento\Checkout\Block\Cart $subject,
        $html
    ){

        if (in_array($subject->getNameInLayout(), $this->displayBlocks)){
           
            $grand_total = $subject->getQuote()->getGrandTotal();     
                             
            return  $html . $this->wizpay_helper->getWizpayMessage('Cart', $grand_total, $this->assetRepository); 
        }else{
            return  $html;
        }
        
    }


}