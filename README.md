<h2> 1.1    New Wizpay Installation with Composer (Recommended) </h2>
<p> This section outlines the steps to install Wizpay plugin using Composer. </p>

<ol>
	<li> Open Command Line Interface and navigate to the Magento directory on your server</li>
	<li> In CLI, run the below command to install Wizpay module: <br/> <em>composer require Wizpay/Wizpay</em> </li>
	<li> At the Composer request, enter your Magento marketplace credentials (public key - username, private key - password)</li>
	<li> Make sure that Composer finished the installation without errors </li>
	<li> In CLI, run the Magento setup upgrade: <br/> <em>sudo php bin/magento setup:upgrade</em> </li>
	<li> In CLI, run the Magento Dependencies Injection Compile: <br/> <em>sudo php bin/magento setup:di:compile</em> </li>
	<li> In CLI, run the Magento Static Content deployment: <br/> <em>sudo php bin/magento setup:static-content:deploy -f</em> </li>
	<li> Login to Magento Admin and navigate to System/Cache Management </li>
	<li> Flush the cache storage by selecting Flush Cache Storage </li>
</ol>

<h2> 1.2   New Wizpay Installation </h2>
<p>This section outlines the steps to install the Wizpay plugin for the first time.</p>

<p> Note: [MAGENTO] refers to the root folder where Magento is installed. </p>

<ol>
	<li> Download the Magento-Wizpay plugin - Available as a .zip or tar.gz file from the Wizpay GitHub directory. </li>
	<li> Unzip the file </li>
	<li> Create directory Wizpay/Wizpay in: <br/> <em>[MAGENTO]/app/code/</em></li>
	<li> Copy the files to <em>'Wizpay/Wizpay'</em> folder </li>
	<li> Open Command Line Interface </li>

	<li> In CLI, run the below command to enable Wizpay module: <br/> <em>php bin/magento module:enable Wizpay_Wizpay</em> </li>
	<li> In CLI, run the Magento setup upgrade: <br/> <em>php bin/magento setup:upgrade</em> </li>
	<li> In CLI, run the Magento Dependencies Injection Compile: <br/> <em>php bin/magento setup:di:compile</em> </li>
	<li> In CLI, run the Magento Static Content deployment: <br/> <em>php bin/magento setup:static-content:deploy</em> </li>

	<li> Login to Magento Admin and navigate to System/Cache Management </li>
	<li> Flush the cache storage by selecting Flush Cache Storage </li>
</ol>

<h2> 1.3	Wizpay Merchant Setup </h2>
<p> Complete the below steps to configure the merchant’s Wizpay Merchant Credentials in Magento Admin. </p>
<p> Note: Prerequisite for this section is to obtain an Wizpay Merchant ID and Secret Key from Wizpay. </p>

<ol>
	<li> Navigate to <em>Magento Admin/Stores/Configuration/Sales/Payment Methods/Wizpay</em> </li>
	<li> Enter the <em>Merchant ID</em> and <em>Merchant Key</em>. </li>
	<li> Enable Wizpay plugin using the <em>Enabled</em> checkbox. </li>
	<li> Configure the Wizpay API Mode (<em>Sandbox Mode</em> for testing on a staging instance and <em>Production Mode</em> for a live website and legitimate transactions). </li>
	<li> Save the configuration. </li>
	<li> Click the <em>Update Limits</em> button to retrieve the Minimum and Maximum Wizpay Order values.</li>
</ol>

<h2> 1.4	Upgrade Of Wizpay Installation using Composer</h2>
<p> This section outlines the steps to upgrade the currently installed Wizpay plugin version using composer. </p>
<p> Notes: </p>
<p>Prerequisite for this section is that the module should be installed using composer. Please see section 1.1 for guidelines to install Wizpay module using composer.</p>
<p>[MAGENTO] refers to the root folder where Magento is installed. </p>

<ol>
	<li> Open Command Line Interface and navigate to the Magento directory on your server</li>
	<li> In CLI, run the below command to update Wizpay module: <br/> <em>composer update Wizpay-global/module-Wizpay</em> </li>
	<li> Make sure that Composer finished the update without errors </li>
	<li> In CLI, run the Magento setup upgrade: <br/> <em>sudo php bin/magento setup:upgrade</em> </li>
	<li> In CLI, run the Magento Dependencies Injection Compile: <br/> <em>sudo php bin/magento setup:di:compile</em> </li>
	<li> In CLI, run the Magento Static Content deployment: <br/> <em>sudo php bin/magento setup:static-content:deploy -f</em> </li>
	<li> Login to Magento Admin and navigate to System/Cache Management </li>
	<li> Flush the cache storage by selecting Flush Cache Storage </li>
</ol>

<h2> 1.5	Upgrade Of Wizpay Installation </h2>
<p> This section outlines the steps to upgrade the currently installed Wizpay plugin version. </p>
<p> The process of upgrading the Wizpay plugin version involves the complete removal of Wizpay plugin files. </p>
<p> Note: [MAGENTO] refers to the root folder where Magento is installed. </p>

<ol>
	<li> Remove Files in: <em>[MAGENTO]/app/code/Wizpay/Wizpay</em></li>
	<li> Download the Magento-Wizpay plugin - Available as a .zip or tar.gz file from the Wizpay GitHub directory. </li>
	<li> Unzip the file </li>
	<li> Copy the files in folder to: <br/> <em>[MAGENTO]/app/code/Wizpay/Wizpay</em> </li>
	<li> Open Command Line Interface </li>

	<li> In CLI, run the below command to enable Wizpay module: <br/> <em>php bin/magento module:enable Wizpay_Wizpay</em> </li>
	<li> In CLI, run the Magento setup upgrade: <br/> <em>php bin/magento setup:upgrade</em> </li>
	<li> In CLI, run the Magento Dependencies Injection Compile: <br/> <em>php bin/magento setup:di:compile</em> </li>
	<li> In CLI, run the Magento Static Content deployment: <br/> <em>php bin/magento setup:static-content:deploy</em> </li>

	<li> Login to Magento Admin and navigate to System/Cache Management </li>
	<li> Flush the cache storage by selecting Flush Cache Storage </li>
</ol>
