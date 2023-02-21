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
        
        
        // $this->logger->info('wizpay - L33 Loading quotes'); 
        // $this->quoteCollection = $this->quoteCollectionFactory->create();
        // $this->logger->info('wizpay - L34 Loaded quotes');         


        // if($this->quoteCollection && $this->quoteCollection->getSize() > 0){
        //     $this->logger->info('wizpay - L38 get total quotes before filter ' . $this->quoteCollection->getSize());

        //     $this->logger->info('wizpay - L45 Add filter for quote'); 
        //     $tomorrow = date('Y-m-d', strtotime('now + 1day'));
        //     $two_days_before_today = date('Y-m-d', strtotime('now - 2day'));
        //     $this->logger->info('wizpay - L48 Search quotes from ' . $two_days_before_today . ', to ' . $tomorrow);
        //     $quote_collection = $this->quoteCollection->addFieldToFilter('created_at', array('from' => $two_days_before_today, 'to' => $tomorrow))->loadWithFilter();
        //     $this->logger->info('wizpay - L48 Reload all quotes');
            
        //     $quotes =  $quote_collection->getItems();            
            
        //     if(is_array($quotes) && count($quotes) > 0){

        //         $this->logger->info('wizpay - L60 get total quotes after get items ' .  count($quotes));
                
        //         foreach($quotes as $quote){
        //             $paymentMethod = $quote->getPayment();
        //             if(isset($paymentMethod) && $paymentMethod->getMethod() == 'wizpay'){
                        
        //                 $additionalInformation = $paymentMethod->getAdditionalInformation();
                        
        //                 if(isset($additionalInformation) && is_array($additionalInformation)
        //                     && array_key_exists('token',$additionalInformation )
        //                     && array_key_exists('transactionId',$additionalInformation )
        //                     && array_key_exists('mer',$additionalInformation )){
                                
        //                     $wz_token = $additionalInformation["token"];
        //                     $wzTxnId = $additionalInformation["transactionId"];
        //                     $merchantReference  = $additionalInformation["mer"];
                            
        //                     if(!empty($wz_token) && !empty($wzTxnId) && !empty($merchantReference)){
        //                         $this->logger->info('wizpay - L64 process quote, QuoteId=' . $quote->getId() . ', mre=' . $merchantReference );
        //                         try{
        //                             $this->wizpayOrderProcesser->process_quote($quote->getId(), $merchantReference);
        //                         }catch (Exception $e) {
        //                             $this->logger->info('wizpay - L68 process quote error, QuoteId=' . $quote->getId() . ', mre=' . $merchantReference . ', error = '. $e->getMessage());
        //                         }
        //                     }
        //                 }
        //             }
                    
    
        //         }
        //     }
            
            
        // }
    }
}