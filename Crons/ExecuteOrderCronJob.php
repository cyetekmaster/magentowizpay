<?php
namespace Wizpay\Wizpay\Crons;

use Psr\Log\LoggerInterface;

use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;


class ExecuteOrderCronJob {
    protected $logger;
    protected $quoteCollection;
    private $quoteCollectionFactory;
    private $wizpayOrderProcesser;

    public function __construct(
        LoggerInterface $logger,
        \Magento\Quote\Model\ResourceModel\Quote\Collection $quoteCollection,
        \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory $quoteCollectionFactory,
        \Wizpay\Wizpay\Controller\Index\Success $wizpayOrderProcesser
        ) {
        $this->logger = $logger;
        
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->wizpayOrderProcesser = $wizpayOrderProcesser;
    }

   /**
    * Write to system.log
    *
    * @return void
    */
    public function execute() {
        $this->logger->info('wizpay - ExecuteOrderCronJob Cron Works run every 5 mins');
        
        
        $this->logger->info('wizpay - L33 Loading quotes'); 
        $this->quoteCollection = $this->quoteCollectionFactory->create();
        $this->logger->info('wizpay - L34 Loaded quotes'); 

        if($this->quoteCollection && $this->quoteCollection->getSize() > 0){
            $this->logger->info('wizpay - L38 get total quotes ' . $this->quoteCollection->getSize());
            
            $quotes = $this->quoteCollection->getItems();
            
            if(is_array($quotes) && count($quotes) > 0){
                
                foreach($quotes as $quote){
                    $paymentMethod = $quote->getPayment();
                    if(isset($paymentMethod) && $paymentMethod->getMethod() == 'wizpay'){
                        
                        $additionalInformation = $paymentMethod->getAdditionalInformation();
                        
                        if(isset($additionalInformation) && is_array($additionalInformation)
                            && array_key_exists('token',$additionalInformation )
                            && array_key_exists('transactionId',$additionalInformation )
                            && array_key_exists('mer',$additionalInformation )){
                                
                            $wz_token = $additionalInformation["token"];
                            $wzTxnId = $additionalInformation["transactionId"];
                            $merchantReference  = $additionalInformation["mer"];
                            
                            if(!empty($wz_token) && !empty($wzTxnId) && !empty($merchantReference)){
                                $this->logger->info('wizpay - L64 process quote, QuoteId=' . $quote->getId() . ', mre=' . $merchantReference );
                                $this->wizpayOrderProcesser->process_quote($quote->getId(), $merchantReference);
                            }
                        }
                    }
                    
    
                }
            }
            
            
        }
    }
}