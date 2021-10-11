<h2> 1.1    New Wzizpay Installation with Composer (Recommended) </h2>
<p> This section outlines the steps to install Wzizpay plugin using Composer. </p>

<ol>
	<li> Open Command Line Interface and navigate to the Magento directory on your server</li>
	<li> In CLI, run the below command to install Wzizpay module: <br/> <em>composer require Wzizpay-global/module-Wzizpay</em> </li>
	<li> At the Composer request, enter your Magento marketplace credentials (public key - username, private key - password)</li>
	<li> Make sure that Composer finished the installation without errors </li>
	<li> In CLI, run the Magento setup upgrade: <br/> <em>php bin/magento setup:upgrade</em> </li>
	<li> In CLI, run the Magento Dependencies Injection Compile: <br/> <em>php bin/magento setup:di:compile</em> </li>
	<li> In CLI, run the Magento Static Content deployment: <br/> <em>php bin/magento setup:static-content:deploy</em> </li>
	<li> Login to Magento Admin and navigate to System/Cache Management </li>
	<li> Flush the cache storage by selecting Flush Cache Storage </li>
</ol>

<h2> 1.2   New Wzizpay Installation </h2>
<p>This section outlines the steps to install the Wzizpay plugin for the first time.</p>

<p> Note: [MAGENTO] refers to the root folder where Magento is installed. </p>

<ol>
	<li> Download the Magento-Wzizpay plugin - Available as a .zip or tar.gz file from the Wzizpay GitHub directory. </li>
	<li> Unzip the file </li>
	<li> Create directory Wzizpay/Wzizpay in: <br/> <em>[MAGENTO]/app/code/</em></li>
	<li> Copy the files to <em>'Wzizpay/Wzizpay'</em> folder </li>
	<li> Open Command Line Interface </li>
	<li> In CLI, run the below command to enable Wzizpay module: <br/> <em>php bin/magento module:enable Wzizpay_Wzizpay</em> </li>
	<li> In CLI, run the Magento setup upgrade: <br/> <em>php bin/magento setup:upgrade</em> </li>
	<li> In CLI, run the Magento Dependencies Injection Compile: <br/> <em>php bin/magento setup:di:compile</em> </li>
	<li> In CLI, run the Magento Static Content deployment: <br/> <em>php bin/magento setup:static-content:deploy</em> </li>
	<li> Login to Magento Admin and navigate to System/Cache Management </li>
	<li> Flush the cache storage by selecting Flush Cache Storage </li>
</ol>

<h2> 1.3	Wzizpay Merchant Setup </h2>
<p> Complete the below steps to configure the merchant’s Wzizpay Merchant Credentials in Magento Admin. </p>
<p> Note: Prerequisite for this section is to obtain an Wzizpay Merchant ID and Secret Key from Wzizpay. </p>

<ol>
	<li> Navigate to <em>Magento Admin/Stores/Configuration/Sales/Payment Methods/Wzizpay</em> </li>
	<li> Enter the <em>Merchant ID</em> and <em>Merchant Key</em>. </li>
	<li> Enable Wzizpay plugin using the <em>Enabled</em> checkbox. </li>
	<li> Configure the Wzizpay API Mode (<em>Sandbox Mode</em> for testing on a staging instance and <em>Production Mode</em> for a live website and legitimate transactions). </li>
	<li> Save the configuration. </li>
	<li> Click the <em>Update Limits</em> button to retrieve the Minimum and Maximum Wzizpay Order values.</li>
</ol>

<h2> 1.4	Upgrade Of Wzizpay Installation using Composer</h2>
<p> This section outlines the steps to upgrade the currently installed Wzizpay plugin version using composer. </p>
<p> Notes: </p>
<p>Prerequisite for this section is that the module should be installed using composer. Please see section 1.1 for guidelines to install Wzizpay module using composer.</p>
<p>[MAGENTO] refers to the root folder where Magento is installed. </p>

<ol>
	<li> Open Command Line Interface and navigate to the Magento directory on your server</li>
	<li> In CLI, run the below command to update Wzizpay module: <br/> <em>composer update Wzizpay-global/module-Wzizpay</em> </li>
	<li> Make sure that Composer finished the update without errors </li>
	<li> In CLI, run the Magento setup upgrade: <br/> <em>php bin/magento setup:upgrade</em> </li>
	<li> In CLI, run the Magento Dependencies Injection Compile: <br/> <em>php bin/magento setup:di:compile</em> </li>
	<li> In CLI, run the Magento Static Content deployment: <br/> <em>php bin/magento setup:static-content:deploy</em> </li>
	<li> Login to Magento Admin and navigate to System/Cache Management </li>
	<li> Flush the cache storage by selecting Flush Cache Storage </li>
</ol>

<h2> 1.5	Upgrade Of Wzizpay Installation </h2>
<p> This section outlines the steps to upgrade the currently installed Wzizpay plugin version. </p>
<p> The process of upgrading the Wzizpay plugin version involves the complete removal of Wzizpay plugin files. </p>
<p> Note: [MAGENTO] refers to the root folder where Magento is installed. </p>

<ol>
	<li> Remove Files in: <em>[MAGENTO]/app/code/Wzizpay/Wzizpay</em></li>
	<li> Download the Magento-Wzizpay plugin - Available as a .zip or tar.gz file from the Wzizpay GitHub directory. </li>
	<li> Unzip the file </li>
	<li> Copy the files in folder to: <br/> <em>[MAGENTO]/app/code/Wzizpay/Wzizpay</em> </li>
	<li> Open Command Line Interface </li>
	<li> In CLI, run the below command to enable Wzizpay module: <br/> <em>php bin/magento module:enable Wzizpay_Wzizpay</em> </li>
	<li> In CLI, run the Magento setup upgrade: <br/> <em>php bin/magento setup:upgrade</em> </li>
	<li> In CLI, run the Magento Dependencies Injection Compile: <br/> <em>php bin/magento setup:di:compile</em> </li>
	<li> In CLI, run the Magento Static Content deployment: <br/> <em>php bin/magento setup:static-content:deploy</em> </li>
	<li> Login to Magento Admin and navigate to System/Cache Management </li>
	<li> Flush the cache storage by selecting Flush Cache Storage </li>
</ol>