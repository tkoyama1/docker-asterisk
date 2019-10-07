#!/usr/bin/env php
<?php
/**
* autoban.php
*
*/

//error_reporting(E_ALL);
openlog("autoban", LOG_PID, LOG_LOCAL0);

require_once 'error.inc';
require_once 'ami.class.inc';
require_once 'ban.class.inc';
require_once 'nft.class.inc';

/**
* The AMI event handlers are defined here.
*/

function eventAbuse($event,$parameters,$server,$port) {
	global $ban, $nft;
//	trigger_error("eventAbuse (".$event.")");
	if (array_key_exists('RemoteAddress',$parameters)) {
		$address = explode('/',$parameters['RemoteAddress']);
		$ip = $address[2];
		if (!empty($ip)) {
			if ($ban->abuse($ip) !== false) {
				$nft->ban($ip);
			}
		}
	}
}

function eventPrune($event,$parameters,$server,$port) {
	global $ban, $nft;
//	trigger_error("eventPrune (".$event.")");
	$ips = $ban->prune();
	if ($ips !== false) {
		foreach ($ips as $ip) {
			$nft->unban($ip);
		}
	}
}

function eventReset($event,$parameters,$server,$port) {
	global $ban, $nft;
//	trigger_error("eventReset (".$event.")");
	$ban->reset();
	$nft->reset();
}


$nft = new \Nft();
$ban = new \Ban('/etc/asterisk/autoban.conf');
$ami = new \PHPAMI\Ami('/etc/asterisk/autoban.conf');
$ami->setLogLevel(2);

/**
* Register the AMI event handlers to their corresponding events.
*/
$ami->addEventHandler('FullyBooted',             'eventReset');
$ami->addEventHandler('FailedACL',               'eventAbuse');
$ami->addEventHandler('InvalidAccountID',        'eventAbuse');
$ami->addEventHandler('ChallengeResponseFailed', 'eventAbuse');
$ami->addEventHandler('InvalidPassword',         'eventAbuse');
$ami->addEventHandler('*',                       'eventPrune');

/**
* Start code execution.
* Wait 1s allowing Asterisk time to setup the Asterisk Management Interface (AMI).
* If autoban is activated try to connect to the AMI. If successful, start
* listening for events indefinitely. If connection fails, exit (errorhandler sets 
* $exit_code) and let the system supervisor start us again, so we can retry to
* connect.
* If autoban is deactivated stay in an infinite loop instead of exiting.
* Otherwise the system supervisor will relentlessly just try to restart us.
*/
sleep(1);
if ($ban->config['autoban']['enabled']) {
	if ($ami->connect(null,null,null,'on') === false) {
		trigger_error('Unable to connect to Asterisk Management Interface',E_USER_ERROR);
	} else {
		trigger_error('Connected to Asterisk Management Interface',E_USER_NOTICE);
	}
	while($exit_code === 0) { $ami->waitResponse(); }
} else {
	trigger_error('Disabled! Activate autoban using conf file (/etc/asterisk/autoban.conf)',E_USER_NOTICE);
	while(true) { sleep(60); }
}

/**
* We normally will not come here.
*/
$ami->disconnect();
?>
